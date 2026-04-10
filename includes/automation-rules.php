<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hae infopaketin oikea kieliversio
 *
 * @param int    $pkg_id Infopaketin ID
 * @param string $lang   Haluttu kielikoodi
 * @return int Käännetyn infopaketin ID tai alkuperäinen jos käännöstä ei löydy
 */
function tjobs_get_translated_package_id( $pkg_id, $lang ) {
    if ( empty( $pkg_id ) || empty( $lang ) ) {
        return $pkg_id;
    }

    // Polylang
    if ( function_exists( 'pll_get_post' ) ) {
        $translated_id = pll_get_post( $pkg_id, $lang );
        if ( $translated_id ) {
            return $translated_id;
        }
    }

    // WPML
    if ( function_exists( 'icl_object_id' ) ) {
        $translated_id = icl_object_id( $pkg_id, 'tjobs_infopackage', false, $lang );
        if ( $translated_id ) {
            return $translated_id;
        }
    }

    // Fallback: palauta alkuperäinen
    return $pkg_id;
}

/**
 * Ratkaise mikä infopaketti näytetään työpaikalle
 *
 * @param int    $job_post_id Työpaikan post ID
 * @param string $lang        Kielikoodi (null = nykyinen kieli)
 * @return int|null Infopaketin ID tai null jos ei löydy
 */
function tjobs_resolve_infopackage( $job_post_id, $lang = null ) {
if ( ! $job_post_id ) {
    return null;
}

if ( $lang === null ) {
    $lang = tjobs_get_current_lang();
}

// 1. Tarkista manuaalinen liitos
$manual_link = get_post_meta( $job_post_id, '_tjobs_linked_infopackage', true );
if ( $manual_link && is_numeric( $manual_link ) ) {
    $manual_link = absint( $manual_link );
    // Tarkista että paketti on julkaistu
    if ( get_post_status( $manual_link ) === 'publish' ) {
        // Palauta oikea kieliversio
        return tjobs_get_translated_package_id( $manual_link, $lang );
    }
}

// 2. Automaattinen valinta pisteytyksen perusteella
$default_lang = tjobs_get_default_lang();

// Hae kaikki julkaistut infopaketit oletuskielellä (välttää duplikaatit).
// Jos kielisuodatus on käytössä (Polylang) mutta ei palauta tuloksia (esim. infopaketeille
// ei ole asetettu kielimääritystä), haetaan uudelleen ilman kielisuodatusta.
$lang_query_arg  = function_exists( 'pll_default_language' ) ? $default_lang : '';
$packages_query  = new WP_Query( array(
    'post_type'      => 'tjobs_infopackage',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'lang'           => $lang_query_arg,
) );

if ( ! $packages_query->have_posts() && ! empty( $lang_query_arg ) ) {
    $packages_query = new WP_Query( array(
        'post_type'      => 'tjobs_infopackage',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ) );
}

if ( ! $packages_query->have_posts() ) {
    return null;
}

$job_post    = get_post( $job_post_id );
$job_title   = strtolower( $job_post->post_title );
$job_content = strtolower( strip_tags( $job_post->post_content ) );
$job_excerpt = strtolower( strip_tags( $job_post->post_excerpt ) );
$job_text    = $job_title . ' ' . $job_content . ' ' . $job_excerpt;

$best_score      = 0;
$best_package_id = null;

while ( $packages_query->have_posts() ) {
    $packages_query->the_post();
    $pkg_id = get_the_ID();
    $score  = 0;

    // Sijainti-match (paino 10)
    $auto_location = get_post_meta( $pkg_id, '_tjobs_info_auto_location', true );
    if ( ! empty( $auto_location ) ) {
        $locations = array_map( 'trim', explode( ',', strtolower( $auto_location ) ) );
        foreach ( $locations as $location ) {
            if ( ! empty( $location ) && strpos( $job_text, $location ) !== false ) {
                $score += 10;
            }
        }
    }

    // Avainsana-match (paino 5)
    $auto_keywords = get_post_meta( $pkg_id, '_tjobs_info_auto_keywords', true );
    if ( ! empty( $auto_keywords ) ) {
        $keywords = array_map( 'trim', explode( ',', strtolower( $auto_keywords ) ) );
        foreach ( $keywords as $keyword ) {
            if ( ! empty( $keyword ) && strpos( $job_text, $keyword ) !== false ) {
                $score += 5;
            }
        }
    }

    // Päivitä paras match
    if ( $score > $best_score ) {
        $best_score      = $score;
        $best_package_id = $pkg_id;
    }
}

wp_reset_postdata();

// Jos löytyi match, palauta sen kieliversio
if ( $best_package_id ) {
    return tjobs_get_translated_package_id( $best_package_id, $lang );
}

// Default infopackage fallback
$opts        = tjobs_get_settings();
$default_pkg = isset( $opts['default_infopackage'] ) ? absint( $opts['default_infopackage'] ) : 0;
if ( $default_pkg && get_post_status( $default_pkg ) === 'publish' ) {
    return tjobs_get_translated_package_id( $default_pkg, $lang );
}

return null;
}

/**
 * Lisää meta box työpaikka-CPT:lle infopaketin manuaaliseen valintaan
 */
function tjobs_add_job_infopackage_meta_box() {
    add_meta_box(
        'tjobs_job_infopackage_link',
        __( 'Infopaketti', 'tapojarvijobs' ),
        'tjobs_render_job_infopackage_meta_box',
        'tjobs_tyopaikat',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'tjobs_add_job_infopackage_meta_box' );

/**
 * Renderöi infopaketti-valinta meta box
 *
 * @param WP_Post $post Nykyinen post.
 */
function tjobs_render_job_infopackage_meta_box( $post ) {
wp_nonce_field( 'tjobs_save_job_infopackage', 'tjobs_job_infopackage_nonce' );

$linked_package = get_post_meta( $post->ID, '_tjobs_linked_infopackage', true );

// Hae kaikki julkaistut infopaketit
$packages = get_posts( array(
    'post_type'      => 'tjobs_infopackage',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
) );

// Hae automaation ehdotus
$auto_suggestion       = tjobs_resolve_infopackage( $post->ID );
$auto_suggestion_title = '';
if ( $auto_suggestion ) {
    $auto_post = get_post( $auto_suggestion );
    if ( $auto_post ) {
        $auto_suggestion_title = $auto_post->post_title;
    }
}

?>
<p>
    <label for="tjobs_linked_infopackage"><strong><?php esc_html_e( 'Valitse infopaketti', 'tapojarvijobs' ); ?></strong></label><br>
    <select id="tjobs_linked_infopackage" name="tjobs_linked_infopackage" style="width:100%;">
        <option value=""><?php echo esc_html( tjobs_i18n( 'admin.automatic_selection' ) ); ?></option>
        <?php foreach ( $packages as $pkg ) : ?>
            <option value="<?php echo esc_attr( $pkg->ID ); ?>" <?php selected( $linked_package, $pkg->ID ); ?>>
                <?php echo esc_html( $pkg->post_title ); ?>
            </option>
        <?php endforeach; ?>
    </select>
</p>

<?php if ( $auto_suggestion_title ) : ?>
    <p style="padding:8px; background:#f0f0f0; border-left:3px solid #2271b1; font-size:12px;">
        <?php echo esc_html( tjobs_i18n( 'admin.automation_suggestion' ) ); ?>:<br>
        <strong><?php echo esc_html( $auto_suggestion_title ); ?></strong>
    </p>
<?php endif; ?>

<?php
// Näytä kieliversioiden saatavuus
if ( $linked_package || $auto_suggestion ) {
    $check_package_id = $linked_package ? $linked_package : $auto_suggestion;
    $available_langs  = tjobs_get_available_languages();

    echo '<div style="margin-top:15px; padding:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;">';
    echo '<p style="margin:0 0 8px 0; font-weight:bold;">' . esc_html( tjobs_i18n( 'admin.available_languages' ) ) . ':</p>';

    foreach ( $available_langs as $lang ) {
        $translated_id = tjobs_get_translated_package_id( $check_package_id, $lang );
        $is_available  = $translated_id && get_post_status( $translated_id ) === 'publish';
        $icon          = $is_available ? '✅' : '❌';
        $edit_link     = '';

        if ( $is_available ) {
            $edit_url  = admin_url( 'post.php?post=' . $translated_id . '&action=edit' );
            $edit_link = ' <a href="' . esc_url( $edit_url ) . '" target="_blank" style="font-size:11px;">(' . esc_html( tjobs_i18n( 'admin.edit' ) ) . ')</a>';
        }

        echo '<div style="margin-bottom:4px;">' . $icon . ' <strong>' . esc_html( strtoupper( $lang ) ) . '</strong>' . $edit_link . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    echo '</div>';
}
}

/**
 * Tallenna työpaikan infopaketti-linkitys
 *
 * @param int $post_id Post ID.
 */
function tjobs_save_job_infopackage_meta( $post_id ) {
    // Tarkista nonce
    if ( ! isset( $_POST['tjobs_job_infopackage_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tjobs_job_infopackage_nonce'] ) ), 'tjobs_save_job_infopackage' ) ) {
        return;
    }

    // Tarkista autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // Tarkista oikeudet
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Tallenna linkitys
    if ( isset( $_POST['tjobs_linked_infopackage'] ) ) {
        $package_id = sanitize_text_field( wp_unslash( $_POST['tjobs_linked_infopackage'] ) );
        if ( empty( $package_id ) ) {
            delete_post_meta( $post_id, '_tjobs_linked_infopackage' );
        } else {
            update_post_meta( $post_id, '_tjobs_linked_infopackage', absint( $package_id ) );
        }

        // Päivitä HTML-välimuistin cache bump
        update_option( 'tjobs_cache_bump', time() );
    }
}
add_action( 'save_post_tjobs_tyopaikat', 'tjobs_save_job_infopackage_meta' );

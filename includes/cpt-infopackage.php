<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Rekisteröi Infopaketti Custom Post Type
 */
function tjobs_register_cpt_infopackage() {
    $labels = array(
        'name'               => __( 'TJobs: Infopaketit', 'tapojarvijobs' ),
        'singular_name'      => __( 'TJobs: Infopaketti', 'tapojarvijobs' ),
        'menu_name'          => __( 'TJobs Infopaketit', 'tapojarvijobs' ),
        'add_new'            => __( 'Lisää uusi', 'tapojarvijobs' ),
        'add_new_item'       => __( 'Lisää uusi infopaketti', 'tapojarvijobs' ),
        'edit_item'          => __( 'Muokkaa infopakettia', 'tapojarvijobs' ),
        'new_item'           => __( 'Uusi infopaketti', 'tapojarvijobs' ),
        'view_item'          => __( 'Näytä infopaketti', 'tapojarvijobs' ),
        'search_items'       => __( 'Etsi infopaketteja', 'tapojarvijobs' ),
        'not_found'          => __( 'Infopaketteja ei löytynyt', 'tapojarvijobs' ),
        'not_found_in_trash' => __( 'Infopaketteja ei löytynyt roskakorista', 'tapojarvijobs' ),
    );

    $args = array(
        'labels'              => $labels,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'tjobs-v2-settings',
        'show_in_rest'        => true,
        'supports'            => array( 'title' ),
        'capability_type'     => 'post',
        'has_archive'         => false,
        'hierarchical'        => false,
        'rewrite'             => false,
        'menu_icon'           => 'dashicons-info',
    );

    register_post_type( 'tjobs_infopackage', $args );
}
add_action( 'init', 'tjobs_register_cpt_infopackage' );

/**
 * Rekisteröi CPT Polylangin käännettäväksi
 */
function tjobs_register_infopackage_for_polylang( $post_types ) {
    if ( ! in_array( 'tjobs_infopackage', $post_types, true ) ) {
        $post_types[] = 'tjobs_infopackage';
    }
    return $post_types;
}
add_filter( 'pll_get_post_types', 'tjobs_register_infopackage_for_polylang', 10, 1 );

/**
 * Lisää meta boxit
 */
function tjobs_add_infopackage_meta_boxes() {
    // Pääsisältö – tabbattu metabox
    add_meta_box(
        'tjobs_infopackage_main',
        __( 'Infopaketti', 'tapojarvijobs' ),
        'tjobs_render_infopackage_main_meta_box',
        'tjobs_infopackage',
        'normal',
        'high'
    );

    // Kieliversio-badge
    add_meta_box(
        'tjobs_infopackage_language',
        __( 'Kieliversio', 'tapojarvijobs' ),
        'tjobs_render_infopackage_language_meta_box',
        'tjobs_infopackage',
        'side',
        'high'
    );
}
add_action( 'add_meta_boxes', 'tjobs_add_infopackage_meta_boxes' );

/**
 * Renderöi kieliversio-badge
 */
function tjobs_render_infopackage_language_meta_box( $post ) {
$current_lang = 'FI'; // default

// Polylang
if ( function_exists( 'pll_get_post_language' ) ) {
    $lang = pll_get_post_language( $post->ID, 'slug' );
    if ( $lang ) {
        $current_lang = strtoupper( tjobs_normalize_lang_code( $lang ) );
    }
}
// WPML
elseif ( function_exists( 'wpml_get_language_information' ) ) {
    $lang_info = wpml_get_language_information( null, $post->ID );
    if ( isset( $lang_info['locale'] ) ) {
        $current_lang = strtoupper( tjobs_normalize_lang_code( $lang_info['locale'] ) );
    }
}

echo '<div class="tjobs-metabox-lang-badge">';
echo esc_html( tjobs_i18n( 'admin.language_version' ) ) . ': <span class="tjobs-lang-code">' . esc_html( $current_lang ) . '</span>';
echo '</div>';
}

/**
 * Renderöi päämetabox – tabbattu UI (Yleiset / Media / Kysymykset & Palaute / Automaatio & Yhteystiedot)
 */
function tjobs_render_infopackage_main_meta_box( $post ) {
wp_nonce_field( 'tjobs_save_infopackage', 'tjobs_infopackage_nonce' );

// ── Fetch all meta values ────────────────────────────────────────────────────
$intro         = get_post_meta( $post->ID, '_tjobs_info_intro', true );
$highlights    = get_post_meta( $post->ID, '_tjobs_info_highlights', true );
$contact_name  = get_post_meta( $post->ID, '_tjobs_info_contact_name', true );
$contact_email = get_post_meta( $post->ID, '_tjobs_info_contact_email', true );
$contact_phone = get_post_meta( $post->ID, '_tjobs_info_contact_phone', true );

$video_url = get_post_meta( $post->ID, '_tjobs_info_video_url', true );
$gallery   = get_post_meta( $post->ID, '_tjobs_info_gallery', true );

$questions = get_post_meta( $post->ID, '_tjobs_info_questions', true );

$score_feedback_rules = get_post_meta( $post->ID, '_tjobs_score_feedback_rules', true );

$auto_location = get_post_meta( $post->ID, '_tjobs_info_auto_location', true );
$auto_keywords = get_post_meta( $post->ID, '_tjobs_info_auto_keywords', true );

$sections = get_post_meta( $post->ID, '_tjobs_info_sections', true );

if ( ! is_array( $highlights ) )           { $highlights = array(); }
if ( ! is_array( $gallery ) )              { $gallery = array(); }
if ( ! is_array( $questions ) )            { $questions = array(); }
if ( ! is_array( $score_feedback_rules ) ) { $score_feedback_rules = array(); }
if ( ! is_array( $sections ) )             { $sections = array(); }

?>
<div class="tjobs-metabox-wrap">

    <!-- Tab navigation -->
    <div class="tjobs-admin-tabs__nav">
        <button type="button" class="tjobs-admin-tab-btn is-active" data-tab="general"><?php esc_html_e( 'Yleiset', 'tapojarvijobs' ); ?></button>
        <button type="button" class="tjobs-admin-tab-btn" data-tab="sections"><?php esc_html_e( 'Tietosisällöt', 'tapojarvijobs' ); ?></button>
        <button type="button" class="tjobs-admin-tab-btn" data-tab="media"><?php esc_html_e( 'Media', 'tapojarvijobs' ); ?></button>
        <button type="button" class="tjobs-admin-tab-btn" data-tab="questions"><?php esc_html_e( 'Kysymykset & Palaute', 'tapojarvijobs' ); ?></button>
        <button type="button" class="tjobs-admin-tab-btn" data-tab="automation"><?php esc_html_e( 'Automaatio & Yhteystiedot', 'tapojarvijobs' ); ?></button>
    </div>

    <!-- ── TAB 1: Yleiset ────────────────────────────────────────────────── -->
    <div class="tjobs-admin-tab-content is-active" data-tab-content="general">

        <div class="tjobs-metabox-field">
            <label class="tjobs-metabox-label" for="tjobs_info_intro">
                <?php esc_html_e( 'Esittelyteksti', 'tapojarvijobs' ); ?>
            </label>
            <textarea id="tjobs_info_intro" name="tjobs_info_intro" rows="5" class="tjobs-metabox-textarea"><?php echo esc_textarea( $intro ); ?></textarea>
        </div>

        <div class="tjobs-metabox-field">
            <label class="tjobs-metabox-label">
                <?php esc_html_e( 'Highlights / Nostot', 'tapojarvijobs' ); ?>
                <span class="tjobs-metabox-desc"><?php esc_html_e( 'Lyhyitä nostokohtia infopaketista', 'tapojarvijobs' ); ?></span>
            </label>
            <div id="tjobs-highlights-container" class="tjobs-metabox-list">
                <?php foreach ( $highlights as $highlight ) : ?>
                <div class="tjobs-list-row">
                    <input type="text" name="tjobs_info_highlights[]" value="<?php echo esc_attr( $highlight ); ?>" class="tjobs-metabox-input" />
                    <button type="button" class="tjobs-metabox-btn tjobs-metabox-btn-remove tjobs-remove-highlight" aria-label="<?php esc_attr_e( 'Poista', 'tapojarvijobs' ); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="tjobs-add-highlight" class="tjobs-metabox-btn tjobs-metabox-btn-add">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Lisää highlight', 'tapojarvijobs' ); ?>
            </button>
        </div>

    </div><!-- /tab general -->

    <!-- ── TAB 2: Tietosisällöt ──────────────────────────────────────────── -->
    <div class="tjobs-admin-tab-content" data-tab-content="sections">

        <!-- Quick-add preset buttons -->
        <div class="tjobs-section-presets">
            <button type="button" class="tjobs-section-preset-btn" data-icon="🏠" data-fi="Asuminen" data-en="Housing" data-sv="Boende" data-it="Alloggio">🏠 <?php esc_html_e( 'Asuminen', 'tapojarvijobs' ); ?></button>
            <button type="button" class="tjobs-section-preset-btn" data-icon="🏪" data-fi="Palvelut" data-en="Local Services" data-sv="Tjänster" data-it="Servizi">🏪 <?php esc_html_e( 'Palvelut', 'tapojarvijobs' ); ?></button>
            <button type="button" class="tjobs-section-preset-btn" data-icon="🚗" data-fi="Kulkuyhteydet" data-en="Transportation" data-sv="Transport" data-it="Trasporti">🚗 <?php esc_html_e( 'Kulkuyhteydet', 'tapojarvijobs' ); ?></button>
            <button type="button" class="tjobs-section-preset-btn" data-icon="💰" data-fi="Työsuhde-edut" data-en="Benefits" data-sv="Förmåner" data-it="Benefici">💰 <?php esc_html_e( 'Työsuhde-edut', 'tapojarvijobs' ); ?></button>
            <button type="button" class="tjobs-section-preset-btn" data-icon="🌍" data-fi="Muutto ja relocation" data-en="Relocation" data-sv="Flytt och relokation" data-it="Trasferimento">🌍 <?php esc_html_e( 'Muutto ja relocation', 'tapojarvijobs' ); ?></button>
            <button type="button" class="tjobs-section-preset-btn" data-icon="ℹ️" data-fi="Vapaa osio" data-en="Custom Section" data-sv="Fri sektion" data-it="Sezione libera">ℹ️ <?php esc_html_e( 'Vapaa osio', 'tapojarvijobs' ); ?></button>
        </div>

        <!-- Section repeater rows -->
        <div id="tjobs-sections-container">
            <?php
            foreach ( $sections as $index => $section ) {
                $s_icon    = isset( $section['icon'] ) ? $section['icon'] : '';
                $s_title   = isset( $section['title'] ) ? $section['title'] : '';
                $s_content = isset( $section['content'] ) ? $section['content'] : '';
                ?>
                <div class="tjobs-section-row">
                    <div class="tjobs-section-header">
                        <strong><?php echo esc_html( sprintf( __( 'Osio #%d', 'tapojarvijobs' ), $index + 1 ) ); ?></strong>
                        <button type="button" class="tjobs-metabox-btn tjobs-metabox-btn-remove tjobs-remove-section" aria-label="<?php esc_attr_e( 'Poista', 'tapojarvijobs' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                    <div class="tjobs-metabox-grid tjobs-section-row__grid">
                        <div class="tjobs-metabox-field">
                            <label class="tjobs-metabox-label"><?php esc_html_e( 'Ikoni', 'tapojarvijobs' ); ?></label>
                            <input type="text" name="tjobs_info_sections[<?php echo $index; ?>][icon]" value="<?php echo esc_attr( $s_icon ); ?>" class="tjobs-metabox-input" placeholder="🏠" />
                        </div>
                        <div class="tjobs-metabox-field">
                            <label class="tjobs-metabox-label"><?php esc_html_e( 'Otsikko', 'tapojarvijobs' ); ?></label>
                            <input type="text" name="tjobs_info_sections[<?php echo $index; ?>][title]" value="<?php echo esc_attr( $s_title ); ?>" class="tjobs-metabox-input" />
                        </div>
                    </div>
                    <div class="tjobs-metabox-field">
                        <label class="tjobs-metabox-label"><?php esc_html_e( 'Sisältö', 'tapojarvijobs' ); ?></label>
                        <textarea name="tjobs_info_sections[<?php echo $index; ?>][content]" rows="5" class="tjobs-metabox-textarea"><?php echo esc_textarea( $s_content ); ?></textarea>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>

        <button type="button" id="tjobs-add-section" class="tjobs-metabox-btn tjobs-metabox-btn-add">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'Lisää osio', 'tapojarvijobs' ); ?>
        </button>

    </div><!-- /tab sections -->

    <!-- ── TAB 3: Media ──────────────────────────────────────────────────── -->
    <div class="tjobs-admin-tab-content" data-tab-content="media">

        <div class="tjobs-metabox-field">
            <label class="tjobs-metabox-label" for="tjobs_info_video_url">
                <?php esc_html_e( 'Video', 'tapojarvijobs' ); ?>
                <span class="tjobs-metabox-desc"><?php esc_html_e( 'YouTube tai Vimeo URL – parsitaan automaattisesti embed-muotoon', 'tapojarvijobs' ); ?></span>
            </label>
            <input type="url" name="tjobs_info_video_url" id="tjobs_info_video_url" value="<?php echo esc_attr( $video_url ); ?>" class="tjobs-metabox-input" placeholder="https://www.youtube.com/watch?v=xxxxx" />
        </div>

        <div class="tjobs-metabox-field">
            <label class="tjobs-metabox-label">
                <?php esc_html_e( 'Kuvagalleria', 'tapojarvijobs' ); ?>
                <span class="tjobs-metabox-desc"><?php esc_html_e( 'Kuvat näytetään modalissa galleria-näkymässä', 'tapojarvijobs' ); ?></span>
            </label>
            <div id="tjobs-gallery-container" class="tjobs-gallery-grid">
                <?php
                foreach ( $gallery as $attachment_id ) {
                    $image_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
                    if ( $image_url ) {
                        ?>
                        <div class="tjobs-gallery-item" data-id="<?php echo esc_attr( $attachment_id ); ?>">
                            <img src="<?php echo esc_url( $image_url ); ?>" alt="" />
                            <button type="button" class="tjobs-gallery-remove tjobs-remove-gallery-item" aria-label="<?php esc_attr_e( 'Poista', 'tapojarvijobs' ); ?>">&times;</button>
                            <input type="hidden" name="tjobs_info_gallery[]" value="<?php echo esc_attr( $attachment_id ); ?>" />
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            <button type="button" id="tjobs-add-gallery-images" class="tjobs-metabox-btn tjobs-metabox-btn-add">
                <span class="dashicons dashicons-format-gallery"></span>
                <?php esc_html_e( 'Lisää kuvia', 'tapojarvijobs' ); ?>
            </button>
        </div>

    </div><!-- /tab media -->

    <!-- ── TAB 3: Kysymykset & Palaute ───────────────────────────────────── -->
    <div class="tjobs-admin-tab-content" data-tab-content="questions">

        <div id="tjobs-questions-container">
            <?php
            foreach ( $questions as $index => $q ) {
                tjobs_render_question_row( $index, $q );
            }
            ?>
        </div>
        <button type="button" id="tjobs-add-question" class="tjobs-metabox-btn tjobs-metabox-btn-add">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'Lisää kysymys', 'tapojarvijobs' ); ?>
        </button>

        <!-- Score-based feedback rules -->
        <div class="tjobs-metabox-section-title tjobs-metabox-section-title--spaced">
            <?php esc_html_e( 'Pistemäinen yleispalaute', 'tapojarvijobs' ); ?>
            <span class="tjobs-metabox-desc"><?php esc_html_e( 'Valinnainen. Näytetään kaikkien kysymysten jälkeen. Säännöt arvioidaan suurimmasta pienimpään.', 'tapojarvijobs' ); ?></span>
        </div>

        <div id="tjobs-score-rules-container">
            <?php foreach ( $score_feedback_rules as $ri => $rule ) : ?>
            <div class="tjobs-score-rule-row tjobs-list-row">
                <span class="tjobs-score-rule-label"><?php esc_html_e( 'Jos virheitä >=', 'tapojarvijobs' ); ?></span>
                <input type="number" name="tjobs_score_feedback_rules[<?php echo esc_attr( $ri ); ?>][min_errors]"
                    value="<?php echo esc_attr( isset( $rule['min_errors'] ) ? $rule['min_errors'] : 0 ); ?>"
                    class="tjobs-metabox-input tjobs-score-rule-number" min="0" />
                <textarea name="tjobs_score_feedback_rules[<?php echo esc_attr( $ri ); ?>][message]" rows="2"
                    class="tjobs-metabox-textarea"
                    placeholder="<?php esc_attr_e( 'Palauteviesti...', 'tapojarvijobs' ); ?>"><?php echo esc_textarea( isset( $rule['message'] ) ? $rule['message'] : '' ); ?></textarea>
                <button type="button" class="tjobs-metabox-btn tjobs-metabox-btn-remove tjobs-remove-score-rule" aria-label="<?php esc_attr_e( 'Poista', 'tapojarvijobs' ); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="tjobs-add-score-rule" class="tjobs-metabox-btn tjobs-metabox-btn-add">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'Lisää sääntö', 'tapojarvijobs' ); ?>
        </button>

    </div><!-- /tab questions -->

    <!-- ── TAB 4: Automaatio & Yhteystiedot ──────────────────────────────── -->
    <div class="tjobs-admin-tab-content" data-tab-content="automation">

        <div class="tjobs-metabox-field">
            <label class="tjobs-metabox-label" for="tjobs_info_auto_location">
                <?php esc_html_e( 'Sijainti sisältää', 'tapojarvijobs' ); ?>
                <span class="tjobs-metabox-desc"><?php esc_html_e( 'Pilkulla erotettuna', 'tapojarvijobs' ); ?></span>
            </label>
            <input type="text" id="tjobs_info_auto_location" name="tjobs_info_auto_location" value="<?php echo esc_attr( $auto_location ); ?>" class="tjobs-metabox-input" placeholder="Helsinki, Espoo, Vantaa" />
        </div>
        <div class="tjobs-metabox-field">
            <label class="tjobs-metabox-label" for="tjobs_info_auto_keywords">
                <?php esc_html_e( 'Otsikko sisältää', 'tapojarvijobs' ); ?>
                <span class="tjobs-metabox-desc"><?php esc_html_e( 'Pilkulla erotettuna', 'tapojarvijobs' ); ?></span>
            </label>
            <input type="text" id="tjobs_info_auto_keywords" name="tjobs_info_auto_keywords" value="<?php echo esc_attr( $auto_keywords ); ?>" class="tjobs-metabox-input" placeholder="kehittäjä, designer" />
        </div>
        <p class="tjobs-metabox-notice">
            <span class="dashicons dashicons-info"></span>
            <?php esc_html_e( 'Automaatiosäännöt ovat kieliriippumattomia ja pisteyttävät työpaikkoja automaattisesti.', 'tapojarvijobs' ); ?>
        </p>

        <div class="tjobs-metabox-section-title"><?php esc_html_e( 'Yhteyshenkilö', 'tapojarvijobs' ); ?></div>
        <div class="tjobs-metabox-grid">
            <div class="tjobs-metabox-field">
                <label class="tjobs-metabox-label" for="tjobs_info_contact_name">
                    <?php esc_html_e( 'Nimi', 'tapojarvijobs' ); ?>
                </label>
                <input type="text" id="tjobs_info_contact_name" name="tjobs_info_contact_name" value="<?php echo esc_attr( $contact_name ); ?>" class="tjobs-metabox-input" />
            </div>
            <div class="tjobs-metabox-field">
                <label class="tjobs-metabox-label" for="tjobs_info_contact_email">
                    <?php esc_html_e( 'Sähköposti', 'tapojarvijobs' ); ?>
                </label>
                <input type="email" id="tjobs_info_contact_email" name="tjobs_info_contact_email" value="<?php echo esc_attr( $contact_email ); ?>" class="tjobs-metabox-input" />
            </div>
            <div class="tjobs-metabox-field">
                <label class="tjobs-metabox-label" for="tjobs_info_contact_phone">
                    <?php esc_html_e( 'Puhelin', 'tapojarvijobs' ); ?>
                </label>
                <input type="text" id="tjobs_info_contact_phone" name="tjobs_info_contact_phone" value="<?php echo esc_attr( $contact_phone ); ?>" class="tjobs-metabox-input" />
            </div>
        </div>

    </div><!-- /tab automation -->

</div><!-- .tjobs-metabox-wrap -->

<script>
jQuery(document).ready(function($) {

    // ── Tab switching ────────────────────────────────────────────────────────
    $(document).on('click', '.tjobs-admin-tab-btn', function() {
        var tab = $(this).data('tab');
        $('.tjobs-admin-tab-btn').removeClass('is-active');
        $(this).addClass('is-active');
        $('.tjobs-admin-tab-content').removeClass('is-active');
        $('.tjobs-admin-tab-content[data-tab-content="' + tab + '"]').addClass('is-active');
    });

    // ── Highlights ───────────────────────────────────────────────────────────
    $('#tjobs-add-highlight').on('click', function() {
        var html = '<div class="tjobs-list-row">' +
            '<input type="text" name="tjobs_info_highlights[]" value="" class="tjobs-metabox-input" />' +
            '<button type="button" class="tjobs-metabox-btn tjobs-metabox-btn-remove tjobs-remove-highlight" aria-label="Poista"><span class="dashicons dashicons-trash"></span></button>' +
            '</div>';
        $('#tjobs-highlights-container').append(html);
        $('#tjobs-highlights-container .tjobs-list-row:last-child input').focus();
    });

    $(document).on('click', '.tjobs-remove-highlight', function() {
        $(this).closest('.tjobs-list-row').remove();
    });

    // ── Info Sections ─────────────────────────────────────────────────────────
    var sectionIndex = <?php echo count( $sections ); ?>;

    function tjobsBuildSectionRow(index, icon, title) {
        var escapedIcon  = $('<div>').text(icon).html();
        var escapedTitle = $('<div>').text(title).html();
        return '<div class="tjobs-section-row">' +
            '<div class="tjobs-section-header">' +
            '<strong><?php echo esc_js( __( 'Osio #', 'tapojarvijobs' ) ); ?>' + (index + 1) + '</strong>' +
            '<button type="button" class="tjobs-metabox-btn tjobs-metabox-btn-remove tjobs-remove-section" aria-label="<?php echo esc_js( __( 'Poista', 'tapojarvijobs' ) ); ?>"><span class="dashicons dashicons-trash"></span></button>' +
            '</div>' +
            '<div class="tjobs-metabox-grid tjobs-section-row__grid">' +
            '<div class="tjobs-metabox-field">' +
            '<label class="tjobs-metabox-label"><?php echo esc_js( __( 'Ikoni', 'tapojarvijobs' ) ); ?></label>' +
            '<input type="text" name="tjobs_info_sections[' + index + '][icon]" value="' + escapedIcon + '" class="tjobs-metabox-input" placeholder="🏠" />' +
            '</div>' +
            '<div class="tjobs-metabox-field">' +
            '<label class="tjobs-metabox-label"><?php echo esc_js( __( 'Otsikko', 'tapojarvijobs' ) ); ?></label>' +
            '<input type="text" name="tjobs_info_sections[' + index + '][title]" value="' + escapedTitle + '" class="tjobs-metabox-input" />' +
            '</div>' +
            '</div>' +
            '<div class="tjobs-metabox-field">' +
            '<label class="tjobs-metabox-label"><?php echo esc_js( __( 'Sisältö', 'tapojarvijobs' ) ); ?></label>' +
            '<textarea name="tjobs_info_sections[' + index + '][content]" rows="5" class="tjobs-metabox-textarea"></textarea>' +
            '</div>' +
            '</div>';
    }

    $('.tjobs-section-preset-btn').on('click', function() {
        var icon  = $(this).data('icon');
        var title = $(this).data('fi') || '';
        $('#tjobs-sections-container').append(tjobsBuildSectionRow(sectionIndex, icon, title));
        sectionIndex++;
    });

    $('#tjobs-add-section').on('click', function() {
        $('#tjobs-sections-container').append(tjobsBuildSectionRow(sectionIndex, '', ''));
        sectionIndex++;
    });

    $(document).on('click', '.tjobs-remove-section', function() {
        $(this).closest('.tjobs-section-row').remove();
    });

    // ── Gallery ──────────────────────────────────────────────────────────────
    var mapMediaUploader;

    $('#tjobs-add-gallery-images').on('click', function(e) {
        e.preventDefault();

        if (mapMediaUploader) {
            mapMediaUploader.open();
            return;
        }

        mapMediaUploader = wp.media({
            title: '<?php echo esc_js( __( 'Valitse kuvat galleriaan', 'tapojarvijobs' ) ); ?>',
            button: { text: '<?php echo esc_js( __( 'Lisää galleriaan', 'tapojarvijobs' ) ); ?>' },
            multiple: true
        });

        mapMediaUploader.on('select', function() {
            var attachments = mapMediaUploader.state().get('selection').toJSON();
            attachments.forEach(function(attachment) {
                var thumbUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                var html = '<div class="tjobs-gallery-item" data-id="' + attachment.id + '">' +
                    '<img src="' + thumbUrl + '" alt="" />' +
                    '<button type="button" class="tjobs-gallery-remove tjobs-remove-gallery-item" aria-label="Poista">&times;</button>' +
                    '<input type="hidden" name="tjobs_info_gallery[]" value="' + attachment.id + '" />' +
                    '</div>';
                $('#tjobs-gallery-container').append(html);
            });
        });

        mapMediaUploader.open();
    });

    $(document).on('click', '.tjobs-remove-gallery-item', function() {
        $(this).closest('.tjobs-gallery-item').remove();
    });

    // ── Questions ────────────────────────────────────────────────────────────
    var questionIndex = <?php echo count( $questions ); ?>;

    $('#tjobs-add-question').on('click', function() {
        $.post(ajaxurl, {
            action: 'tjobs_render_question_row',
            index: questionIndex
        }, function(response) {
            $('#tjobs-questions-container').append(response);
            questionIndex++;
        });
    });

    $(document).on('click', '.tjobs-remove-question', function() {
        $(this).closest('.tjobs-question-row').remove();
    });

    $(document).on('change', '.tjobs-question-type', function() {
        var $row = $(this).closest('.tjobs-question-row');
        var type = $(this).val();

        if (type === 'select') {
            $row.find('.tjobs-question-options-wrapper').show();
        } else {
            $row.find('.tjobs-question-options-wrapper').hide();
        }

        if (type === 'yesno') {
            var currentVal = $row.find('.tjobs-unsuitable-value-field').val();
            $row.find('.tjobs-unsuitable-text-input').hide();
            $row.find('.tjobs-unsuitable-yesno-picker').show();
            var $radios = $row.find('.tjobs-unsuitable-yesno-radio');
            $radios.prop('checked', false);
            $row.find('.tjobs-yesno-option').removeClass('is-selected');
            if (currentVal === 'yes' || currentVal === 'no') {
                $radios.filter('[value="' + currentVal + '"]').prop('checked', true)
                    .closest('.tjobs-yesno-option').addClass('is-selected');
            } else {
                $radios.filter('[value=""]').prop('checked', true)
                    .closest('.tjobs-yesno-option').addClass('is-selected');
                $row.find('.tjobs-unsuitable-value-field').val('');
            }
        } else {
            var hiddenVal = $row.find('.tjobs-unsuitable-value-field').val();
            $row.find('.tjobs-unsuitable-yesno-picker').hide();
            $row.find('.tjobs-unsuitable-text-input').show();
            $row.find('.tjobs-unsuitable-text-field').val(hiddenVal);
        }
    });

    $(document).on('input change', '.tjobs-unsuitable-text-field', function() {
        var $row = $(this).closest('.tjobs-question-row');
        $row.find('.tjobs-unsuitable-value-field').val($(this).val());
    });

    $(document).on('change', '.tjobs-unsuitable-yesno-radio', function() {
        var $row = $(this).closest('.tjobs-question-row');
        $row.find('.tjobs-unsuitable-value-field').val($(this).val());
        $row.find('.tjobs-yesno-option').removeClass('is-selected');
        $(this).closest('.tjobs-yesno-option').addClass('is-selected');
    });

    // ── Score feedback rules ─────────────────────────────────────────────────
    var scoreRuleIndex = <?php echo count( $score_feedback_rules ); ?>;

    $('#tjobs-add-score-rule').on('click', function() {
        var html = '<div class="tjobs-score-rule-row tjobs-list-row">' +
            '<span class="tjobs-score-rule-label"><?php echo esc_js( __( 'Jos virheitä >=', 'tapojarvijobs' ) ); ?></span>' +
            '<input type="number" name="tjobs_score_feedback_rules[' + scoreRuleIndex + '][min_errors]" value="1" class="tjobs-metabox-input tjobs-score-rule-number" min="0" />' +
            '<textarea name="tjobs_score_feedback_rules[' + scoreRuleIndex + '][message]" rows="2" class="tjobs-metabox-textarea" placeholder="<?php echo esc_js( __( 'Palauteviesti...', 'tapojarvijobs' ) ); ?>"></textarea>' +
            '<button type="button" class="tjobs-metabox-btn tjobs-metabox-btn-remove tjobs-remove-score-rule" aria-label="<?php echo esc_js( __( 'Poista', 'tapojarvijobs' ) ); ?>"><span class="dashicons dashicons-trash"></span></button>' +
            '</div>';
        $('#tjobs-score-rules-container').append(html);
        scoreRuleIndex++;
    });

    $(document).on('click', '.tjobs-remove-score-rule', function() {
        $(this).closest('.tjobs-score-rule-row').remove();
    });

});
</script>
<?php
}

/**
 * Renderöi yksittäinen kysymysrivi
 */
function tjobs_render_question_row( $index, $data = array() ) {
$question = isset( $data['question'] ) ? $data['question'] : '';
$type     = isset( $data['type'] ) ? $data['type'] : 'text';
$options  = isset( $data['options'] ) ? $data['options'] : '';
$required = isset( $data['required'] ) ? $data['required'] : false;
$unsuitable_value    = isset( $data['unsuitable_value'] ) ? $data['unsuitable_value'] : '';
$unsuitable_feedback = isset( $data['unsuitable_feedback'] ) ? $data['unsuitable_feedback'] : '';

$show_options = ( $type === 'select' );
?>
<div class="tjobs-question-row">
    <div class="tjobs-question-header">
        <strong><?php echo esc_html( sprintf( __( 'Kysymys #%d', 'tapojarvijobs' ), $index + 1 ) ); ?></strong>
        <button type="button" class="tjobs-metabox-btn tjobs-metabox-btn-remove tjobs-remove-question" aria-label="<?php esc_attr_e( 'Poista', 'tapojarvijobs' ); ?>">
            <span class="dashicons dashicons-trash"></span>
        </button>
    </div>

    <div class="tjobs-metabox-field">
        <label class="tjobs-metabox-label"><?php esc_html_e( 'Kysymysteksti', 'tapojarvijobs' ); ?></label>
        <input type="text" name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][question]" value="<?php echo esc_attr( $question ); ?>" class="tjobs-metabox-input" />
    </div>

    <div class="tjobs-metabox-field">
        <label class="tjobs-metabox-label"><?php esc_html_e( 'Tyyppi', 'tapojarvijobs' ); ?></label>
        <select name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][type]" class="tjobs-metabox-select tjobs-question-type">
            <option value="text"  <?php selected( $type, 'text' ); ?>>Text (textarea)</option>
            <option value="yesno" <?php selected( $type, 'yesno' ); ?>>Yes/No</option>
            <option value="scale" <?php selected( $type, 'scale' ); ?>>Scale (1-5)</option>
            <option value="select" <?php selected( $type, 'select' ); ?>>Select (dropdown)</option>
            <option value="info"  <?php selected( $type, 'info' ); ?>>Info (vain teksti)</option>
        </select>
    </div>

    <div class="tjobs-metabox-field tjobs-question-options-wrapper" <?php echo $show_options ? '' : 'style="display:none;"'; ?>>
        <label class="tjobs-metabox-label">
            <?php esc_html_e( 'Vaihtoehdot', 'tapojarvijobs' ); ?>
            <span class="tjobs-metabox-desc"><?php esc_html_e( 'Yksi per rivi, vain select-tyypille', 'tapojarvijobs' ); ?></span>
        </label>
        <textarea name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][options]" rows="3" class="tjobs-metabox-textarea"><?php echo esc_textarea( $options ); ?></textarea>
    </div>

    <div class="tjobs-metabox-field">
        <label class="tjobs-toggle-label">
            <span class="tjobs-toggle">
                <input type="checkbox" name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][required]" value="1" <?php checked( $required, true ); ?> />
                <span class="tjobs-toggle-slider"></span>
            </span>
            <?php esc_html_e( 'Pakollinen', 'tapojarvijobs' ); ?>
        </label>
    </div>

    <div class="tjobs-question-unsuitable">
        <div class="tjobs-metabox-section-title">
            <?php esc_html_e( 'Epäsopivuuspalaute (valinnainen)', 'tapojarvijobs' ); ?>
            <span class="tjobs-metabox-desc"><?php esc_html_e( 'Näytetään, jos vastaaja valitsee epäsopivan arvon', 'tapojarvijobs' ); ?></span>
        </div>
        <div class="tjobs-metabox-grid">
            <div class="tjobs-metabox-field">
                <label class="tjobs-metabox-label">
                    <?php esc_html_e( 'Epäsopiva arvo', 'tapojarvijobs' ); ?>
                </label>

                <?php /* Single hidden input that always carries the submitted value */ ?>
                <input type="hidden" name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][unsuitable_value]" class="tjobs-unsuitable-value-field" value="<?php echo esc_attr( $unsuitable_value ); ?>" />

                <?php /* Text input overlay: shown for non-yesno question types */ ?>
                <div class="tjobs-unsuitable-text-input" <?php echo ( $type === 'yesno' ) ? 'style="display:none;"' : ''; ?>>
                    <input type="text" class="tjobs-metabox-input tjobs-unsuitable-text-field" value="<?php echo ( $type !== 'yesno' ) ? esc_attr( $unsuitable_value ) : ''; ?>" placeholder="<?php esc_attr_e( 'Esim: no, 1, 2 (pilkulla erotettuna)', 'tapojarvijobs' ); ?>" />
                    <span class="tjobs-metabox-desc"><?php esc_html_e( 'scale: 1,2 | select: vaihtoehdon teksti', 'tapojarvijobs' ); ?></span>
                </div>

                <?php /* Radio picker: shown only for yesno question types */ ?>
                <div class="tjobs-unsuitable-yesno-picker" <?php echo ( $type !== 'yesno' ) ? 'style="display:none;"' : ''; ?>>
                    <p class="tjobs-metabox-desc" style="margin-bottom:8px;"><?php esc_html_e( 'Kumpi vastaus on epäsopiva?', 'tapojarvijobs' ); ?></p>
                    <div class="tjobs-unsuitable-yesno-options">
                        <label class="tjobs-yesno-option <?php echo ( $type === 'yesno' && $unsuitable_value === 'yes' ) ? 'is-selected' : ''; ?>">
                            <input type="radio" class="tjobs-unsuitable-yesno-radio" value="yes"
                                   <?php checked( $type === 'yesno' && $unsuitable_value === 'yes' ); ?> />
                            <span><?php esc_html_e( 'Kyllä (Yes)', 'tapojarvijobs' ); ?></span>
                        </label>
                        <label class="tjobs-yesno-option <?php echo ( $type === 'yesno' && $unsuitable_value === 'no' ) ? 'is-selected' : ''; ?>">
                            <input type="radio" class="tjobs-unsuitable-yesno-radio" value="no"
                                   <?php checked( $type === 'yesno' && $unsuitable_value === 'no' ); ?> />
                            <span><?php esc_html_e( 'Ei (No)', 'tapojarvijobs' ); ?></span>
                        </label>
                        <label class="tjobs-yesno-option <?php echo ( $type === 'yesno' && $unsuitable_value !== 'yes' && $unsuitable_value !== 'no' ) ? 'is-selected' : ''; ?>">
                            <input type="radio" class="tjobs-unsuitable-yesno-radio" value=""
                                   <?php checked( $type === 'yesno' && $unsuitable_value !== 'yes' && $unsuitable_value !== 'no' ); ?> />
                            <span><?php esc_html_e( 'Kumpikaan', 'tapojarvijobs' ); ?></span>
                        </label>
                    </div>
                </div>

                <?php if ( ! empty( $unsuitable_value ) ) : ?>
                <span class="tjobs-unsuitable-hint">
                    💡 <?php echo esc_html( sprintf( __( 'Palaute aktivoituu arvo(i)lla: %s', 'tapojarvijobs' ), $unsuitable_value ) ); ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="tjobs-metabox-field">
                <label class="tjobs-metabox-label"><?php esc_html_e( 'Palauteviesti', 'tapojarvijobs' ); ?></label>
                <textarea name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][unsuitable_feedback]" rows="2" class="tjobs-metabox-textarea" placeholder="<?php esc_attr_e( 'Esim: Tämä tehtävä saattaa vaatia tätä ominaisuutta. Voit silti jatkaa hakemista!', 'tapojarvijobs' ); ?>"><?php echo esc_textarea( $unsuitable_feedback ); ?></textarea>
            </div>
        </div>
    </div>
</div>
<?php
}

/**
 * AJAX handler kysymysrivin renderöintiin
 */
function tjobs_ajax_render_question_row() {
    $index = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : 0;
    tjobs_render_question_row( $index );
    wp_die();
}
add_action( 'wp_ajax_tjobs_render_question_row', 'tjobs_ajax_render_question_row' );

/**
 * Tallenna infopaketin meta datat
 */
function tjobs_save_infopackage_meta( $post_id ) {
// Tarkista nonce
if ( ! isset( $_POST['tjobs_infopackage_nonce'] ) || ! wp_verify_nonce( $_POST['tjobs_infopackage_nonce'], 'tjobs_save_infopackage' ) ) {
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

// Tallenna intro
if ( isset( $_POST['tjobs_info_intro'] ) ) {
    update_post_meta( $post_id, '_tjobs_info_intro', sanitize_textarea_field( $_POST['tjobs_info_intro'] ) );
}

// Tallenna highlights
if ( isset( $_POST['tjobs_info_highlights'] ) && is_array( $_POST['tjobs_info_highlights'] ) ) {
    $highlights = array_map( 'sanitize_text_field', $_POST['tjobs_info_highlights'] );
    $highlights = array_filter( $highlights ); // Poista tyhjät
    update_post_meta( $post_id, '_tjobs_info_highlights', $highlights );
} else {
    delete_post_meta( $post_id, '_tjobs_info_highlights' );
}

// Tallenna yhteyshenkilö
if ( isset( $_POST['tjobs_info_contact_name'] ) ) {
    update_post_meta( $post_id, '_tjobs_info_contact_name', sanitize_text_field( $_POST['tjobs_info_contact_name'] ) );
}
if ( isset( $_POST['tjobs_info_contact_email'] ) ) {
    update_post_meta( $post_id, '_tjobs_info_contact_email', sanitize_email( $_POST['tjobs_info_contact_email'] ) );
}
if ( isset( $_POST['tjobs_info_contact_phone'] ) ) {
    update_post_meta( $post_id, '_tjobs_info_contact_phone', sanitize_text_field( $_POST['tjobs_info_contact_phone'] ) );
}

// Tallenna video URL
if ( isset( $_POST['tjobs_info_video_url'] ) ) {
    update_post_meta( $post_id, '_tjobs_info_video_url', esc_url_raw( $_POST['tjobs_info_video_url'] ) );
}

// Tallenna galleria
if ( isset( $_POST['tjobs_info_gallery'] ) && is_array( $_POST['tjobs_info_gallery'] ) ) {
    $gallery = array_map( 'absint', $_POST['tjobs_info_gallery'] );
    $gallery = array_filter( $gallery ); // Poista tyhjät
    update_post_meta( $post_id, '_tjobs_info_gallery', $gallery );
} else {
    delete_post_meta( $post_id, '_tjobs_info_gallery' );
}

// Tallenna kysymykset
if ( isset( $_POST['tjobs_info_questions'] ) && is_array( $_POST['tjobs_info_questions'] ) ) {
    $questions = array();
    foreach ( $_POST['tjobs_info_questions'] as $q ) {
        if ( empty( $q['question'] ) ) {
            continue;
        }
        $questions[] = array(
            'question' => sanitize_text_field( $q['question'] ),
            'type'     => isset( $q['type'] ) ? sanitize_text_field( $q['type'] ) : 'text',
            'options'  => isset( $q['options'] ) ? sanitize_textarea_field( $q['options'] ) : '',
            'required' => isset( $q['required'] ) && $q['required'] === '1',
            'unsuitable_value'     => isset( $q['unsuitable_value'] ) ? sanitize_text_field( $q['unsuitable_value'] ) : '',
            'unsuitable_feedback'  => isset( $q['unsuitable_feedback'] ) ? sanitize_textarea_field( $q['unsuitable_feedback'] ) : '',
        );
    }
    update_post_meta( $post_id, '_tjobs_info_questions', $questions );
} else {
    delete_post_meta( $post_id, '_tjobs_info_questions' );
}

// Tallenna tietosisältöosiot
if ( isset( $_POST['tjobs_info_sections'] ) && is_array( $_POST['tjobs_info_sections'] ) ) {
    $sections = array();
    foreach ( $_POST['tjobs_info_sections'] as $s ) {
        if ( empty( $s['title'] ) && empty( $s['content'] ) ) {
            continue; // Ohita tyhjät osiot
        }
        $sections[] = array(
            'icon'    => isset( $s['icon'] ) ? sanitize_text_field( $s['icon'] ) : '',
            'title'   => isset( $s['title'] ) ? sanitize_text_field( $s['title'] ) : '',
            'content' => isset( $s['content'] ) ? sanitize_textarea_field( $s['content'] ) : '',
        );
    }
    update_post_meta( $post_id, '_tjobs_info_sections', $sections );
} else {
    delete_post_meta( $post_id, '_tjobs_info_sections' );
}

// Tallenna automaatiosäännöt
if ( isset( $_POST['tjobs_info_auto_location'] ) ) {
    update_post_meta( $post_id, '_tjobs_info_auto_location', sanitize_text_field( $_POST['tjobs_info_auto_location'] ) );
}
if ( isset( $_POST['tjobs_info_auto_keywords'] ) ) {
    update_post_meta( $post_id, '_tjobs_info_auto_keywords', sanitize_text_field( $_POST['tjobs_info_auto_keywords'] ) );
}

// Tallenna score-based feedback rules
if ( isset( $_POST['tjobs_score_feedback_rules'] ) && is_array( $_POST['tjobs_score_feedback_rules'] ) ) {
    $score_rules = array();
    foreach ( $_POST['tjobs_score_feedback_rules'] as $rule ) {
        if ( ! is_array( $rule ) || ! isset( $rule['message'] ) || '' === trim( $rule['message'] ) ) {
            continue;
        }
        $score_rules[] = array(
            'min_errors' => isset( $rule['min_errors'] ) ? absint( $rule['min_errors'] ) : 0,
            'message'    => sanitize_textarea_field( $rule['message'] ),
        );
    }
    update_post_meta( $post_id, '_tjobs_score_feedback_rules', $score_rules );
} else {
    delete_post_meta( $post_id, '_tjobs_score_feedback_rules' );
}

// Päivitä HTML-välimuistin cache bump
update_option( 'tjobs_cache_bump', time() );
}
add_action( 'save_post_tjobs_infopackage', 'tjobs_save_infopackage_meta' );

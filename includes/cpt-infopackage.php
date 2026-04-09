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
    // Infopaketin sisältö
    add_meta_box(
        'tjobs_infopackage_content',
        __( 'Infopaketin sisältö', 'tapojarvijobs' ),
        'tjobs_render_infopackage_content_meta_box',
        'tjobs_infopackage',
        'normal',
        'high'
    );

    // Media (Video ja Kuvagalleria)
    add_meta_box(
        'tjobs_infopackage_media',
        __( 'Media (Video ja Kuvagalleria)', 'tapojarvijobs' ),
        'tjobs_render_infopackage_media_meta_box',
        'tjobs_infopackage',
        'normal',
        'high'
    );

    // Kysymyspatteristo
    add_meta_box(
        'tjobs_infopackage_questions',
        __( 'Kysymyspatteristo', 'tapojarvijobs' ),
        'tjobs_render_infopackage_questions_meta_box',
        'tjobs_infopackage',
        'normal',
        'high'
    );

    // Automaattinen liitos
    add_meta_box(
        'tjobs_infopackage_automation',
        __( 'Automaattinen liitos', 'tapojarvijobs' ),
        'tjobs_render_infopackage_automation_meta_box',
        'tjobs_infopackage',
        'side',
        'default'
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
 * Renderöi sisältö meta box
 */
function tjobs_render_infopackage_content_meta_box( $post ) {
wp_nonce_field( 'tjobs_save_infopackage', 'tjobs_infopackage_nonce' );

$intro         = get_post_meta( $post->ID, '_tjobs_info_intro', true );
$highlights    = get_post_meta( $post->ID, '_tjobs_info_highlights', true );
$contact_name  = get_post_meta( $post->ID, '_tjobs_info_contact_name', true );
$contact_email = get_post_meta( $post->ID, '_tjobs_info_contact_email', true );
$contact_phone = get_post_meta( $post->ID, '_tjobs_info_contact_phone', true );

if ( ! is_array( $highlights ) ) {
    $highlights = array();
}

?>
<div class="tjobs-metabox-wrap">
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
            <?php
            if ( ! empty( $highlights ) ) {
                foreach ( $highlights as $index => $highlight ) {
                    ?>
                    <div class="tjobs-list-row">
                        <input type="text" name="tjobs_info_highlights[]" value="<?php echo esc_attr( $highlight ); ?>" class="tjobs-metabox-input" />
                        <button type="button" class="tjobs-metabox-btn tjobs-metabox-btn-remove tjobs-remove-highlight" aria-label="<?php esc_attr_e( 'Poista', 'tapojarvijobs' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <button type="button" id="tjobs-add-highlight" class="tjobs-metabox-btn tjobs-metabox-btn-add">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'Lisää highlight', 'tapojarvijobs' ); ?>
        </button>
    </div>

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
</div>

<script>
jQuery(document).ready(function($) {
    // Add highlight
    $('#tjobs-add-highlight').on('click', function() {
        var html = '<div class="tjobs-list-row">' +
            '<input type="text" name="tjobs_info_highlights[]" value="" class="tjobs-metabox-input" />' +
            '<button type="button" class="tjobs-metabox-btn tjobs-metabox-btn-remove tjobs-remove-highlight" aria-label="Poista"><span class="dashicons dashicons-trash"></span></button>' +
            '</div>';
        $('#tjobs-highlights-container').append(html);
        $('#tjobs-highlights-container .tjobs-list-row:last-child input').focus();
    });

    // Remove highlight
    $(document).on('click', '.tjobs-remove-highlight', function() {
        $(this).closest('.tjobs-list-row').remove();
    });
});
</script>
<?php
}

/**
 * Renderöi media (video ja galleria) meta box
 */
function tjobs_render_infopackage_media_meta_box( $post ) {
$video_url = get_post_meta( $post->ID, '_tjobs_info_video_url', true );
$gallery   = get_post_meta( $post->ID, '_tjobs_info_gallery', true );

if ( ! is_array( $gallery ) ) {
    $gallery = array();
}

?>
<div class="tjobs-metabox-wrap">
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
</div>

<script>
jQuery(document).ready(function($) {
    // WordPress Media Uploader for gallery
    var mapMediaUploader;
    
    $('#tjobs-add-gallery-images').on('click', function(e) {
        e.preventDefault();
        
        if (mapMediaUploader) {
            mapMediaUploader.open();
            return;
        }
        
        mapMediaUploader = wp.media({
            title: '<?php echo esc_js( __( 'Valitse kuvat galleriaan', 'tapojarvijobs' ) ); ?>',
            button: {
                text: '<?php echo esc_js( __( 'Lisää galleriaan', 'tapojarvijobs' ) ); ?>'
            },
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
    
    // Remove gallery item
    $(document).on('click', '.tjobs-remove-gallery-item', function() {
        $(this).closest('.tjobs-gallery-item').remove();
    });
});
</script>
<?php
}

/**
 * Renderöi kysymyspatteristo meta box
 */
function tjobs_render_infopackage_questions_meta_box( $post ) {
$questions = get_post_meta( $post->ID, '_tjobs_info_questions', true );
if ( ! is_array( $questions ) ) {
    $questions = array();
}

?>
<div class="tjobs-metabox-wrap">
    <div id="tjobs-questions-container">
        <?php
        if ( ! empty( $questions ) ) {
            foreach ( $questions as $index => $q ) {
                tjobs_render_question_row( $index, $q );
            }
        }
        ?>
    </div>
    <button type="button" id="tjobs-add-question" class="tjobs-metabox-btn tjobs-metabox-btn-add">
        <span class="dashicons dashicons-plus-alt2"></span>
        <?php esc_html_e( 'Lisää kysymys', 'tapojarvijobs' ); ?>
    </button>
</div>

<script>
jQuery(document).ready(function($) {
    var questionIndex = <?php echo count( $questions ); ?>;

    // Add question
    $('#tjobs-add-question').on('click', function() {
        $.post(ajaxurl, {
            action: 'tjobs_render_question_row',
            index: questionIndex
        }, function(response) {
            $('#tjobs-questions-container').append(response);
            questionIndex++;
        });
    });

    // Remove question
    $(document).on('click', '.tjobs-remove-question', function() {
        $(this).closest('.tjobs-question-row').remove();
    });

    // Toggle options field visibility
    $(document).on('change', '.tjobs-question-type', function() {
        var $row = $(this).closest('.tjobs-question-row');
        var type = $(this).val();
        if (type === 'select') {
            $row.find('.tjobs-question-options-wrapper').show();
        } else {
            $row.find('.tjobs-question-options-wrapper').hide();
        }
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
                    <span class="tjobs-metabox-desc"><?php esc_html_e( 'yes/no: no | scale: 1,2 | select: vaihtoehdon teksti', 'tapojarvijobs' ); ?></span>
                </label>
                <input type="text" name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][unsuitable_value]" value="<?php echo esc_attr( $unsuitable_value ); ?>" class="tjobs-metabox-input" placeholder="<?php esc_attr_e( 'Esim: no, 1, 2 (pilkulla erotettuna)', 'tapojarvijobs' ); ?>" />
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
 * Renderöi automaattinen liitos meta box
 */
function tjobs_render_infopackage_automation_meta_box( $post ) {
    $auto_location = get_post_meta( $post->ID, '_tjobs_info_auto_location', true );
    $auto_keywords = get_post_meta( $post->ID, '_tjobs_info_auto_keywords', true );

    ?>
    <div class="tjobs-metabox-wrap">
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
    </div>
    <?php
}

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

// Tallenna automaatiosäännöt
if ( isset( $_POST['tjobs_info_auto_location'] ) ) {
    update_post_meta( $post_id, '_tjobs_info_auto_location', sanitize_text_field( $_POST['tjobs_info_auto_location'] ) );
}
if ( isset( $_POST['tjobs_info_auto_keywords'] ) ) {
    update_post_meta( $post_id, '_tjobs_info_auto_keywords', sanitize_text_field( $_POST['tjobs_info_auto_keywords'] ) );
}

// Päivitä HTML-välimuistin cache bump
update_option( 'tjobs_cache_bump', time() );
}
add_action( 'save_post_tjobs_infopackage', 'tjobs_save_infopackage_meta' );

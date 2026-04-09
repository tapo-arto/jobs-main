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
        'show_in_menu'        => 'tjobs-settings',
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

echo '<div style="padding:10px; background:#f0f0f0; border-radius:4px; text-align:center; font-weight:bold;">';
echo esc_html( tjobs_i18n( 'admin.language_version' ) ) . ': <span style="color:#2271b1;">' . esc_html( $current_lang ) . '</span>';
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
<p>
    <label for="tjobs_info_intro"><strong><?php _e( 'Esittelyteksti', 'tapojarvijobs' ); ?></strong></label><br>
    <textarea id="tjobs_info_intro" name="tjobs_info_intro" rows="5" style="width:100%;"><?php echo esc_textarea( $intro ); ?></textarea>
</p>

<div>
    <label><strong><?php _e( 'Highlights / Nostot', 'tapojarvijobs' ); ?></strong></label>
    <div id="tjobs-highlights-container">
        <?php
        if ( ! empty( $highlights ) ) {
            foreach ( $highlights as $index => $highlight ) {
                ?>
                <div class="tjobs-highlight-row" style="margin-bottom:8px; display:flex; align-items:center;">
                    <input type="text" name="tjobs_info_highlights[]" value="<?php echo esc_attr( $highlight ); ?>" style="flex:1; margin-right:8px;" />
                    <button type="button" class="button tjobs-remove-highlight">Poista</button>
                </div>
                <?php
            }
        }
        ?>
    </div>
    <button type="button" id="tjobs-add-highlight" class="button">+ Lisää highlight</button>
</div>

<hr style="margin:20px 0;" />

<h4><?php _e( 'Yhteyshenkilö', 'tapojarvijobs' ); ?></h4>
<p>
    <label><?php _e( 'Nimi', 'tapojarvijobs' ); ?></label><br>
    <input type="text" name="tjobs_info_contact_name" value="<?php echo esc_attr( $contact_name ); ?>" style="width:100%;" />
</p>
<p>
    <label><?php _e( 'Sähköposti', 'tapojarvijobs' ); ?></label><br>
    <input type="email" name="tjobs_info_contact_email" value="<?php echo esc_attr( $contact_email ); ?>" style="width:100%;" />
</p>
<p>
    <label><?php _e( 'Puhelin', 'tapojarvijobs' ); ?></label><br>
    <input type="text" name="tjobs_info_contact_phone" value="<?php echo esc_attr( $contact_phone ); ?>" style="width:100%;" />
</p>

<script>
jQuery(document).ready(function($) {
    // Add highlight
    $('#tjobs-add-highlight').on('click', function() {
        var html = '<div class="tjobs-highlight-row" style="margin-bottom:8px; display:flex; align-items:center;">' +
            '<input type="text" name="tjobs_info_highlights[]" value="" style="flex:1; margin-right:8px;" />' +
            '<button type="button" class="button tjobs-remove-highlight">Poista</button>' +
            '</div>';
        $('#tjobs-highlights-container').append(html);
    });

    // Remove highlight
    $(document).on('click', '.tjobs-remove-highlight', function() {
        $(this).closest('.tjobs-highlight-row').remove();
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
<div style="margin-bottom:20px;">
    <h4><?php _e( 'Video', 'tapojarvijobs' ); ?></h4>
    <p>
        <label><?php _e( 'YouTube tai Vimeo URL', 'tapojarvijobs' ); ?></label><br>
        <input type="url" name="tjobs_info_video_url" id="tjobs_info_video_url" value="<?php echo esc_attr( $video_url ); ?>" style="width:100%;" placeholder="https://www.youtube.com/watch?v=xxxxx tai https://vimeo.com/xxxxx" />
    </p>
    <p class="description">
        <?php _e( 'Syötä YouTube tai Vimeo videon URL. URL parsitaan automaattisesti embed-muotoon.', 'tapojarvijobs' ); ?>
    </p>
</div>

<hr style="margin:20px 0;" />

<div>
    <h4><?php _e( 'Kuvagalleria', 'tapojarvijobs' ); ?></h4>
    <div id="tjobs-gallery-container" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
        <?php
        foreach ( $gallery as $attachment_id ) {
            $image_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
            if ( $image_url ) {
                ?>
                <div class="tjobs-gallery-item" data-id="<?php echo esc_attr( $attachment_id ); ?>" style="position:relative; width:100px; height:100px;">
                    <img src="<?php echo esc_url( $image_url ); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:4px;" />
                    <button type="button" class="tjobs-remove-gallery-item" style="position:absolute; top:2px; right:2px; width:24px; height:24px; padding:0; background:#ef4444; color:#fff; border:none; border-radius:50%; cursor:pointer; font-size:16px; line-height:1;">&times;</button>
                    <input type="hidden" name="tjobs_info_gallery[]" value="<?php echo esc_attr( $attachment_id ); ?>" />
                </div>
                <?php
            }
        }
        ?>
    </div>
    <button type="button" id="tjobs-add-gallery-images" class="button">+ Lisää kuvia</button>
    <p class="description">
        <?php _e( 'Kuvat näytetään modalissa galleria-näkymässä.', 'tapojarvijobs' ); ?>
    </p>
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
                var html = '<div class="tjobs-gallery-item" data-id="' + attachment.id + '" style="position:relative; width:100px; height:100px;">' +
                    '<img src="' + attachment.sizes.thumbnail.url + '" style="width:100%; height:100%; object-fit:cover; border-radius:4px;" />' +
                    '<button type="button" class="tjobs-remove-gallery-item" style="position:absolute; top:2px; right:2px; width:24px; height:24px; padding:0; background:#ef4444; color:#fff; border:none; border-radius:50%; cursor:pointer; font-size:16px; line-height:1;">&times;</button>' +
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
<div id="tjobs-questions-container">
    <?php
    if ( ! empty( $questions ) ) {
        foreach ( $questions as $index => $q ) {
            tjobs_render_question_row( $index, $q );
        }
    }
    ?>
</div>
<button type="button" id="tjobs-add-question" class="button">+ Lisää kysymys</button>

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
<div class="tjobs-question-row" style="border:1px solid #ddd; padding:15px; margin-bottom:15px; background:#f9f9f9; border-radius:4px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
        <strong>Kysymys #<?php echo esc_html( $index + 1 ); ?></strong>
        <button type="button" class="button tjobs-remove-question">Poista</button>
    </div>

    <p>
        <label>Kysymysteksti</label><br>
        <input type="text" name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][question]" value="<?php echo esc_attr( $question ); ?>" style="width:100%;" />
    </p>

    <p>
        <label>Tyyppi</label><br>
        <select name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][type]" class="tjobs-question-type">
            <option value="text" <?php selected( $type, 'text' ); ?>>Text (textarea)</option>
            <option value="yesno" <?php selected( $type, 'yesno' ); ?>>Yes/No</option>
            <option value="scale" <?php selected( $type, 'scale' ); ?>>Scale (1-5)</option>
            <option value="select" <?php selected( $type, 'select' ); ?>>Select (dropdown)</option>
            <option value="info" <?php selected( $type, 'info' ); ?>>Info (vain teksti)</option>
        </select>
    </p>

    <div class="tjobs-question-options-wrapper" style="<?php echo $show_options ? '' : 'display:none;'; ?>">
        <label>Vaihtoehdot (yksi per rivi, vain select-tyypille)</label><br>
        <textarea name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][options]" rows="3" style="width:100%;"><?php echo esc_textarea( $options ); ?></textarea>
    </div>

    <p>
        <label>
            <input type="checkbox" name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][required]" value="1" <?php checked( $required, true ); ?> />
            Pakollinen
        </label>
    </p>

    <hr style="margin:15px 0; border-top:1px dashed #ccc;" />
    <p>
        <label><strong>Epäsopivuuspalaute (valinnainen)</strong></label><br>
        <small style="color:#666;">Jos vastaaja valitsee "epäsopivan" arvon, näytetään kohtelias palaute.</small>
    </p>
    <p>
        <label>Epäsopiva arvo</label><br>
        <input type="text" name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][unsuitable_value]" value="<?php echo esc_attr( $unsuitable_value ); ?>" style="width:100%;" placeholder="Esim: no, 1, 2 (pilkulla erotettuna)" />
        <small style="color:#999;">Yes/No: <code>no</code> | Scale: <code>1,2</code> | Select: vaihtoehdon teksti</small>
    </p>
    <p>
        <label>Palauteviesti</label><br>
        <textarea name="tjobs_info_questions[<?php echo esc_attr( $index ); ?>][unsuitable_feedback]" rows="2" style="width:100%;" placeholder="Esim: Tämä tehtävä saattaa vaatia tätä ominaisuutta. Voit silti jatkaa hakemista!"><?php echo esc_textarea( $unsuitable_feedback ); ?></textarea>
    </p>
    <?php if ( ! empty( $unsuitable_value ) ) : ?>
    <p style="margin-top:5px;">
        <span style="background:#fef3c7; border:1px solid #f59e0b; border-radius:4px; padding:2px 8px; font-size:11px; color:#92400e;">
            💡 Palaute aktivoituu arvo(i)lla: <code><?php echo esc_html( $unsuitable_value ); ?></code>
        </span>
    </p>
    <?php endif; ?>
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
    <p>
        <label><?php _e( 'Sijainti sisältää (pilkulla erotetut)', 'tapojarvijobs' ); ?></label><br>
        <input type="text" name="tjobs_info_auto_location" value="<?php echo esc_attr( $auto_location ); ?>" style="width:100%;" placeholder="Helsinki, Espoo, Vantaa" />
    </p>
<p>
    <label><?php _e( 'Otsikko sisältää (pilkulla erotetut)', 'tapojarvijobs' ); ?></label><br>
    <input type="text" name="tjobs_info_auto_keywords" value="<?php echo esc_attr( $auto_keywords ); ?>" style="width:100%;" placeholder="kehittäjä, designer" />
</p>
<p class="description">
    <?php _e( 'Huom: Automaatiosäännöt ovat kieliriippumattomia ja pisteyttävät työpaikkoja automaattisesti.', 'tapojarvijobs' ); ?>
</p>
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

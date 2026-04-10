<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API -endpoint työpaikkojen hakuun.
 * Route: tjobs/v1/jobs (GET)
 */
function tjobs_register_rest_routes() {
register_rest_route( 'tjobs/v1', '/jobs', array(
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => 'tjobs_rest_get_jobs',
    'permission_callback' => '__return_true',
    'args'                => array(
        'page'     => array(
            'default'           => 1,
            'sanitize_callback' => 'absint',
        ),
        'per_page' => array(
            'default'           => 10,
            'sanitize_callback' => 'absint',
        ),
        'search'   => array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ),
        'lang'     => array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ),
        'country'  => array(
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
        ),
    ),
) );

// Uusi endpoint infopaketeille
register_rest_route( 'tjobs/v1', '/job-info/(?P<id>\d+)', array(
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => 'tjobs_rest_get_job_info',
    'permission_callback' => '__return_true',
    'args'                => array(
        'id'   => array(
            'validate_callback' => function( $param ) {
                return is_numeric( $param );
            },
            'sanitize_callback' => 'absint',
        ),
        'lang' => array(
            'required'          => false,
            'default'           => null,
            'sanitize_callback' => 'sanitize_text_field',
        ),
    ),
) );
}
add_action( 'rest_api_init', 'tjobs_register_rest_routes' );

/**
 * REST API -callback: palauttaa työpaikat JSON-muodossa.
 *
 * @param WP_REST_Request $request Pyyntö.
 * @return WP_REST_Response JSON-vastaus.
 */
function tjobs_rest_get_jobs( WP_REST_Request $request ) {
$page     = $request->get_param( 'page' );
$per_page = $request->get_param( 'per_page' );
$search   = $request->get_param( 'search' );
$lang     = $request->get_param( 'lang' );
$country  = $request->get_param( 'country' );

// Rajoitetaan per_page järkeväksi
$per_page = min( max( 1, $per_page ), 100 );
$page     = max( 1, $page );

$query_args = array(
    'post_type'      => 'tjobs_tyopaikat',
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    'paged'          => $page,
);

// Haku otsikon perusteella
if ( ! empty( $search ) ) {
    $query_args['s'] = $search;
}

// Kielisuodatus Polylangin kautta
if ( ! empty( $lang ) && function_exists( 'pll_get_post_language' ) ) {
    $query_args['lang'] = $lang;
}

// Maasuodatus
if ( ! empty( $country ) ) {
    $country_meta = array(
        'key'   => '_tjobs_country',
        'value' => $country,
    );
    if ( isset( $query_args['meta_query'] ) ) {
        $query_args['meta_query'][] = $country_meta;
    } else {
        $query_args['meta_query'] = array( $country_meta );
    }
}

$query = new WP_Query( $query_args );
$jobs  = array();

if ( $query->have_posts() ) {
    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id = get_the_ID();
        $jobs[]  = array(
            'id'               => $post_id,
            'title'            => get_the_title(),
            'excerpt'          => get_the_excerpt(),
            '_tjobs_rss_link' => get_post_meta( $post_id, '_tjobs_rss_link', true ),
            'date'             => get_the_date( 'c' ),
            'country'          => get_post_meta( $post_id, '_tjobs_country', true ),
            'city'             => get_post_meta( $post_id, '_tjobs_city', true ),
            '_tjobs_type'         => get_post_meta( $post_id, '_tjobs_type', true ),
            'worktime'         => get_post_meta( $post_id, '_tjobs_worktime', true ),
            'form_url'         => get_post_meta( $post_id, '_tjobs_form_url', true ),
        );
    }
    wp_reset_postdata();
}

return new WP_REST_Response( array(
    'jobs'        => $jobs,
    'total'       => (int) $query->found_posts,
    'total_pages' => (int) $query->max_num_pages,
), 200 );
}

/**
 * REST API -callback: palauttaa yksittäisen työpaikan ja siihen liittyvän infopaketin tiedot.
 *
 * @param WP_REST_Request $request Pyyntö.
 * @return WP_REST_Response|WP_Error JSON-vastaus tai virhe.
 */
function tjobs_rest_get_job_info( WP_REST_Request $request ) {
$post_id = absint( $request->get_param( 'id' ) );
$lang    = $request->get_param( 'lang' );

// Validoi kielikoodi
if ( $lang !== null && function_exists( 'tjobs_normalize_lang_code' ) ) {
    $lang = tjobs_normalize_lang_code( $lang );
} elseif ( $lang !== null ) {
    $lang = in_array( $lang, array( 'fi', 'en', 'sv', 'it' ), true ) ? $lang : 'fi';
}

// Tunnista kieli jos ei annettu
if ( empty( $lang ) ) {
    $lang = function_exists( 'tjobs_get_current_lang' ) ? tjobs_get_current_lang() : 'fi';
}

$post = get_post( $post_id );

if ( ! $post || $post->post_type !== 'tjobs_tyopaikat' || $post->post_status !== 'publish' ) {
    return new WP_Error(
        'job_not_found',
        __( 'Työpaikkaa ei löydy.', 'tapojarvijobs' ),
        array( 'status' => 404 )
    );
}

// Hakemisen URL
$form_url  = get_post_meta( $post_id, '_tjobs_form_url', true );
$rss_link  = get_post_meta( $post_id, '_tjobs_rss_link', true );
$apply_url = ! empty( $form_url ) ? $form_url : $rss_link;

// Hae infopaketti
$infopackage_data = null;
try {
    if ( function_exists( 'tjobs_resolve_infopackage' ) ) {
        $pkg_id = tjobs_resolve_infopackage( $post_id, $lang );

        if ( $pkg_id ) {
            $pkg_post = get_post( $pkg_id );

            if ( $pkg_post && $pkg_post->post_status === 'publish' ) {
                // Perustiedot
                $intro      = get_post_meta( $pkg_id, '_tjobs_info_intro', true );
                $highlights = get_post_meta( $pkg_id, '_tjobs_info_highlights', true );
                $video_url  = get_post_meta( $pkg_id, '_tjobs_info_video_url', true );
                $gallery    = get_post_meta( $pkg_id, '_tjobs_info_gallery', true );
                $questions  = get_post_meta( $pkg_id, '_tjobs_info_questions', true );

                // Yhteyshenkilö
                $contact_name  = get_post_meta( $pkg_id, '_tjobs_info_contact_name', true );
                $contact_email = get_post_meta( $pkg_id, '_tjobs_info_contact_email', true );
                $contact_phone = get_post_meta( $pkg_id, '_tjobs_info_contact_phone', true );

                // Highlights arrayksi
                $highlights_arr = array();
                if ( ! empty( $highlights ) ) {
                    if ( is_array( $highlights ) ) {
                        $highlights_arr = array_values( array_filter( array_map( 'sanitize_text_field', $highlights ) ) );
                    } elseif ( is_string( $highlights ) ) {
                        $decoded = json_decode( $highlights, true );
                        if ( is_array( $decoded ) ) {
                            $highlights_arr = array_values( array_filter( array_map( 'sanitize_text_field', $decoded ) ) );
                        }
                    }
                }

                // Galleria arrayksi: palauta {id, url, thumb} objekteja
                $gallery_arr = array();
                if ( ! empty( $gallery ) ) {
                    $gallery_ids = array();
                    if ( is_array( $gallery ) ) {
                        $gallery_ids = $gallery;
                    } elseif ( is_string( $gallery ) ) {
                        $decoded = json_decode( $gallery, true );
                        if ( is_array( $decoded ) ) {
                            $gallery_ids = $decoded;
                        }
                    }
                    foreach ( $gallery_ids as $attachment_id ) {
                        $attachment_id = absint( $attachment_id );
                        if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
                            continue;
                        }
                        $image_url = wp_get_attachment_image_url( $attachment_id, 'large' );
                        $thumb_url = wp_get_attachment_image_url( $attachment_id, 'medium' );
                        if ( $image_url ) {
                            $gallery_arr[] = array(
                                'id'    => $attachment_id,
                                'url'   => $image_url,
                                'thumb' => $thumb_url ? $thumb_url : $image_url,
                            );
                        }
                    }
                }

                // Kysymykset arrayksi
                $questions_arr = array();
                if ( ! empty( $questions ) ) {
                    if ( is_array( $questions ) ) {
                        $questions_arr = $questions;
                    } elseif ( is_string( $questions ) ) {
                        $decoded = json_decode( $questions, true );
                        if ( is_array( $decoded ) ) {
                            $questions_arr = $decoded;
                        }
                    }
                }

                // Sanitoi kysymykset
                $sanitized_questions = array();
                foreach ( $questions_arr as $q ) {
                    if ( ! is_array( $q ) ) {
                        continue;
                    }
                    $sanitized_questions[] = array(
                        'question'            => isset( $q['question'] ) ? sanitize_text_field( $q['question'] ) : '',
                        'type'                => isset( $q['type'] ) ? sanitize_key( $q['type'] ) : 'text',
                        'options'             => isset( $q['options'] ) ? sanitize_textarea_field( $q['options'] ) : '',
                        'required'            => ! empty( $q['required'] ),
                        'unsuitable_value'    => isset( $q['unsuitable_value'] ) ? sanitize_text_field( $q['unsuitable_value'] ) : '',
                        'unsuitable_feedback' => isset( $q['unsuitable_feedback'] ) ? sanitize_textarea_field( $q['unsuitable_feedback'] ) : '',
                    );
                }

                // Saatavilla olevat kieliversiot
                $available_langs   = function_exists( 'tjobs_get_available_languages' ) ? tjobs_get_available_languages() : array( 'fi', 'en', 'sv', 'it' );
                $lang_availability = array();
                foreach ( $available_langs as $l ) {
                    if ( function_exists( 'tjobs_get_translated_package_id' ) ) {
                        $translated_id           = tjobs_get_translated_package_id( $pkg_id, $l );
                        $lang_availability[ $l ] = $translated_id && get_post_status( $translated_id ) === 'publish';
                    } else {
                        $lang_availability[ $l ] = ( $l === $lang );
                    }
                }

                $infopackage_data = array(
                    'id'                  => $pkg_id,
                    'title'               => $pkg_post->post_title,
                    'intro'               => wp_kses_post( (string) $intro ),
                    'highlights'          => $highlights_arr,
                    'video_url'           => esc_url_raw( (string) $video_url ),
                    'gallery'             => $gallery_arr,
                    'questions'           => $sanitized_questions,
                    'contact'             => array(
                        'name'  => sanitize_text_field( (string) $contact_name ),
                        'email' => sanitize_email( (string) $contact_email ),
                        'phone' => sanitize_text_field( (string) $contact_phone ),
                    ),
                    'available_languages' => $lang_availability,
                );
            }
        }
    }
} catch ( Throwable $e ) {
    // Prevent any unexpected exception from returning a 500 error;
    // infopackage data is simply omitted from the response.
    error_log( 'TJobs REST API: infopackage resolution failed for post ' . $post_id . ': ' . $e->getMessage() );
    $infopackage_data = null;
}

// i18n-käännökset frontendille
$i18n = function_exists( 'tjobs_get_js_translations' ) ? tjobs_get_js_translations( $lang ) : array();

return new WP_REST_Response( array(
    'id'          => $post_id,
    'title'       => $post->post_title,
    'excerpt'     => get_the_excerpt( $post ),
    'description' => wp_kses_post( $post->post_content ),
    'apply_url'   => esc_url_raw( (string) $apply_url ),
    'lang'        => $lang,
    'infopackage' => $infopackage_data,
    'i18n'        => $i18n,
), 200 );
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Rekisteröi turvallisuusparannukset: AJAX-endpoint synkronoinnille ja nonce-lataus.
 */

/**
 * Lataa admin-skriptit: lisää synkronointinonce wp_localize_script:llä.
 * Ajetaan prioriteetilla 20, jotta pääkooditiedoston skriptin rekisteröinti (pri 10) tapahtuu ensin.
 */
function tjobs_security_admin_enqueue( $hook ) {
    if ( 'toplevel_page_tjobs-settings' !== $hook ) {
        return;
    }

    // Varmistetaan, että skripti on rekisteröity ennen lokalisointia
    if ( ! wp_script_is( 'tjobs-admin-js', 'registered' )
        && ! wp_script_is( 'tjobs-admin-js', 'enqueued' ) ) {
        return;
    }

    wp_localize_script(
        'tjobs-admin-js',
        'tjobsAjax',
        array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'tjobs_sync_nonce' ),
        )
    );
}
add_action( 'admin_enqueue_scripts', 'tjobs_security_admin_enqueue', 20 );

/**
 * AJAX-endpoint pakotettua synkronointia varten (turvallinen versio).
 * Tarkistaa noncen ja käyttäjän oikeudet ennen ajoa.
 */
function tjobs_force_sync() {
    // Tarkista nonce
    check_ajax_referer( 'tjobs_sync_nonce', 'nonce' );

    // Tarkista käyttäjäoikeudet
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Ei riittäviä oikeuksia.', 'tapojarvijobs' ) ), 403 );
        return;
    }

    // Ajetaan synkronointi
    $result = tjobs_sync_feed();

    wp_send_json_success( array(
        'added'   => count( $result['added'] ),
        'removed' => count( $result['removed'] ),
        'updated' => count( $result['updated'] ),
    ) );
}
add_action( 'wp_ajax_tjobs_force_sync', 'tjobs_force_sync' );

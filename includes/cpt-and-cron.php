<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Suora pääsy estetty
}

// === 1. CPT-rekisteröinti ===
function tjobs_register_cpt_jobs() {
    register_post_type( 'tjobs_tyopaikat', array(
        'labels' => array(
            'name'          => __('TJobs: Avoimet työpaikat', 'tapojarvijobs'),
            'singular_name' => __('TJobs: Avoin työpaikka', 'tapojarvijobs'),
            'menu_name'     => __('TJobs Työpaikat', 'tapojarvijobs'),
        ),
        'public'       => true,
        'has_archive'  => true,
        'supports'     => array('title', 'editor', 'excerpt', 'custom-fields'),
    ));
}
add_action( 'init', 'tjobs_register_cpt_jobs' );

// === 2. Cron-ajastukset ===

// Lisätään mukautettu cron interval (3 hours)
function tjobs_add_cron_intervals($schedules) {
    $schedules['3_hours'] = array(
        'interval' => 3 * HOUR_IN_SECONDS,
        'display'  => __('Kolmen tunnin välein')
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'tjobs_add_cron_intervals' );

// Cronin aktivointi
function tjobs_activate_cron() {
    // Asetetaan oletusajaksi '3_hours' (tai haluamasi)
    if ( ! wp_next_scheduled('tjobs_cron_hook') ) {
        wp_schedule_event(time(), '3_hours', 'tjobs_cron_hook');
    }
}

// Cronin deaktivointi
function tjobs_deactivate_cron() {
    wp_clear_scheduled_hook('tjobs_cron_hook');
}

// Funktio päivittää cronin aikataulun (kutsutaan asetusten tallennuksen yhteydessä)
function tjobs_update_cron_schedule($frequency = '3_hours') {
    // Poistetaan vanha ajo
    wp_clear_scheduled_hook('tjobs_cron_hook');

    // Asetetaan uusi
    wp_schedule_event(time(), $frequency, 'tjobs_cron_hook');
}

// Sidotaan cron-hook tjobs_sync_feed()-funktioon
add_action('tjobs_cron_hook', 'tjobs_sync_feed');

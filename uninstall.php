<?php
// Varmistetaan, että tämä tiedosto ajetaan vain poiston yhteydessä
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. Poistetaan kaikki tjobs_tyopaikat -tyypin postaukset
$post_ids = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
        'tjobs_tyopaikat'
    )
);

foreach ( $post_ids as $post_id ) {
    wp_delete_post( (int) $post_id, true ); // Pakota pysyvä poisto
}

// 2. Poistetaan lisäosan optionit
$options_to_delete = array(
    'tjobs_settings',
    'tjobs_import_log',
    'tjobs_last_sync',
    'tjobs_last_sync_stats',
    'tjobs_cache_bump',
    'tjobs_open_application_url',
    'tjobs_open_application_urls',
);

foreach ( $options_to_delete as $option ) {
    delete_option( $option );
}

// 3. Poistetaan cron-tapahtumat
wp_clear_scheduled_hook( 'tjobs_cron_sync_event' );
wp_clear_scheduled_hook( 'tjobs_cron_hook' );

// 4. Poistetaan transientit (pattern: map_jobs_html_*)
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_tjobs_html_%'
        OR option_name LIKE '_transient_timeout_tjobs_html_%'"
);

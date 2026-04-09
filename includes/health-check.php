<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Site Health -integraatio: rekisteröi testi lisäosan tilasta.
 */
function tjobs_add_site_health_tests( $tests ) {
    $tests['direct']['tjobs_status'] = array(
        'label' => __( 'My Aggregator Plugin -tila', 'tapojarvijobs' ),
        'test'  => 'tjobs_site_health_test_aggregator',
    );
    return $tests;
}
add_filter( 'site_status_tests', 'tjobs_add_site_health_tests' );

/**
 * Suorittaa Site Health -testin aggregaattorin tilalle.
 *
 * @return array Testitulos.
 */
function tjobs_site_health_test_aggregator() {
$result = array(
    'label'       => __( 'My Aggregator Plugin toimii oikein', 'tapojarvijobs' ),
    'status'      => 'good',
    'badge'       => array(
        'label' => __( 'Aggregaattori', 'tapojarvijobs' ),
        'color' => 'blue',
    ),
    'description' => '',
    'actions'     => '',
    'test'        => 'tjobs_status',
);

$opts     = function_exists( 'tjobs_get_settings' ) ? tjobs_get_settings() : array();
$feed_url = isset( $opts['feed_url'] ) ? $opts['feed_url'] : '';

// Tarkistus 1: onko feed URL asetettu
if ( empty( $feed_url ) ) {
    $result['status']      = 'recommended';
    $result['label']       = __( 'RSS-syötteen URL puuttuu', 'tapojarvijobs' );
    $result['description'] = '<p>' . __( 'My Aggregator Plugin -lisäosalle ei ole asetettu RSS-syötteen URL:ia. Aseta se lisäosan asetuksista.', 'tapojarvijobs' ) . '</p>';
    $result['actions']     = '<a href="' . esc_url( admin_url( 'admin.php?page=tjobs-settings' ) ) . '">' . __( 'Siirry asetuksiin', 'tapojarvijobs' ) . '</a>';
    return $result;
}

// Tarkistus 2: milloin viimeisin synkronointi
$last_sync = get_option( 'tjobs_last_sync', 0 );
if ( empty( $last_sync ) ) {
    $result['status']      = 'recommended';
    $result['label']       = __( 'Synkronointia ei ole vielä suoritettu', 'tapojarvijobs' );
    $result['description'] = '<p>' . __( 'RSS-syötteen synkronointia ei ole vielä suoritettu. Aja synkronointi manuaalisesti tai odota, että WP-Cron ajaa sen automaattisesti.', 'tapojarvijobs' ) . '</p>';
    return $result;
}

$hours_since = ( time() - (int) $last_sync ) / HOUR_IN_SECONDS;
if ( $hours_since > 24 ) {
    $result['status']      = 'recommended';
    $result['label']       = __( 'Synkronointi on vanhentunut', 'tapojarvijobs' );
    $result['description'] = '<p>' . sprintf(
        /* translators: %d: hours since last sync */
        __( 'Viimeisin synkronointi on %d tuntia sitten. Tarkista WP-Cronin toiminta.', 'tapojarvijobs' ),
        (int) $hours_since
    ) . '</p>';
    return $result;
}

// Tarkistus 3: oliko virheitä viimeisimmässä synkronoinnissa
$stats = get_option( 'tjobs_last_sync_stats', array() );
$import_log = get_option( 'tjobs_import_log', array() );
if ( is_array( $import_log ) && ! empty( $import_log ) ) {
    $latest = end( $import_log );
    if ( ! empty( $latest['error'] ) ) {
        $result['status']      = 'critical';
        $result['label']       = __( 'Synkronoinnissa on virhe', 'tapojarvijobs' );
        $result['description'] = '<p>' . sprintf(
            /* translators: %s: error message */
            __( 'Viimeisin synkronointi päättyi virheeseen: %s', 'tapojarvijobs' ),
            esc_html( $latest['error'] )
        ) . '</p>';
        return $result;
    }
}

$result['description'] = '<p>' . __( 'RSS-syötteen synkronointi toimii normaalisti.', 'tapojarvijobs' ) . '</p>';
return $result;
}

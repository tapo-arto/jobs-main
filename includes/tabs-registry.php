<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tab-rekisteri – modaalin vaiheiden yksi totuuden lähde.
 *
 * Kaikki modaalin vaiheet rekisteröidään tänne. Ylläpidossa voi muuttaa
 * järjestystä (drag & drop) ja ottaa vaiheita pois käytöstä.
 *
 * @return array Järjestetty ja suodatettu taulukko aktiivisista välilehdistä.
 */
function tjobs_get_tab_registry() {
    // Kaikki mahdolliset vaiheet (rekisteri)
    $registry = array(
        'announcement' => array(
            'id'       => 'announcement',
            'label'    => 'tab.announcement',
            'required' => true,
            'render'   => 'tjobs_render_tab_announcement',
        ),
        'general'      => array(
            'id'       => 'general',
            'label'    => 'tab.general',
            'required' => false,
            'render'   => 'tjobs_render_tab_general',
        ),
        'videos'       => array(
            'id'       => 'videos',
            'label'    => 'tab.videos',
            'required' => false,
            'render'   => 'tjobs_render_tab_videos',
        ),
        'details'      => array(
            'id'       => 'details',
            'label'    => 'tab.details',
            'required' => false,
            'render'   => 'tjobs_render_tab_details',
        ),
        'questions'    => array(
            'id'       => 'questions',
            'label'    => 'tab.questions',
            'required' => true,
            'render'   => 'tjobs_render_tab_questions',
        ),
    );

    $valid_keys      = array_keys( $registry );
    $default_order   = $valid_keys;
    $default_enabled = $valid_keys;

    // Lue tallennettu järjestys ja käytössä olevat välilehdet optioista
    $saved_order   = get_option( 'tjobs_tab_order', $default_order );
    $saved_enabled = get_option( 'tjobs_tab_enabled', $default_enabled );

    // Whitelist-validointi: hyväksy vain rekisteröidyt avaimet
    if ( ! is_array( $saved_order ) ) {
        $saved_order = $default_order;
    }
    if ( ! is_array( $saved_enabled ) ) {
        $saved_enabled = $default_enabled;
    }

    $saved_order   = array_values( array_filter(
        $saved_order,
        function( $k ) use ( $valid_keys ) { return in_array( $k, $valid_keys, true ); }
    ) );
    $saved_enabled = array_values( array_filter(
        $saved_enabled,
        function( $k ) use ( $valid_keys ) { return in_array( $k, $valid_keys, true ); }
    ) );

    // Pakollisia välilehtiä ei voi poistaa käytöstä
    foreach ( $registry as $key => $tab ) {
        if ( ! empty( $tab['required'] ) && ! in_array( $key, $saved_enabled, true ) ) {
            $saved_enabled[] = $key;
        }
    }

    // Rakenna järjestetty lista: ensin tallennettu järjestys (vain käytössä olevat)
    $ordered = array();
    foreach ( $saved_order as $key ) {
        if ( isset( $registry[ $key ] ) && in_array( $key, $saved_enabled, true ) ) {
            $ordered[ $key ] = $registry[ $key ];
        }
    }

    // Lisää lopuksi mahdolliset uudet rekisteröidyt välilehdet, jotka eivät ole tallennetussa järjestyksessä
    foreach ( $registry as $key => $tab ) {
        if ( ! isset( $ordered[ $key ] ) && in_array( $key, $saved_enabled, true ) ) {
            $ordered[ $key ] = $tab;
        }
    }

    /**
     * Filtteri laajennettavuutta varten.
     *
     * @param array $ordered Järjestetty taulukko aktiivisista välilehdistä.
     */
    return apply_filters( 'tjobs_tab_registry', $ordered );
}

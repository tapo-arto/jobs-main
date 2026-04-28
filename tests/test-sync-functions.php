<?php
/**
 * PHPUnit-testit sync-funktioille ja tab-rekisterille.
 *
 * @package TJobs_Plugin
 */

class Test_Sync_Functions extends WP_UnitTestCase {

    /**
     * Testaa tjobs_sync_feed() tyhjällä feed URL:lla.
     * Odotettu tulos: palauttaa tyhjät taulukot, ei lisää postauksia.
     */
    public function test_sync_feed_empty_url() {
        // Varmistetaan, että feed_url on tyhjä
        update_option( 'tjobs_settings', array( 'feed_url' => '' ) );

        $result = tjobs_sync_feed();

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'added', $result );
        $this->assertArrayHasKey( 'removed', $result );
        $this->assertArrayHasKey( 'updated', $result );
        $this->assertEmpty( $result['added'] );
        $this->assertEmpty( $result['removed'] );
        $this->assertEmpty( $result['updated'] );

        // Varmistetaan, että synkka-aika on päivittynyt
        $this->assertNotEmpty( get_option( 'tjobs_last_sync' ) );
    }

    /**
     * Testaa kiellettyjen otsikoiden suodatusta.
     * Varmistaa, että kielletyt otsikot suodatetaan pois.
     */
    public function test_forbidden_titles_are_filtered() {
        $opts = tjobs_get_settings();

        // Kielletyt otsikot oletusasetuksissa
        $forbidden_raw   = $opts['forbidden_titles'];
        $forbidden_titles = array_filter( array_map( 'trim', explode( "\n", $forbidden_raw ) ) );

        // Varmistetaan, että oletusotsikot ovat kielletyissä
        $this->assertContains( 'Avoin hakemus', $forbidden_titles );
        $this->assertContains( 'Open application', $forbidden_titles );
        $this->assertContains( 'Öppen ansökan', $forbidden_titles );
    }

    /**
     * Testaa asetusten oletusarvot.
     */
    public function test_default_settings() {
        // Poistetaan optio, jotta saadaan oletusarvot
        delete_option( 'tjobs_settings' );

        $opts = tjobs_get_settings();

        $this->assertIsArray( $opts );
        $this->assertSame( '', $opts['feed_url'] );
        $this->assertSame( 10, $opts['items_count'] );
        $this->assertSame( 'date', $opts['order_by'] );
        $this->assertSame( 'DESC', $opts['order'] );
        $this->assertSame( '#000000', $opts['link_color'] );
        $this->assertSame( '#666666', $opts['description_text_color'] );
        $this->assertSame( '#ff0000', $opts['link_hover_color'] );
    }

    // =========================================================================
    // Tab-rekisteri
    // =========================================================================

    /**
     * Puhdista wizard-asetukset jokaisen testin jälkeen.
     */
    public function tearDown(): void {
        delete_option( 'tjobs_tab_order' );
        delete_option( 'tjobs_tab_enabled' );
        parent::tearDown();
    }

    /**
     * Testaa oletusjärjestys: kaikki viisi välilehteä oikeassa järjestyksessä.
     */
    public function test_tab_registry_default_order() {
        delete_option( 'tjobs_tab_order' );
        delete_option( 'tjobs_tab_enabled' );

        $tabs = tjobs_get_tab_registry();

        $this->assertIsArray( $tabs );
        $this->assertCount( 5, $tabs );

        $keys = array_keys( $tabs );
        $this->assertSame( array( 'announcement', 'general', 'videos', 'details', 'questions' ), $keys );
    }

    /**
     * Testaa tallennettu järjestys: järjestys heijastuu rekisteristä.
     */
    public function test_tab_registry_saved_order() {
        update_option( 'tjobs_tab_order', array( 'questions', 'announcement', 'general', 'videos', 'details' ) );
        update_option( 'tjobs_tab_enabled', array( 'announcement', 'general', 'videos', 'details', 'questions' ) );

        $tabs = tjobs_get_tab_registry();
        $keys = array_keys( $tabs );

        $this->assertSame( 'questions', $keys[0] );
        $this->assertSame( 'announcement', $keys[1] );
    }

    /**
     * Testaa whitelist-validointi: tuntemattomat avaimet hylätään.
     */
    public function test_tab_registry_whitelist_validation() {
        update_option( 'tjobs_tab_order', array( 'announcement', 'invalid_tab', 'general', 'questions' ) );
        update_option( 'tjobs_tab_enabled', array( 'announcement', 'general', 'questions', 'malicious_key' ) );

        $tabs = tjobs_get_tab_registry();
        $keys = array_keys( $tabs );

        $this->assertNotContains( 'invalid_tab', $keys );
        $this->assertNotContains( 'malicious_key', $keys );
        $this->assertContains( 'announcement', $keys );
        $this->assertContains( 'general', $keys );
    }

    /**
     * Testaa, että pakollisia välilehtiä ei voi poistaa käytöstä.
     */
    public function test_required_tabs_always_included() {
        // Yritetään poistaa pakolliset välilehdet käytöstä
        update_option( 'tjobs_tab_enabled', array( 'general', 'videos' ) );

        $tabs = tjobs_get_tab_registry();
        $keys = array_keys( $tabs );

        $this->assertContains( 'announcement', $keys, 'announcement on pakollinen, ei voi poistaa' );
        $this->assertContains( 'questions', $keys, 'questions on pakollinen, ei voi poistaa' );
    }

    /**
     * Testaa, että disabled-välilehtiä ei palauteta (paitsi pakolliset).
     */
    public function test_disabled_tabs_excluded() {
        update_option( 'tjobs_tab_order', array( 'announcement', 'general', 'videos', 'details', 'questions' ) );
        update_option( 'tjobs_tab_enabled', array( 'announcement', 'questions' ) ); // vain pakolliset

        $tabs = tjobs_get_tab_registry();
        $keys = array_keys( $tabs );

        $this->assertNotContains( 'general', $keys );
        $this->assertNotContains( 'videos', $keys );
        $this->assertNotContains( 'details', $keys );
        $this->assertContains( 'announcement', $keys );
        $this->assertContains( 'questions', $keys );
    }

    /**
     * Testaa, että filtteri toimii.
     */
    public function test_tab_registry_filter() {
        delete_option( 'tjobs_tab_order' );
        delete_option( 'tjobs_tab_enabled' );

        // Lisätään testi-filtteri joka poistaa videos-välilehden
        add_filter( 'tjobs_tab_registry', function( $tabs ) {
            unset( $tabs['videos'] );
            return $tabs;
        } );

        $tabs = tjobs_get_tab_registry();

        remove_all_filters( 'tjobs_tab_registry' );

        $this->assertArrayNotHasKey( 'videos', $tabs );
        $this->assertArrayHasKey( 'announcement', $tabs );
    }
}

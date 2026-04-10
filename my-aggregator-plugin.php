<?php
/**
 * Plugin Name: Tapojärvi Jobs Infopaketit (V2)
 * Description: Tapojärvi Jobs Infopaketit – erillinen lisäosa RSS-synkronointiin, infopaketteihin, REST API:hin, Gutenberg-blokkiin ja Schema.org-merkintöihin.
 * Version: 4.0.9
 * Author: Arto Huhta
 * Text Domain: tapojarvijobs
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ladataan muut tiedostot
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/cpt-and-cron.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/sync-functions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/shortcodes.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/rest-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/gutenberg-block.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/schema-markup.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/security-improvements.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/health-check.php';
// Infopaketti-toiminto: i18n täytyy ladata ennen muita uusia tiedostoja
require_once plugin_dir_path( __FILE__ ) . 'includes/i18n-strings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/cpt-infopackage.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/automation-rules.php';
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/wp-cli.php';
}

// === Aktivointi / deaktivointi -hookit ===
// Huom! On tärkeää, että nämä hookit ovat pluginin pääkooditiedostossa
register_activation_hook( __FILE__, 'tjobs_activate_cron' );
register_deactivation_hook( __FILE__, 'tjobs_deactivate_cron' );

// === Rekisteröi CSS ja JS fronttiin ===
function tjobs_register_assets() {
    // Ladataan julkisen puolen CSS (my-job-list-tyylit), jos haluat
    $css_path = plugin_dir_path(__FILE__) . 'css/minun-aggregator-plugin.css';
    if ( file_exists( $css_path ) ) {
        wp_enqueue_style(
            'tjobs-css',
            plugins_url( 'css/minun-aggregator-plugin.css', __FILE__ ),
            array(),
            filemtime( $css_path )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'tjobs_register_assets' );

// === Rekisteröi CSS ja JS admin-puolelle ===
function tjobs_register_admin_assets( $hook ) {
    // Ladataan adminin CSS/JS vain, jos ollaan pluginin asetussivulla
    $screen = get_current_screen();
    if ( 'toplevel_page_tjobs-v2-settings' === $hook || ( $screen && 'tjobs_infopackage' === $screen->post_type ) ) {
        // Admin-CSS
        $admin_css = plugin_dir_path(__FILE__) . 'css/admin-minun-aggregator-plugin.css';
        if ( file_exists( $admin_css ) ) {
            wp_enqueue_style(
                'tjobs-admin-css',
                plugins_url( 'css/admin-minun-aggregator-plugin.css', __FILE__ ),
                array('wp-color-picker'),
                filemtime( $admin_css )
            );
        }

        // Admin-JS (valinnainen, jos haluat värivalitsimen yms.)
        $admin_js = plugin_dir_path(__FILE__) . 'js/admin-minun-aggregator-plugin.js';
        if ( file_exists( $admin_js ) ) {
            wp_enqueue_script(
                'tjobs-admin-js',
                plugins_url( 'js/admin-minun-aggregator-plugin.js', __FILE__ ),
                array('wp-color-picker'),
                filemtime( $admin_js ),
                true
            );
        }
    }
}
add_action( 'admin_enqueue_scripts', 'tjobs_register_admin_assets' );


// ============================================================================
// ===============  U U D E T  LISÄYKSET: BUILDER-SUOJA & CACHE  =============
// ============================================================================

/**
 * Tunnista sivunrakentajan (Elementor/Gutenberg tms.) editointitila.
 * Palauttaa true, jos ollaan builderissa (tällöin vältetään raskaat haut).
 */
function tjobs_is_builder_request() {
    // WP admin -alueessa ei yleensä renderöidä fronttia, mutta Elementor voi käyttää admin-iframeä.
    if ( is_admin() ) {
        // Sallitaan kuitenkin pluginin oma asetussivu normaaliin käyttöön
        if ( isset($_GET['page']) && $_GET['page'] === 'tjobs-v2-settings' ) {
            return false;
        }
        return true;
    }

    // Elementor-parametrit URL:ssa tai editorin tila
    if ( defined('ELEMENTOR_VERSION') ) {
        if ( isset($_GET['elementor-preview']) || (isset($_REQUEST['action']) && $_REQUEST['action'] === 'elementor') ) {
            return true;
        }
        if ( class_exists('\Elementor\Plugin') ) {
            try {
                $plugin = \Elementor\Plugin::$instance;
                if ( $plugin && isset($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode') ) {
                    if ( $plugin->editor->is_edit_mode() ) {
                        return true;
                    }
                }
            } catch ( \Throwable $e ) {
                // ignooraa – palataan oletukseen
            }
        }
    }

    // AJAX-esikatseluja on paljon, ei oleteta automaattisesti builderiksi
    return false;
}

/**
 * Kohdelyhytkoodit, joita suojataan builderissa ja joille tehdään HTML-välimuisti.
 * Lisää tänne lyhytkoodit, joita lisäosa käyttää listauksen näyttämiseen.
 */
function tjobs_target_shortcodes() {
    // Skannauksen perusteella lisäosa käyttää tageja 'tjobs_list' ja 'tjobs_by_country'
    return array( 'tjobs_list', 'tjobs_by_country' );
}

/**
 * Generoi välimuistin avaimen lyhytkoodin tulosteelle.
 * Sisältää myös "cache bump" -option, jonka sync päivittää (get_option('tjobs_cache_bump',0)).
 */
function tjobs_jobs_cache_key( $tag, $atts ) {
    if ( is_array( $atts ) ) {
        ksort( $atts );
    }
    $bump = (int) get_option( 'tjobs_cache_bump', 0 );
    $lang = '';
    if ( function_exists( 'pll_current_language' ) ) {
        $lang = pll_current_language() ?: 'fi';
    }
    return 'tjobs_html_' . md5( $tag . '|' . wp_json_encode( $atts ) . '|' . $bump . '|' . $lang );
}

/**
 * Ennen kuin WordPress suorittaa lyhytkoodin:
 * - Builderissa palautetaan kevyt placeholder (ei raskaita hakuja eikä ulkoisia pyyntöjä).
 * - Julkisella puolella kokeillaan ensin välimuistia.
 */
function tjobs_prevent_heavy_shortcodes_in_builder( $return, $tag, $atts, $m ) {
    // Vain omat lyhytkoodit
    if ( ! in_array( $tag, tjobs_target_shortcodes(), true ) ) {
        return $return;
    }

    // Builder: näytä kevyt esikatselu
    if ( function_exists( 'tjobs_is_builder_request' ) && tjobs_is_builder_request() ) {
        return '<div class="my-job-list my-job-list--placeholder" style="opacity:.7;">' . esc_html__( 'Avoimet työpaikat – esikatselu. Julkaisussa listaus näkyy normaalisti.', 'tapojarvijobs' ) . '</div>';
    }

    // Julkinen puoli: kokeile välimuistia ennen varsinaista renderöintiä
    $key     = tjobs_jobs_cache_key( $tag, (array) $atts );
    $cached  = get_transient( $key );
    if ( false !== $cached ) {
        return $cached; // Palauta suoraan välimuistista
    }

    return $return; // Anna jatkua normaalille lyhytkoodille
}
add_filter( 'pre_do_shortcode_tag', 'tjobs_prevent_heavy_shortcodes_in_builder', 10, 4 );

/**
 * Kun lyhytkoodi on ajettu: tallenna HTML välimuistiin (vain julkisessa näkymässä).
 */
function tjobs_cache_shortcode_output( $output, $tag, $atts, $m ) {
    if ( ! in_array( $tag, tjobs_target_shortcodes(), true ) ) {
        return $output;
    }
    if ( function_exists( 'tjobs_is_builder_request' ) && tjobs_is_builder_request() ) {
        return $output; // builderissa ei cacheteta (palautettiin jo placeholder)
    }

    $key = tjobs_jobs_cache_key( $tag, (array) $atts );
    set_transient( $key, $output, 5 * MINUTE_IN_SECONDS ); // 5 min HTML-cache
    return $output;
}
add_filter( 'do_shortcode_tag', 'tjobs_cache_shortcode_output', 10, 4 );

/**
 * RSS/HTTP-ajoasetukset: lyhyempi timeout ja kohtuullinen feed-välimuisti.
 * Tämä auttaa sekä julkisessa näkymässä että editorissa, jos feedi on hidas.
 */
function tjobs_feed_set_timeout( $feed ) {
    if ( is_object( $feed ) && method_exists( $feed, 'set_timeout' ) ) {
        $feed->set_timeout( 7 ); // sekuntia
    }
}
add_action( 'wp_feed_options', 'tjobs_feed_set_timeout' );

function tjobs_feed_cache_lifetime( $seconds ) {
    // 30 min feed-välimuisti (SimplePie). Huom: ei sama kuin HTML-outputin transient.
    return 30 * MINUTE_IN_SECONDS;
}
add_filter( 'wp_feed_cache_transient_lifetime', 'tjobs_feed_cache_lifetime' );

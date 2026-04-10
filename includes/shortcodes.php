<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lataa modal-assettit (CSS + JS) fronttiin.
 * Kutsutaan shortcodeista tarpeen mukaan.
 */
function tjobs_enqueue_modal_assets() {
$css_path = plugin_dir_path( dirname( __FILE__ ) ) . 'css/modal-infopackage.css';
if ( file_exists( $css_path ) ) {
    wp_enqueue_style(
        'tjobs-modal-css',
        plugins_url( 'css/modal-infopackage.css', dirname( __FILE__ ) ),
        array(),
        filemtime( $css_path )
    );
}

$js_path = plugin_dir_path( dirname( __FILE__ ) ) . 'js/frontend-modal.js';
if ( file_exists( $js_path ) ) {
    wp_enqueue_script(
        'tjobs-modal-js',
        plugins_url( 'js/frontend-modal.js', dirname( __FILE__ ) ),
        array(),
        filemtime( $js_path ),
        true
    );

    $lang = 'fi';
    if ( function_exists( 'tjobs_get_current_lang' ) ) {
        $lang = tjobs_get_current_lang();
    } elseif ( function_exists( 'pll_current_language' ) ) {
        $lang = pll_current_language() ?: 'fi';
    }

    $i18n = function_exists( 'tjobs_get_js_translations' ) ? tjobs_get_js_translations( $lang ) : array();

    wp_localize_script(
        'tjobs-modal-js',
        'tjobsModalConfig',
        array(
            'restUrl' => esc_url_raw( rest_url( 'tjobs/v1' ) ),
            'lang'    => $lang,
            'i18n'    => $i18n,
        )
    );
}
}


/**
 * Palauttaa nykyiselle kielelle sopivan avoimen hakemuksen URL:n.
 *
 * @param string $lang_code Nykyinen kielikoodi.
 * @return string
 */
function tjobs_get_open_application_url_for_language($lang_code = '') {
$lang_code = sanitize_key((string) $lang_code);
if ($lang_code === '') {
    $lang_code = 'fi';
    if (function_exists('pll_current_language')) {
        $current = pll_current_language();
        if (!empty($current)) {
            $lang_code = $current;
        }
    }
}

$urls = get_option('tjobs_open_application_urls', array());
if (is_array($urls) && !empty($urls)) {
    if (!empty($urls[$lang_code])) {
        return $urls[$lang_code];
    }

    foreach (array('en', 'fi', 'sv', 'it') as $fallback_lang) {
        if (!empty($urls[$fallback_lang])) {
            return $urls[$fallback_lang];
        }
    }

    $first = reset($urls);
    if (!empty($first)) {
        return $first;
    }
}

$legacy_url = get_option('tjobs_open_application_url', '');
return is_string($legacy_url) ? $legacy_url : '';
}

/**
 * Lyhytkoodin logiikka
 *
 * @param array $atts Lyhytkoodin attribuutit
 * @return string Työpaikkalistaus HTML-muodossa
 */
function tjobs_jobs_list_shortcode($atts) {
// Lataa modal-assettit
tjobs_enqueue_modal_assets();

// Hae asetukset
$opts = tjobs_get_settings();

// Polylang: tunnista kieli renderöintihetkellä
$lang_code = 'fi';
if (function_exists('pll_current_language')) {
    $current = pll_current_language();
    if ($current) {
        $lang_code = $current;
    }
}

// Kielikohtainen label hakuajan päättymiselle
switch ($lang_code) {
    case 'fi':
        $end_label = 'Hakuaika päättyy';
        break;
    case 'en':
        $end_label = 'Application ends';
        break;
    case 'sv':
        $end_label = 'Ansökan slutar';
        break;
    case 'it':
        $end_label = "L'applicazione termina";
        break;
    default:
        $end_label = 'Application ends';
}

// Lyhytkoodin attribuuttien oletukset
$args = shortcode_atts(array(
    'import' => 'no', // Oletus: ei pakotettua tuontia
), $atts);

// Pakota RSS-syötteen synkronointi, jos `import="yes"`
if (strtolower($args['import']) === 'yes') {
    tjobs_sync_feed();
}

// Hae työpaikat Custom Post Type -tietokannasta
$query_args = array(
    'post_type'      => 'tjobs_tyopaikat',
    'post_status'    => 'publish',
    'posts_per_page' => $opts['items_count'], // Asetuksista haettu määrä
    'orderby'        => $opts['order_by'],    // Asetuksista haettu järjestyskenttä
    'order'          => $opts['order'],       // Asetuksista haettu järjestyssuunta
);

$query = new WP_Query($query_args);

// Jos ei löydy yhtään työpaikkaa
if (!$query->have_posts()) {
    switch ($lang_code) {
        case 'fi':
            $no_jobs_text = 'Ei työpaikkoja saatavilla.';
            break;
        case 'sv':
            $no_jobs_text = 'Inga lediga jobb tillgängliga.';
            break;
        case 'it':
            $no_jobs_text = 'Nessun lavoro disponibile.';
            break;
        default:
            $no_jobs_text = 'No jobs available.';
    }
    return '<p>' . esc_html($no_jobs_text) . '</p>';
}

// Validoi värit ennen inline-CSS:ää
$link_color       = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $opts['link_color'])       ? $opts['link_color']       : '#000000';
$link_hover_color = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $opts['link_hover_color']) ? $opts['link_hover_color'] : '#ff0000';
$desc_text_color  = preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $opts['description_text_color']) ? $opts['description_text_color'] : '#666666';

// Dynaaminen inline-CSS väripäivityksiä varten
$inline_css = '
    .tjobs-job-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .tjobs-job-list li {
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.25);
    }
    .tjobs-job-list a {
        color: ' . esc_attr( $link_color ) . ';
        text-decoration: none;
        font-weight: bold;
        font-size: 18px;
    }
    .tjobs-job-list a:hover {
        color: ' . esc_attr( $link_hover_color ) . ';
        text-decoration: none;
    }
    .tjobs-job-list .description {
        color: ' . esc_attr( $desc_text_color ) . ';
        font-size: 0.8rem;
        font-weight: 300;
        margin-top: 5px;
    }';

// Lisätään inline-tyylit asianmukaisesti
if ( wp_style_is( 'tjobs-css', 'enqueued' ) ) {
    wp_add_inline_style( 'tjobs-css', $inline_css );
} elseif ( wp_style_is( 'tjobs-modal-css', 'enqueued' ) ) {
    wp_add_inline_style( 'tjobs-modal-css', $inline_css );
} else {
    $output = '<style>' . $inline_css . '</style>';
}

if ( ! isset( $output ) ) {
    $output = '';
}

$output .= '<ul class="tjobs-job-list">';
while ($query->have_posts()) {
    $query->the_post();

    $post_id = get_the_ID();
    $title   = get_the_title();
    $link    = get_post_meta( $post_id, '_tjobs_rss_link', true );
    $excerpt = get_the_excerpt();

    $output .= '<li>';
    if ($link) {
        $output .= '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a>';
    } else {
        // Jos linkkiä ei ole, näytetään pelkkä otsikko
        $output .= esc_html($title);
    }

    if ($excerpt) {
        $output .= '<div class="description">' . esc_html($end_label . ': ' . $excerpt) . '</div>';
    }

    $output .= '</li>';
}
$output .= '</ul>';

wp_reset_postdata(); // Palautetaan WP:n query-tila

return $output;
}
add_shortcode('tjobs_list', 'tjobs_jobs_list_shortcode');

/**
 * Lyhytkoodin logiikka: maakohtainen ryhmittely moderneilla korteilla
 *
 * @param array $atts Lyhytkoodin attribuutit
 * @return string Maakohtainen työpaikkalistaus HTML-muodossa
 */
function tjobs_jobs_by_country_shortcode($atts) {
// Lataa modal-assettit
tjobs_enqueue_modal_assets();

// Hae asetukset
$opts = tjobs_get_settings();

// Polylang: tunnista kieli renderöintihetkellä
$lang_code = 'fi';
if (function_exists('pll_current_language')) {
    $current = pll_current_language();
    if ($current) {
        $lang_code = $current;
    }
}

// Maat kiinteässä järjestyksessä
$countries = array(
    'fi' => array('flag' => 'https://tapojarvi.com/wp-content/uploads/2026/02/tapojarvi_icon_122-finnish-flag.svg',  'fi' => 'Suomi',  'en' => 'Finland', 'sv' => 'Finland',  'it' => 'Finlandia'),
    'se' => array('flag' => 'https://tapojarvi.com/wp-content/uploads/2026/02/tapojarvi_icon_123-swedish-flag.svg',  'fi' => 'Ruotsi', 'en' => 'Sweden',  'sv' => 'Sverige',  'it' => 'Svezia'),
    'gr' => array('flag' => 'https://tapojarvi.com/wp-content/uploads/2026/02/tapojarvi_icon_125-greek-flag.svg',    'fi' => 'Kreikka','en' => 'Greece',  'sv' => 'Grekland', 'it' => 'Grecia'),
    'it' => array('flag' => 'https://tapojarvi.com/wp-content/uploads/2026/02/tapojarvi_icon_124-italian-flag.svg',  'fi' => 'Italia', 'en' => 'Italy',   'sv' => 'Italien',  'it' => 'Italia'),
);

// Monikieliset UI-tekstit
$texts = array(
    'fi' => array(
        'end_label'      => 'Hakuaika päättyy',
        'no_jobs'        => 'Ei avoimia työpaikkoja tällä hetkellä.',
        'open_positions' => 'avointa paikkaa',
        'apply'          => 'Hae paikkaa',
        'cta_title'      => 'Etkö löytänyt sopivaa?',
        'cta_text'       => 'Jätä avoin hakemus – otamme yhteyttä, kun sopivia tehtäviä avautuu!',
        'cta_button'     => 'Jätä avoin hakemus',
    ),
    'en' => array(
        'end_label'      => 'Application ends',
        'no_jobs'        => 'No open positions at the moment.',
        'open_positions' => 'open positions',
        'apply'          => 'Apply',
        'cta_title'      => "Didn't find a suitable position?",
        'cta_text'       => 'Submit an open application – we will contact you when matching opportunities arise!',
        'cta_button'     => 'Submit open application',
    ),
    'sv' => array(
        'end_label'      => 'Ansökan slutar',
        'no_jobs'        => 'Inga lediga jobb just nu.',
        'open_positions' => 'lediga jobb',
        'apply'          => 'Ansök',
        'cta_title'      => 'Hittade du inte rätt tjänst?',
        'cta_text'       => 'Skicka en öppen ansökan – vi kontaktar dig när passande tjänster dyker upp!',
        'cta_button'     => 'Skicka öppen ansökan',
    ),
    'it' => array(
        'end_label'      => 'Scadenza',
        'no_jobs'        => 'Nessuna posizione aperta al momento.',
        'open_positions' => 'posizioni aperte',
        'apply'          => 'Candidati',
        'cta_title'      => 'Non hai trovato la posizione giusta?',
        'cta_text'       => 'Invia una candidatura spontanea – ti contatteremo quando ci saranno opportunità adatte!',
        'cta_button'     => 'Invia candidatura spontanea',
    ),
);
$t = isset($texts[$lang_code]) ? $texts[$lang_code] : $texts['en'];

// Käännökset RSS-syötteestä tuleville _tjobs_type ja _tjobs_worktime arvoille
// RSS antaa arvot aina suomeksi
$jobtype_translations = array(
    'Vakituinen'    => array('fi' => 'Vakituinen',    'en' => 'Permanent',   'sv' => 'Tillsvidare',   'it' => 'Tempo indeterminato'),
    'Määräaikainen' => array('fi' => 'Määräaikainen', 'en' => 'Fixed-term',  'sv' => 'Visstid',       'it' => 'Tempo determinato'),
    'Kesätyö'       => array('fi' => 'Kesätyö',       'en' => 'Summer job',  'sv' => 'Sommarjobb',    'it' => 'Lavoro estivo'),
    'Harjoittelu'   => array('fi' => 'Harjoittelu',   'en' => 'Internship',  'sv' => 'Praktik',       'it' => 'Stage'),
);

$worktime_translations = array(
    'Kokoaikainen' => array('fi' => 'Kokoaikainen', 'en' => 'Full-time',  'sv' => 'Heltid',  'it' => 'Tempo pieno'),
    'Osa-aikainen' => array('fi' => 'Osa-aikainen', 'en' => 'Part-time',  'sv' => 'Deltid',  'it' => 'Part-time'),
);

// Shortcode attribuutit
$args = shortcode_atts(array(
    'theme' => 'dark', // Oletus: tumma teema
), $atts);

$theme_class = ($args['theme'] === 'light') ? 'tjobs-theme-light' : 'tjobs-theme-dark';
$output = '<div class="tjobs-jobs-by-country ' . esc_attr($theme_class) . '">';

// Auto-synkronointi: jos ei ole yhtään julkaistua työpaikkaa, haetaan data automaattisesti.
// Käytetään staattista muuttujaa, jotta tarkistus tehdään korkeintaan kerran per sivulataus.
static $tjobs_auto_sync_done = false;
if ( ! $tjobs_auto_sync_done ) {
    $tjobs_auto_sync_done = true;
    $total_jobs_count = wp_count_posts( 'tjobs_tyopaikat' );
    $total_published  = isset( $total_jobs_count->publish ) ? (int) $total_jobs_count->publish : 0;
    if ( $total_published === 0 ) {
        $last_sync = (int) get_option( 'tjobs_last_sync', 0 );
        if ( ( time() - $last_sync ) > 5 * MINUTE_IN_SECONDS ) {
            tjobs_sync_feed();
        }
    }
}

foreach ($countries as $code => $country_data) {
    // Maan nimi nykyisellä kielellä
    $country_name = isset($country_data[$lang_code]) ? $country_data[$lang_code] : $country_data['en'];
    $flag         = $country_data['flag'];

    $query = new WP_Query(array(
        'post_type'      => 'tjobs_tyopaikat',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => '_tjobs_country',
        'meta_value'     => $code,
        'orderby'        => $opts['order_by'],
        'order'          => $opts['order'],
    ));

    $job_count = $query->found_posts;

    // Ohita maat joissa ei ole avoimia työpaikkoja
    if ($job_count === 0) {
        wp_reset_postdata();
        continue;
    }

    $output .= '<section class="tjobs-country-section" data-country="' . esc_attr($code) . '">';

    $output .= '<div class="tjobs-country-header">';
    $output .= '<h2 class="tjobs-country-title">';
    $output .= '<span class="tjobs-country-flag"><img src="' . esc_url($flag) . '" alt="' . esc_attr($country_name) . '" class="tjobs-country-flag__img"></span>';
    $output .= '<span class="tjobs-country-name">' . esc_html($country_name) . '</span>';
    $output .= '</h2>';
    $output .= '<span class="tjobs-country-count">' . esc_html($job_count . ' ' . $t['open_positions']) . '</span>';
    $output .= '</div>';

    $output .= '<div class="tjobs-jobs-grid">';
        while ($query->have_posts()) {
            $query->the_post();
            $post_id  = get_the_ID();
            $title    = get_the_title();
            $excerpt  = get_the_excerpt();
            $form_url = get_post_meta($post_id, '_tjobs_form_url', true);
            $jobtype  = get_post_meta($post_id, '_tjobs_type', true);
            $worktime = get_post_meta($post_id, '_tjobs_worktime', true);

            // Käytä form_url:ia ensisijaisesti, fallback _tjobs_rss_link
            $apply_url = !empty($form_url) ? $form_url : get_post_meta($post_id, '_tjobs_rss_link', true);

            $output .= '<article class="tjobs-job-card">';
            $output .= '<div class="tjobs-job-card__content">';

            // Otsikko: pelkkä teksti ilman badge tai modal-attribuuttia
            $output .= '<h3 class="tjobs-job-card__title">' . esc_html($title) . '</h3>';

            if (!empty($excerpt)) {
                $output .= '<div class="tjobs-job-card__meta">';
                $output .= '<span class="tjobs-job-card__deadline">';
                $output .= '<span class="tjobs-job-card__meta-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>';
                $output .= esc_html($t['end_label'] . ': ' . $excerpt);
                $output .= '</span>';
                $output .= '</div>';
            }

            if (!empty($jobtype) || !empty($worktime)) {
                $output .= '<div class="tjobs-job-card__tags">';
                if (!empty($jobtype)) {
                    $translated_jobtype = isset($jobtype_translations[$jobtype][$lang_code])
                        ? $jobtype_translations[$jobtype][$lang_code]
                        : $jobtype;
                    $output .= '<span class="tjobs-job-card__tag">' . esc_html($translated_jobtype) . '</span>';
                }
                if (!empty($worktime)) {
                    $translated_worktime = isset($worktime_translations[$worktime][$lang_code])
                        ? $worktime_translations[$worktime][$lang_code]
                        : $worktime;
                    $output .= '<span class="tjobs-job-card__tag">' . esc_html($translated_worktime) . '</span>';
                }
                $output .= '</div>';
            }

            $output .= '</div>'; // tjobs-job-card__content

            if (!empty($apply_url)) {
                // Nappi avaa aina modal-infopaneelin; data haetaan dynaamisesti REST API:sta
                $output .= '<div class="tjobs-job-card__action">';
                $output .= '<button type="button" class="tjobs-job-card__apply-btn" data-job-id="' . esc_attr( $post_id ) . '" aria-label="' . esc_attr( $t['apply'] . ': ' . $title ) . '">';
                $output .= esc_html($t['apply']) . ' <span class="tjobs-job-card__arrow">→</span>';
                $output .= '</button>';
                $output .= '</div>';
            }

            $output .= '</article>';
        }
        $output .= '</div>'; // tjobs-jobs-grid

    $output .= '</section>';
    wp_reset_postdata();
}

// CTA-banneri – näytetään vain kerran lopussa, jos avoin hakemus -URL löytyy
$cta_url = tjobs_get_open_application_url_for_language($lang_code);
if (!empty($cta_url)) {
    $output .= '<div class="tjobs-cta-banner">';
    $output .= '<div class="tjobs-cta-banner__content">';
    $output .= '<div class="tjobs-cta-banner__icon"><img src="https://tapojarvi.com/wp-content/uploads/2026/02/tapojarvi_icon_118-yellow.svg" alt="" class="tjobs-cta-banner__icon-img"></div>';
    $output .= '<h3 class="tjobs-cta-banner__title">' . esc_html($t['cta_title']) . '</h3>';
    $output .= '<p class="tjobs-cta-banner__text">' . esc_html($t['cta_text']) . '</p>';
    $output .= '<a href="' . esc_url($cta_url) . '" target="_blank" rel="noopener" class="tjobs-cta-banner__button">';
    $output .= esc_html($t['cta_button']) . ' <span class="tjobs-cta-banner__arrow">→</span>';
    $output .= '</a>';
    $output .= '</div>';
    $output .= '</div>';
}

$output .= '</div>'; // tjobs-jobs-by-country

return $output;
}
add_shortcode('tjobs_by_country', 'tjobs_jobs_by_country_shortcode');

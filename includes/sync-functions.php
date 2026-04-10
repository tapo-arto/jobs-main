<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Puhdista URL poistamalla ankkurit (#...) ja niiden jälkeen tulevat osat
 *
 * @param string $url URL jonka ankkurit poistetaan
 * @return string Puhdistettu URL
 */
function tjobs_clean_url($url) {
    if (empty($url)) {
        return '';
    }
    $url = preg_replace('/#.*$/', '', $url);
    return trim($url);
}


/**
 * Tunnistaa avoimen hakemuksen kielen RSS-itemin otsikosta.
 *
 * @param string $title RSS-itemin otsikko.
 * @return string Kielikoodi ('fi', 'en', 'sv', 'it') tai tyhjä merkkijono.
 */
function tjobs_detect_open_application_language($title) {
    $title = mb_strtolower(trim(wp_strip_all_tags((string) $title)), 'UTF-8');

    $map = array(
        'avoin hakemus'         => 'fi',
        'open application'      => 'en',
        'öppen ansökan'         => 'sv',
        'candidatura spontanea' => 'it',
        'candidatura aperta'    => 'it',
    );

    foreach ($map as $needle => $lang_code) {
        if ($needle !== '' && mb_strpos($title, $needle, 0, 'UTF-8') !== false) {
            return $lang_code;
        }
    }

    return '';
}

/**
 * Muuntaa Laura-syötteen maan nimen maakoodiksi.
 *
 * @param string $country_name Maan nimi Laura-syötteestä.
 * @return string Maakoodi ('fi', 'se', 'gr', 'it').
 */
function tjobs_country_name_to_code($country_name) {
    $map = array(
        'suomi'   => 'fi',
        'finland' => 'fi',
        'ruotsi'  => 'se',
        'sweden'  => 'se',
        'sverige' => 'se',
        'kreikka' => 'gr',
        'greece'  => 'gr',
        'italia'  => 'it',
        'italy'   => 'it',
    );
    $lower = mb_strtolower(trim($country_name), 'UTF-8');
    return isset($map[$lower]) ? $map[$lower] : 'fi';
}

/**
 * Synkronointifunktio: hakee RSS-syötteen ja päivittää kohteet.
 *
 * @return array Lisättyjen, poistettujen ja päivitettyjen tiedot.
 */
function tjobs_sync_feed() {
// 1. Haetaan asetukset
$opts     = tjobs_get_settings();
$feed_url = isset($opts['feed_url']) ? $opts['feed_url'] : '';

// Jos syöte-URL puuttuu asetuksista, ei tehdä mitään
if (empty($feed_url)) {
    // Päivitä "viimeisin synkka" -tilastot tyhjänäkin
    update_option('tjobs_last_sync', time());
    update_option('tjobs_last_sync_stats', array(
        'time'    => current_time('mysql'),
        'added'   => 0,
        'removed' => 0,
        'updated' => 0,
    ), false);
    return array('added' => array(), 'removed' => array(), 'updated' => array());
}

// 2. Ladataan WordPressin feed-työkalut (SimplePie) ja yritetään hakea syöte
include_once(ABSPATH . WPINC . '/feed.php');
$feed = fetch_feed($feed_url);

if (is_wp_error($feed)) {
    // Syötteen haku epäonnistui – kirjataan virhe (mutta ei "päivityslokia")
    $error_msg = $feed->get_error_message();

    // Päivitä tilastot (0/0/0, mutta virhe talteen logiin näkyvyyden vuoksi)
    update_option('tjobs_last_sync', time());
    update_option('tjobs_last_sync_stats', array(
        'time'    => current_time('mysql'),
        'added'   => 0,
        'removed' => 0,
        'updated' => 0,
    ), false);

    // Kirjaa virhe lokiin (sallittu poikkeus, vaikka added/removed olisi tyhjä)
    tjobs_log_import(array(), array(), $error_msg, array(), array());

    return array('added' => array(), 'removed' => array(), 'updated' => array(), 'error' => $error_msg);
}

// 3. Haetaan jo olemassa olevat avoimet työpaikat (CPT: tjobs_tyopaikat)
$existing_posts = array();
$existing_query = new WP_Query(array(
    'post_type'      => 'tjobs_tyopaikat',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids'
));

if ($existing_query->have_posts()) {
    foreach ($existing_query->posts as $p_id) {
        $meta_link = get_post_meta($p_id, '_tjobs_rss_link', true);
        if (!empty($meta_link)) {
            $existing_posts[tjobs_clean_url($meta_link)] = $p_id;
        }
    }
}

// 4. Kielletyt otsikot (esimerkki: Avoin hakemus, Open application, Öppen ansökan)
$forbidden_titles_raw = isset($opts['forbidden_titles']) ? $opts['forbidden_titles'] : '';
$forbidden_titles = array_filter(array_map('trim', explode("\n", $forbidden_titles_raw)));

// 5. Käydään syötteen itemit läpi
$feed_items = $feed->get_items();
$added   = array();
$removed = array();
$updated = array();
$current_feed_links = array();

// Laura-namespacen URI
$laura_ns = 'https://tapojarvi.rekrytointi.com/#';

// Avoin hakemus -URL:t talteen kielikohtaisesti ennen suodatusta
$open_application_urls = array();

foreach ($feed_items as $item) {
    $title  = $item->get_title();
    $desc   = $item->get_content();
    $link   = $item->get_link();

    // Tarkista kielletyt otsikot
    $skip = false;
    foreach ($forbidden_titles as $bad_title) {
        if (!empty($bad_title) && stripos($title, $bad_title) !== false) {
            $skip = true;
            // Poimi avoin hakemus -lomake URL kielikohtaisesti
            $form_tag_skip = $item->get_item_tags($laura_ns, 'form');
            $form_url_skip = (!empty($form_tag_skip[0]['data'])) ? trim($form_tag_skip[0]['data']) : '';
            $open_application_lang = tjobs_detect_open_application_language($title);
            if (!empty($form_url_skip) && !empty($open_application_lang)) {
                $open_application_urls[$open_application_lang] = tjobs_clean_url($form_url_skip);
            }
            break;
        }
    }
    if ($skip) {
        continue;
    }

    // Laura-namespacen kenttien lukeminen
    $country_tag  = $item->get_item_tags($laura_ns, 'common_job_country');
    $country_name = (!empty($country_tag[0]['data'])) ? trim($country_tag[0]['data']) : '';
    $country_code = tjobs_country_name_to_code($country_name);

    $city_tag = $item->get_item_tags($laura_ns, 'common_job_city');
    $city     = (!empty($city_tag[0]['data'])) ? trim($city_tag[0]['data']) : '';

    $jobtype_tag = $item->get_item_tags($laura_ns, 'common_type');
    $jobtype     = (!empty($jobtype_tag[0]['data'])) ? trim($jobtype_tag[0]['data']) : '';

    $worktime_tag = $item->get_item_tags($laura_ns, 'common_worktime');
    $worktime     = (!empty($worktime_tag[0]['data'])) ? trim($worktime_tag[0]['data']) : '';

    $category_tag = $item->get_item_tags($laura_ns, 'common_category');
    $category     = (!empty($category_tag[0]['data'])) ? trim($category_tag[0]['data']) : '';

    $form_tag = $item->get_item_tags($laura_ns, 'form');
    $form_url = tjobs_clean_url((!empty($form_tag[0]['data'])) ? trim($form_tag[0]['data']) : '');

    $enddate_tag = $item->get_item_tags($laura_ns, 'enddate');
    $enddate     = (!empty($enddate_tag[0]['data'])) ? trim($enddate_tag[0]['data']) : '';

    $laura_desc_tag  = $item->get_item_tags($laura_ns, 'description');
    $laura_description = (!empty($laura_desc_tag[0]['data'])) ? trim($laura_desc_tag[0]['data']) : '';

    // -- Puhdista linkki heti alussa --
    $clean_link = tjobs_clean_url($link);
    $current_feed_links[] = $clean_link;

    // (A) Poista valmiit "Hakuaika päättyy:" / "Application ends:"
    $desc = str_ireplace('Application ends:', '', $desc);
    $desc = str_ireplace('Hakuaika päättyy:', '', $desc);

    // (A2) Poista myös aloituspäivä (jottei fallback nappaa sitä loppuajaksi)
    $desc = preg_replace('/Hakuaika alkaa:\s*\d{1,2}\.\d{1,2}\.\d{4}\s+\d{1,2}:\d{2}/iu', '', $desc);
    $desc = preg_replace('/Application period starts:\s*\d{1,2}\.\d{1,2}\.\d{4}\s+\d{1,2}:\d{2}/iu', '', $desc);

    // 1) Yritä löytää "Hakuaika alkaa: ... - Hakuaika päättyy:" / "Application period starts: ... - Application period ends:"
    $desc = preg_replace(
        '/(Application period starts:[\s\S]*?\-\s*Application period ends:)|(Hakuaika alkaa:[\s\S]*?\-\s*Hakuaika päättyy:)/iu',
        'ENDLABEL:',
        $desc
    );

    // Jos löydettiin "ENDLABEL", otetaan sen jälkeiset merkinnät
    if (preg_match('/ENDLABEL\s*(.+)/i', $desc, $match)) {
        $endDateTime = trim($match[1]);
    } else {
        $endDateTime = '';
    }

    // 2) Fallback: jos endDateTime vielä tyhjä, etsi "dd.mm.yyyy hh:mm"
    if (empty($endDateTime)) {
        if (preg_match('/(\d{1,2}\.\d{1,2}\.\d{4}\s+\d{1,2}:\d{1,2})/u', $desc, $m2)) {
            $endDateTime = trim($m2[1]);
        }
    }

    // Rakennetaan lopullinen excerpt: käytetään laura:enddate jos saatavilla
    if (!empty($enddate)) {
        // Muotoile: "2026-04-30 23:59:00" → "30.04.2026 23:59"
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $enddate);
        if ($dt) {
            $desc_final = $dt->format('d.m.Y H:i');
        } else {
            $desc_final = $endDateTime;
        }
    } else {
        $desc_final = $endDateTime;
    }

    // -- Onko postaus jo olemassa? --
    if (isset($existing_posts[$clean_link])) {
        $post_id = $existing_posts[$clean_link];

        // ===== Vertailu: Onko otsikko tai excerpt muuttunut? =====
        $old_title   = get_the_title($post_id);
        $old_excerpt = get_post_field('post_excerpt', $post_id);

        $new_title   = wp_strip_all_tags($title);
        $new_excerpt = wp_strip_all_tags($desc_final);
        $new_content = wp_kses_post($laura_description);

        // Päivitä vain jos on tarvetta
        if ($old_title !== $new_title || $old_excerpt !== $new_excerpt || get_post_field('post_content', $post_id) !== $new_content) {
            wp_update_post(array(
                'ID'           => $post_id,
                'post_title'   => $new_title,
                'post_excerpt' => $new_excerpt,
                'post_content' => $new_content,
            ));
            $updated[] = $post_id;
        }

        // Päivitä Laura-kentät aina
        update_post_meta($post_id, '_tjobs_country', $country_code);
        update_post_meta($post_id, '_tjobs_city', $city);
        update_post_meta($post_id, '_tjobs_type', $jobtype);
        update_post_meta($post_id, '_tjobs_worktime', $worktime);
        update_post_meta($post_id, '_tjobs_category', $category);
        update_post_meta($post_id, '_tjobs_form_url', tjobs_clean_url($form_url));

    } else {
        // -- Luodaan uusi CPT-postaus --
        $new_post_id = wp_insert_post(array(
            'post_type'    => 'tjobs_tyopaikat',
            'post_status'  => 'publish',
            'post_title'   => wp_strip_all_tags($title),
            'post_excerpt' => wp_strip_all_tags($desc_final),
            'post_content' => wp_kses_post($laura_description),
        ));
        if (!is_wp_error($new_post_id)) {
            update_post_meta($new_post_id, '_tjobs_rss_link', tjobs_clean_url($link));
            update_post_meta($new_post_id, '_tjobs_country', $country_code);
            update_post_meta($new_post_id, '_tjobs_city', $city);
            update_post_meta($new_post_id, '_tjobs_type', $jobtype);
            update_post_meta($new_post_id, '_tjobs_worktime', $worktime);
            update_post_meta($new_post_id, '_tjobs_category', $category);
            update_post_meta($new_post_id, '_tjobs_form_url', tjobs_clean_url($form_url));
            $added[] = $new_post_id;
        }
    }
}

// Tallenna avoin hakemus -URL:t kielikohtaisena optiona
if (!empty($open_application_urls)) {
    update_option('tjobs_open_application_urls', $open_application_urls, false);

    // Säilytetään myös vanha yksittäinen optio taaksepäin yhteensopivuuden vuoksi.
    $legacy_url = '';
    foreach (array('fi', 'en', 'sv', 'it') as $lang_code) {
        if (!empty($open_application_urls[$lang_code])) {
            $legacy_url = $open_application_urls[$lang_code];
            break;
        }
    }
    if (!empty($legacy_url)) {
        update_option('tjobs_open_application_url', $legacy_url, false);
    }
}

// 6. Poistetaan CPT-postaukset, joita ei enää ole syötteessä
foreach ($existing_posts as $existing_link => $existing_post_id) {
    if (!in_array($existing_link, $current_feed_links, true)) {
        wp_trash_post($existing_post_id);
        $removed[] = $existing_post_id;
    }
}

// Päivitä tilastot (aina)
update_option('tjobs_last_sync', time());
update_option('tjobs_last_sync_stats', array(
    'time'    => current_time('mysql'),
    'added'   => count($added),
    'removed' => count($removed),
    'updated' => count($updated),
), false);

// Lokitus: kirjaa vain jos lisäyksiä tai poistoja TAI jos virhe
tjobs_log_import($added, $removed, '', $updated, array());

// Bumpataan mahdollinen HTML-välimuisti (jos käytössä frontissa)
update_option('tjobs_cache_bump', time());

return array(
    'added'   => $added,
    'removed' => $removed,
    'updated' => $updated,
);
}

/**
 * Tallentaa tuontilokin
 *
 * Kirjaa merkinnän vain, jos:
 *  - on lisättyjä TAI poistettuja kohteita TAI
 *  - on virheviesti
 * Muussa tapauksessa päivitetään vain "viimeisin synkka" -tilastot (tehty jo tjobs_sync_feedissä).
 *
 * @param array       $added
 * @param array       $removed
 * @param string      $error
 * @param array|int   $updated
 * @param array       $changes  (ei käytetä enää lokitukseen, jätetään taaksepäin yhteensopivuuden vuoksi)
 */
function tjobs_log_import($added = array(), $removed = array(), $error = '', $updated = array(), $changes = array()) {
    $should_log = (!empty($added) || !empty($removed) || !empty($error));

    // Normalisoi updated count tilastoja varten (jos joku muu kutsuu tätä suoraan)
    $updated_count = is_array($updated) ? count($updated) : intval($updated);

    if (!$should_log) {
        // Ei tehdä varsinaista lokimerkintää
        return;
    }

    $import_log = get_option('tjobs_import_log', array());
    if (!is_array($import_log)) {
        $import_log = array();
    }

    $import_log[] = array(
        'timestamp' => current_time('mysql'),
        'added'     => $added,
        'removed'   => $removed,
        'updated'   => $updated, // pidetään mukana yhteensopivuuden vuoksi
        'error'     => $error,
        'changes'   => array(),  // ei käytössä – pidetään kenttä tyhjänä
    );

    // Rajoitetaan lokin pituus, esim. 200 merkintää
    if (count($import_log) > 200) {
        // Poista vanhin
        array_shift($import_log);
    }

    update_option('tjobs_import_log', $import_log);
}
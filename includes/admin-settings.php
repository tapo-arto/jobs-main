<?php
if (!defined('ABSPATH')) {
    exit; // Suora pääsy estetty
}

// Lisää asetussivu
function tjobs_add_admin_menu() {
    add_menu_page(
        'Jobs V2 Asetukset',
        'Jobs V2',
        'manage_options',
        'tjobs-v2-settings',
        'tjobs_render_settings_page',
        'dashicons-businessman',
        80
    );
}
add_action('admin_menu', 'tjobs_add_admin_menu');

// Hae oletusasetukset
function tjobs_get_settings() {
    $defaults = array(
        'feed_url'               => '',
        'items_count'            => 10,
        'forbidden_titles'       => "Avoin hakemus\nOpen application\nÖppen ansökan",
        'order_by'               => 'date',
        'order'                  => 'DESC',
        'update_frequency'       => 'hourly',
        'link_color'             => '#000000',
        'description_text_color' => '#666666',
        'link_hover_color'       => '#ff0000',
        'default_infopackage'    => 0,
    );
    return wp_parse_args(get_option('tjobs_settings', array()), $defaults);
}

// Asetussivun näyttö ja käsittely
function tjobs_render_settings_page() {
$opts       = tjobs_get_settings();
$import_log = get_option( 'tjobs_import_log', array() );
if ( ! is_array( $import_log ) ) {
    $import_log = array();
}

// 1 = näytä vain lisäykset/poistot/virheet, 0 tai puuttuu = näytä kaikki
$only_changes = isset( $_GET['only_changes'] ) ? (int) $_GET['only_changes'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Pakota tuonti
if ( isset( $_POST['tjobs_force_import'] ) ) {
    check_admin_referer( 'tjobs_settings_nonce' );
    $sync_result = tjobs_sync_feed();
    echo '<div class="notice notice-success is-dismissible"><p>'
        . esc_html__( 'Synkronointi suoritettu!', 'tapojarvijobs' )
        . ' ' . esc_html__( 'Lisätty:', 'tapojarvijobs' ) . ' ' . count( $sync_result['added'] )
        . ', ' . esc_html__( 'Poistettu:', 'tapojarvijobs' ) . ' ' . count( $sync_result['removed'] )
        . ', ' . esc_html__( 'Päivitetty:', 'tapojarvijobs' ) . ' ' . count( $sync_result['updated'] )
        . '</p></div>';
}

// Tallenna asetukset
if ( isset( $_POST['tjobs_save_settings'] ) ) {
    check_admin_referer( 'tjobs_settings_nonce' );
    $new_settings = array(
        'feed_url'               => sanitize_text_field( wp_unslash( $_POST['feed_url'] ) ),
        'items_count'            => absint( $_POST['items_count'] ),
        'forbidden_titles'       => sanitize_textarea_field( wp_unslash( $_POST['forbidden_titles'] ) ),
        'order_by'               => sanitize_text_field( wp_unslash( $_POST['order_by'] ) ),
        'order'                  => sanitize_text_field( wp_unslash( $_POST['order'] ) ),
        'update_frequency'       => sanitize_text_field( wp_unslash( $_POST['update_frequency'] ) ),
        'link_color'             => sanitize_hex_color( wp_unslash( $_POST['link_color'] ) ),
        'description_text_color' => sanitize_hex_color( wp_unslash( $_POST['description_text_color'] ) ),
        'link_hover_color'       => sanitize_hex_color( wp_unslash( $_POST['link_hover_color'] ) ),
        'default_infopackage'    => absint( $_POST['default_infopackage'] ),
    );
    update_option( 'tjobs_settings', $new_settings );
    tjobs_update_cron_schedule( $new_settings['update_frequency'] );
    $opts = $new_settings; // Päivitä muuttuja näkymää varten
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Asetukset tallennettu!', 'tapojarvijobs' ) . '</p></div>';
}

// Suodata loki palvelinpuolella jos only_changes=1
if ( $only_changes === 1 ) {
    $import_log = array_values( array_filter( $import_log, function( $row ) {
        $added   = isset( $row['added'] )   ? ( is_array( $row['added'] )   ? count( $row['added'] )   : (int) $row['added'] )   : 0;
        $removed = isset( $row['removed'] ) ? ( is_array( $row['removed'] ) ? count( $row['removed'] ) : (int) $row['removed'] ) : 0;
        $error   = ! empty( $row['error'] );
        return ( $added > 0 || $removed > 0 || $error );
    } ) );
}

// Sivutus (tehdään suodatuksen jälkeen)
$logs_per_page = 10;
$total_logs    = count( $import_log );
$current_page  = isset( $_GET['log_page'] ) ? max( 1, absint( $_GET['log_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$offset        = ( $current_page - 1 ) * $logs_per_page;
$total_pages   = ( $total_logs > 0 ) ? (int) ceil( $total_logs / $logs_per_page ) : 1;

// Näytetään tuoreimmat ensin
$logs_to_display = array_slice( array_reverse( $import_log ), $offset, $logs_per_page );

// Tab-navigointi
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$tab_base   = admin_url( 'admin.php?page=tjobs-v2-settings' );

// Viimeisin synkronointi
$last_sync       = get_option( 'tjobs_last_sync', 0 );
$last_sync_stats = get_option( 'tjobs_last_sync_stats', array() );
$last_sync_time  = $last_sync
    ? human_time_diff( (int) $last_sync, time() ) . ' ' . esc_html__( 'sitten', 'tapojarvijobs' )
    : esc_html__( 'Ei suoritettu', 'tapojarvijobs' );
$last_added   = isset( $last_sync_stats['added'] )   ? (int) $last_sync_stats['added']   : 0;
$last_removed = isset( $last_sync_stats['removed'] ) ? (int) $last_sync_stats['removed'] : 0;
$last_updated = isset( $last_sync_stats['updated'] ) ? (int) $last_sync_stats['updated'] : 0;
?>
<style>
/* ── TJobs Admin UI ───────────────────────────────────────────── */
.tjobs-wrap { max-width: 900px; }
.tjobs-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 2px solid #e2e8f0;
}
.tjobs-header .dashicons { font-size: 32px; width: 36px; height: 36px; color: #2271b1; }
.tjobs-header h1 { margin: 0; padding: 0; font-size: 1.6rem; font-weight: 700; color: #1e293b; }
.tjobs-header .tjobs-version {
    background: #e0f2fe; color: #0369a1;
    font-size: 11px; font-weight: 600;
    padding: 2px 8px; border-radius: 20px;
    letter-spacing: .4px; text-transform: uppercase;
}
/* Status bar */
.tjobs-status-bar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 22px; }
.tjobs-stat-chip {
    display: inline-flex; align-items: center; gap: 6px;
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 9999px; padding: 5px 14px;
    font-size: 12.5px; color: #475569; font-weight: 500;
}
.tjobs-stat-chip .dashicons { font-size: 14px; width: 14px; height: 14px; color: #94a3b8; }
.tjobs-stat-chip.is-ok   .dashicons { color: #22c55e; }
.tjobs-stat-chip.is-warn .dashicons { color: #f59e0b; }
/* Tabs */
.tjobs-tabs { display: flex; gap: 4px; margin-bottom: 0; border-bottom: 2px solid #e2e8f0; }
.tjobs-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 18px; font-size: 13px; font-weight: 600;
    color: #64748b; text-decoration: none;
    border-radius: 8px 8px 0 0;
    border: 2px solid transparent; border-bottom: none;
    margin-bottom: -2px;
    transition: color .15s, background .15s;
}
.tjobs-tab:hover { color: #2271b1; background: #f1f5f9; }
.tjobs-tab.is-active { color: #2271b1; background: #fff; border-color: #e2e8f0; border-bottom-color: #fff; }
.tjobs-tab .dashicons { font-size: 16px; width: 16px; height: 16px; }
.tjobs-tab .tjobs-badge { margin-left: 4px; }
/* Panel */
.tjobs-panel {
    background: #fff; border: 2px solid #e2e8f0;
    border-top: none; border-radius: 0 0 12px 12px;
    padding: 28px 28px 24px;
}
/* Cards */
.tjobs-card {
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 10px; padding: 20px 22px; margin-bottom: 18px;
}
.tjobs-card h3 {
    margin: 0 0 16px; font-size: 12px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px; color: #64748b;
}
/* Field rows */
.tjobs-field-row {
    display: grid; grid-template-columns: 230px 1fr;
    gap: 8px 16px; align-items: start; margin-bottom: 14px;
}
.tjobs-field-row:last-child { margin-bottom: 0; }
.tjobs-field-label { font-size: 13px; font-weight: 600; color: #374151; padding-top: 7px; }
.tjobs-field-label .desc { display: block; font-weight: 400; color: #94a3b8; font-size: 11px; margin-top: 2px; }
.tjobs-field-row input[type="text"],
.tjobs-field-row input[type="url"],
.tjobs-field-row input[type="number"],
.tjobs-field-row select,
.tjobs-field-row textarea {
    width: 100%; max-width: 460px;
    border: 1.5px solid #cbd5e1; border-radius: 8px;
    padding: 7px 11px; font-size: 13px; color: #1e293b; background: #fff;
    transition: border-color .15s, box-shadow .15s;
    box-shadow: none;
}
.tjobs-field-row input:focus,
.tjobs-field-row select:focus,
.tjobs-field-row textarea:focus {
    border-color: #2271b1; box-shadow: 0 0 0 3px rgba(34,113,177,.12); outline: none;
}
/* Color row */
.tjobs-color-row { display: flex; align-items: center; gap: 10px; }
.tjobs-color-row input[type="color"] {
    width: 40px; height: 34px; padding: 2px 3px;
    border-radius: 6px; border: 1.5px solid #cbd5e1; cursor: pointer;
}
.tjobs-color-row input[type="text"] { max-width: 110px; }
/* Chips */
.tjobs-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 2px; }
.tjobs-chip {
    display: inline-flex; align-items: center; gap: 5px;
    background: #eff6ff; color: #1d4ed8;
    border: 1.5px solid #bfdbfe; border-radius: 9999px;
    padding: 5px 14px; font-size: 12.5px; font-weight: 600;
    cursor: pointer; transition: background .12s, border-color .12s;
    text-decoration: none;
}
.tjobs-chip:hover { background: #dbeafe; border-color: #93c5fd; color: #1d4ed8; }
.tjobs-chip.is-selected { background: #2271b1; color: #fff; border-color: #2271b1; }
/* Action bar */
.tjobs-actions {
    display: flex; align-items: center; gap: 10px;
    flex-wrap: wrap; padding-top: 18px;
    border-top: 1px solid #e2e8f0; margin-top: 6px;
}
.tjobs-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px; font-size: 13px; font-weight: 600;
    border-radius: 8px; cursor: pointer; border: none;
    transition: background .12s, transform .1s;
}
.tjobs-btn:active { transform: scale(.97); }
.tjobs-btn-primary { background: #2271b1; color: #fff; }
.tjobs-btn-primary:hover { background: #1a5f9a; color: #fff; }
.tjobs-btn-secondary { background: #f1f5f9; color: #374151; border: 1.5px solid #cbd5e1; }
.tjobs-btn-secondary:hover { background: #e2e8f0; }
/* Log table */
.tjobs-log-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 4px; }
.tjobs-log-table th {
    text-align: left; padding: 10px 12px;
    background: #f1f5f9; border-bottom: 2px solid #e2e8f0;
    font-weight: 700; color: #475569; font-size: 11px;
    text-transform: uppercase; letter-spacing: .4px;
}
.tjobs-log-table td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.tjobs-log-table tr:last-child td { border-bottom: none; }
.tjobs-log-table tr.is-change td { background: #fffbeb; }
.tjobs-log-table tr.is-err td { background: #fef2f2; }
.tjobs-log-table tbody tr:hover td { background: #f8fafc; }
/* Badges */
.tjobs-badge {
    display: inline-block; padding: 2px 8px;
    border-radius: 9999px; font-size: 11px; font-weight: 700;
}
.tjobs-badge-green  { background: #dcfce7; color: #15803d; }
.tjobs-badge-red    { background: #fee2e2; color: #b91c1c; }
.tjobs-badge-blue   { background: #dbeafe; color: #1d4ed8; }
.tjobs-badge-gray   { background: #f1f5f9; color: #64748b; }
/* Shortcode blocks */
.tjobs-code-block {
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 10px; padding: 18px 20px; margin-bottom: 14px;
}
.tjobs-code-block h4 { margin: 0 0 6px; font-size: 13px; font-weight: 700; color: #1e293b; }
.tjobs-code-block p { margin: 0 0 8px; color: #64748b; font-size: 13px; }
.tjobs-code-block code {
    display: inline-block; background: #1e293b; color: #7dd3fc;
    padding: 3px 10px; border-radius: 5px; font-size: 12px; margin: 2px 0;
}
/* Filter bar */
.tjobs-filter-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
.tjobs-filter-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
/* Inline select pair */
.tjobs-select-pair { display: flex; gap: 8px; flex-wrap: wrap; }
.tjobs-select-pair select { max-width: 190px; }
</style>

<div class="wrap tjobs-wrap">

    <!-- Header -->
    <div class="tjobs-header">
        <span class="dashicons dashicons-businessman"></span>
        <h1>Jobs V2</h1>
        <span class="tjobs-version">v4.0 V2</span>
    </div>

    <!-- Status chips -->
    <div class="tjobs-status-bar">
        <?php
        $feed_ok = ! empty( $opts['feed_url'] );
        $sync_ok = ! empty( $last_sync ) && ( time() - (int) $last_sync ) < 86400;
        ?>
        <span class="tjobs-stat-chip <?php echo $feed_ok ? 'is-ok' : 'is-warn'; ?>">
            <span class="dashicons <?php echo $feed_ok ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
            <?php echo $feed_ok ? esc_html__( 'Syöte asetettu', 'tapojarvijobs' ) : esc_html__( 'Syöte puuttuu', 'tapojarvijobs' ); ?>
        </span>
        <span class="tjobs-stat-chip <?php echo $sync_ok ? 'is-ok' : 'is-warn'; ?>">
            <span class="dashicons dashicons-update"></span>
            <?php echo esc_html( $last_sync_time ); ?>
        </span>
        <?php if ( $last_sync ) : ?>
        <span class="tjobs-stat-chip is-ok">
            <span class="dashicons dashicons-plus-alt2"></span>
            +<?php echo esc_html( $last_added ); ?>
        </span>
        <span class="tjobs-stat-chip">
            <span class="dashicons dashicons-trash"></span>
            -<?php echo esc_html( $last_removed ); ?>
        </span>
        <span class="tjobs-stat-chip">
            <span class="dashicons dashicons-edit"></span>
            ~<?php echo esc_html( $last_updated ); ?>
        </span>
        <?php endif; ?>
    </div>

    <!-- Tab nav -->
    <div class="tjobs-tabs">
        <a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $tab_base ) ); ?>"
           class="tjobs-tab <?php echo $active_tab === 'settings' ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e( 'Asetukset', 'tapojarvijobs' ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'tab', 'log', $tab_base ) ); ?>"
           class="tjobs-tab <?php echo $active_tab === 'log' ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e( 'Tuontiloki', 'tapojarvijobs' ); ?>
            <?php if ( $total_logs > 0 ) : ?>
                <span class="tjobs-badge tjobs-badge-gray"><?php echo esc_html( $total_logs ); ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'tab', 'help', $tab_base ) ); ?>"
           class="tjobs-tab <?php echo $active_tab === 'help' ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-editor-help"></span>
            <?php esc_html_e( 'Ohjeet', 'tapojarvijobs' ); ?>
        </a>
    </div>

    <div class="tjobs-panel">

    <?php if ( $active_tab === 'settings' ) : ?>
    <!-- ═════════ TAB: ASETUKSET ═════════ -->
    <form method="post">
        <?php wp_nonce_field( 'tjobs_settings_nonce' ); ?>

        <!-- RSS-syöte -->
        <div class="tjobs-card">
            <h3><?php esc_html_e( 'RSS-syöte', 'tapojarvijobs' ); ?></h3>
            <div class="tjobs-field-row">
                <label class="tjobs-field-label" for="feed_url">
                    <?php esc_html_e( 'Syötteen URL', 'tapojarvijobs' ); ?>
                    <span class="desc"><?php esc_html_e( 'Laura / muu RSS-osoite', 'tapojarvijobs' ); ?></span>
                </label>
                <input type="url" id="feed_url" name="feed_url"
                       value="<?php echo esc_attr( $opts['feed_url'] ); ?>"
                       placeholder="https://" style="max-width:100%;">
            </div>
            <div class="tjobs-field-row">
                <label class="tjobs-field-label" for="forbidden_titles">
                    <?php esc_html_e( 'Kielletyt otsikot', 'tapojarvijobs' ); ?>
                    <span class="desc"><?php esc_html_e( 'Yksi per rivi – esim. avoimet hakemukset', 'tapojarvijobs' ); ?></span>
                </label>
                <textarea id="forbidden_titles" name="forbidden_titles" rows="3"><?php echo esc_textarea( $opts['forbidden_titles'] ); ?></textarea>
            </div>
        </div>

        <!-- Listausasetukset -->
        <div class="tjobs-card">
            <h3><?php esc_html_e( 'Listaus', 'tapojarvijobs' ); ?></h3>
            <div class="tjobs-field-row">
                <label class="tjobs-field-label" for="items_count">
                    <?php esc_html_e( 'Töiden enimmäismäärä', 'tapojarvijobs' ); ?>
                </label>
                <input type="number" id="items_count" name="items_count"
                       value="<?php echo esc_attr( $opts['items_count'] ); ?>"
                       min="1" max="200" style="max-width:90px;">
            </div>
            <div class="tjobs-field-row">
                <label class="tjobs-field-label"><?php esc_html_e( 'Järjestys', 'tapojarvijobs' ); ?></label>
                <div class="tjobs-select-pair">
                    <select id="order_by" name="order_by">
                        <option value="date"  <?php selected( $opts['order_by'], 'date' ); ?>><?php esc_html_e( 'Päivämäärä', 'tapojarvijobs' ); ?></option>
                        <option value="title" <?php selected( $opts['order_by'], 'title' ); ?>><?php esc_html_e( 'Aakkosjärjestys', 'tapojarvijobs' ); ?></option>
                    </select>
                    <select id="order" name="order">
                        <option value="DESC" <?php selected( $opts['order'], 'DESC' ); ?>><?php esc_html_e( 'Uusin ensin', 'tapojarvijobs' ); ?></option>
                        <option value="ASC"  <?php selected( $opts['order'], 'ASC' ); ?>><?php esc_html_e( 'Vanhin ensin', 'tapojarvijobs' ); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Automaattinen päivitys -->
        <div class="tjobs-card">
            <h3><?php esc_html_e( 'Automaattinen päivitys', 'tapojarvijobs' ); ?></h3>
            <div class="tjobs-field-row">
                <label class="tjobs-field-label">
                    <?php esc_html_e( 'Päivitystiheys', 'tapojarvijobs' ); ?>
                </label>
                <div class="tjobs-chips" id="tjobs-freq-chips">
                    <?php
                    $freqs = array(
                        'hourly'  => __( 'Tunnin välein', 'tapojarvijobs' ),
                        '3_hours' => __( '3 tunnin välein', 'tapojarvijobs' ),
                        'daily'   => __( 'Päivittäin', 'tapojarvijobs' ),
                    );
                    foreach ( $freqs as $val => $label ) :
                        $sel = $opts['update_frequency'] === $val;
                    ?>
                    <button type="button"
                            class="tjobs-chip <?php echo $sel ? 'is-selected' : ''; ?>"
                            data-freq="<?php echo esc_attr( $val ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </button>
                    <?php endforeach; ?>
                    <input type="hidden" id="update_frequency" name="update_frequency"
                           value="<?php echo esc_attr( $opts['update_frequency'] ); ?>">
                </div>
            </div>
        </div>

        <!-- Värit -->
        <div class="tjobs-card">
            <h3><?php esc_html_e( 'Värit (listaus-shortcode)', 'tapojarvijobs' ); ?></h3>
            <?php
            $color_fields = array(
                'link_color'             => __( 'Linkin väri', 'tapojarvijobs' ),
                'description_text_color' => __( 'Kuvauksen väri', 'tapojarvijobs' ),
                'link_hover_color'       => __( 'Hover-väri', 'tapojarvijobs' ),
            );
            foreach ( $color_fields as $ckey => $clabel ) : ?>
            <div class="tjobs-field-row">
                <label class="tjobs-field-label" for="<?php echo esc_attr( $ckey ); ?>">
                    <?php echo esc_html( $clabel ); ?>
                </label>
                <div class="tjobs-color-row">
                    <input type="color" id="<?php echo esc_attr( $ckey ); ?>_picker"
                           value="<?php echo esc_attr( $opts[ $ckey ] ); ?>"
                           data-target="<?php echo esc_attr( $ckey ); ?>">
                    <input type="text" id="<?php echo esc_attr( $ckey ); ?>"
                           name="<?php echo esc_attr( $ckey ); ?>"
                           value="<?php echo esc_attr( $opts[ $ckey ] ); ?>"
                           maxlength="7" placeholder="#000000">
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Infopaketti -->
        <div class="tjobs-card">
            <h3><?php esc_html_e( 'Infopaketti', 'tapojarvijobs' ); ?></h3>
            <div class="tjobs-field-row">
                <label class="tjobs-field-label" for="default_infopackage">
                    <?php esc_html_e( 'Oletusinfopaketti', 'tapojarvijobs' ); ?>
                    <span class="desc"><?php esc_html_e( 'Käytetään kun automaattinen kohdistus ei löydä sopivaa', 'tapojarvijobs' ); ?></span>
                </label>
                <?php
                $infopackages = get_posts( array(
                    'post_type'      => 'tjobs_infopackage',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ) );
                ?>
                <select id="default_infopackage" name="default_infopackage" style="max-width:320px;">
                    <option value="0"><?php esc_html_e( '— Ei oletusinfopakettia —', 'tapojarvijobs' ); ?></option>
                    <?php foreach ( $infopackages as $pkg ) : ?>
                        <option value="<?php echo esc_attr( $pkg->ID ); ?>"
                                <?php selected( $opts['default_infopackage'], $pkg->ID ); ?>>
                            <?php echo esc_html( $pkg->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Action bar -->
        <div class="tjobs-actions">
            <button type="submit" name="tjobs_save_settings" class="tjobs-btn tjobs-btn-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'Tallenna asetukset', 'tapojarvijobs' ); ?>
            </button>
            <button type="submit" name="tjobs_force_import" class="tjobs-btn tjobs-btn-secondary">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e( 'Pakota tuonti nyt', 'tapojarvijobs' ); ?>
            </button>
        </div>
    </form>

    <?php elseif ( $active_tab === 'log' ) : ?>
    <!-- ═════════ TAB: TUONTILOKI ═════════ -->
    <div class="tjobs-filter-bar">
        <span class="tjobs-filter-label"><?php esc_html_e( 'Näytä:', 'tapojarvijobs' ); ?></span>
        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'log', 'only_changes' => '0' ), $tab_base ) ); ?>"
           class="tjobs-chip <?php echo $only_changes !== 1 ? 'is-selected' : ''; ?>">
            <?php esc_html_e( 'Kaikki', 'tapojarvijobs' ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'log', 'only_changes' => '1' ), $tab_base ) ); ?>"
           class="tjobs-chip <?php echo $only_changes === 1 ? 'is-selected' : ''; ?>">
            <?php esc_html_e( 'Vain muutokset', 'tapojarvijobs' ); ?>
        </a>
    </div>

    <?php if ( ! empty( $logs_to_display ) ) : ?>
    <table class="tjobs-log-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Aika', 'tapojarvijobs' ); ?></th>
                <th><?php esc_html_e( 'Lisätty', 'tapojarvijobs' ); ?></th>
                <th><?php esc_html_e( 'Poistettu', 'tapojarvijobs' ); ?></th>
                <th><?php esc_html_e( 'Päivitetty', 'tapojarvijobs' ); ?></th>
                <th><?php esc_html_e( 'Tila', 'tapojarvijobs' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $logs_to_display as $log ) :
            $addedCount   = ! empty( $log['added'] )   ? ( is_array( $log['added'] )   ? count( $log['added'] )   : (int) $log['added'] )   : 0;
            $removedCount = ! empty( $log['removed'] ) ? ( is_array( $log['removed'] ) ? count( $log['removed'] ) : (int) $log['removed'] ) : 0;
            $updatedCount = ! empty( $log['updated'] ) ? ( is_array( $log['updated'] ) ? count( $log['updated'] ) : (int) $log['updated'] ) : 0;
            $hasError     = ! empty( $log['error'] );
            $rowClass     = $hasError ? 'is-err' : ( ( $addedCount > 0 || $removedCount > 0 ) ? 'is-change' : '' );
        ?>
            <tr class="<?php echo esc_attr( $rowClass ); ?>">
                <td style="white-space:nowrap;color:#64748b;font-size:12px;"><?php echo esc_html( $log['timestamp'] ); ?></td>
                <td>
                    <?php if ( $addedCount ) : ?>
                        <span class="tjobs-badge tjobs-badge-green">+<?php echo esc_html( $addedCount ); ?></span>
                    <?php else : ?>
                        <span style="color:#cbd5e1;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $removedCount ) : ?>
                        <span class="tjobs-badge tjobs-badge-red">-<?php echo esc_html( $removedCount ); ?></span>
                    <?php else : ?>
                        <span style="color:#cbd5e1;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $updatedCount ) : ?>
                        <span class="tjobs-badge tjobs-badge-blue">~<?php echo esc_html( $updatedCount ); ?></span>
                    <?php else : ?>
                        <span style="color:#cbd5e1;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $hasError ) : ?>
                        <span class="tjobs-badge tjobs-badge-red"
                              title="<?php echo esc_attr( $log['error'] ); ?>">
                            <?php esc_html_e( 'Virhe', 'tapojarvijobs' ); ?>
                        </span>
                    <?php else : ?>
                        <span class="tjobs-badge tjobs-badge-gray"><?php esc_html_e( 'OK', 'tapojarvijobs' ); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) : ?>
    <div class="tablenav" style="margin-top:12px;">
        <div class="tablenav-pages">
            <?php
            $base_url = add_query_arg( 'tab', 'log', $tab_base );
            if ( $only_changes === 1 ) {
                $base_url = add_query_arg( 'only_changes', '1', $base_url );
            }
            echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                'base'      => add_query_arg( 'log_page', '%#%', $base_url ),
                'format'    => '',
                'current'   => max( 1, $current_page ),
                'total'     => max( 1, $total_pages ),
                'prev_text' => '&laquo; ' . esc_html__( 'Edellinen', 'tapojarvijobs' ),
                'next_text' => esc_html__( 'Seuraava', 'tapojarvijobs' ) . ' &raquo;',
            ) );
            ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <div style="text-align:center;padding:40px 20px;color:#94a3b8;">
        <span class="dashicons dashicons-list-view" style="font-size:40px;width:40px;height:40px;margin-bottom:8px;display:block;"></span>
        <?php esc_html_e( 'Ei tuontilokeja vielä.', 'tapojarvijobs' ); ?>
    </div>
    <?php endif; ?>

    <?php elseif ( $active_tab === 'help' ) : ?>
    <!-- ═════════ TAB: OHJEET ═════════ -->
    <h3 style="margin-top:0;font-size:14px;color:#1e293b;"><?php esc_html_e( 'Lyhytkoodit', 'tapojarvijobs' ); ?></h3>

    <div class="tjobs-code-block">
        <h4>[tjobs_list]</h4>
        <p><?php esc_html_e( 'Yksinkertainen listaus kaikista avoimista työpaikoista. Käyttää asetussivun väri- ja järjestysasetuksia.', 'tapojarvijobs' ); ?></p>
        <code>[tjobs_list]</code><br>
        <code>[tjobs_list import="yes"]</code>
        <span style="font-size:11px;color:#94a3b8;margin-left:6px;"><?php esc_html_e( '← pakottaa RSS-synkronoinnin ennen listausta', 'tapojarvijobs' ); ?></span>
    </div>

    <div class="tjobs-code-block">
        <h4>[tjobs_by_country]</h4>
        <p><?php esc_html_e( 'Maakohtainen ryhmittely (Suomi, Ruotsi, Kreikka, Italia) moderneilla korteilla. Avoin hakemus -banneri näytetään automaattisesti lopussa.', 'tapojarvijobs' ); ?></p>
        <code>[tjobs_by_country]</code>
        <code>[tjobs_by_country theme="dark"]</code>
        <code>[tjobs_by_country theme="light"]</code>
    </div>

    <h3 style="font-size:14px;color:#1e293b;margin-top:24px;"><?php esc_html_e( 'REST API', 'tapojarvijobs' ); ?></h3>
    <div class="tjobs-code-block">
        <p><code>GET <?php echo esc_html( rest_url( 'tjobs/v1/jobs' ) ); ?></code></p>
        <p style="font-size:11px;color:#94a3b8;margin:2px 0 10px;"><?php esc_html_e( 'Parametrit: page, per_page, search, lang, country', 'tapojarvijobs' ); ?></p>
        <p><code>GET <?php echo esc_html( rest_url( 'tjobs/v1/job-info/{id}' ) ); ?></code></p>
        <p style="font-size:11px;color:#94a3b8;margin:2px 0 0;"><?php esc_html_e( 'Palauttaa työpaikan + infopaketitiedot frontille (modal)', 'tapojarvijobs' ); ?></p>
    </div>

    <h3 style="font-size:14px;color:#1e293b;margin-top:24px;"><?php esc_html_e( 'WP-CLI', 'tapojarvijobs' ); ?></h3>
    <div class="tjobs-code-block">
        <code>wp tjobs sync</code>
        <p style="margin-top:8px;font-size:12px;color:#64748b;"><?php esc_html_e( 'Ajaa RSS-syötteen synkronoinnin manuaalisesti komentoriviltä.', 'tapojarvijobs' ); ?></p>
    </div>

    <?php endif; ?>

    </div><!-- .tjobs-panel -->
</div><!-- .wrap.tjobs-wrap -->

<script>
(function(){
    // Frequency chip selector
    var chips  = document.querySelectorAll('#tjobs-freq-chips .tjobs-chip');
    var hidden = document.getElementById('update_frequency');
    if (chips.length && hidden) {
        chips.forEach(function(chip){
            chip.addEventListener('click', function(){
                chips.forEach(function(c){ c.classList.remove('is-selected'); });
                chip.classList.add('is-selected');
                hidden.value = chip.dataset.freq || '';
            });
        });
    }

    // Color picker ↔ text input sync
    document.querySelectorAll('input[type="color"][data-target]').forEach(function(picker){
        var textInput = document.getElementById(picker.dataset.target);
        if (!textInput) { return; }
        picker.addEventListener('input', function(){ textInput.value = picker.value; });
        textInput.addEventListener('input', function(){
            if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(textInput.value)) {
                picker.value = textInput.value;
            }
        });
    });
})();
</script>
<?php
}

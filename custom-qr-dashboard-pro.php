<?php
/**
 * Plugin Name: Custom QR Dashboard Pro
 * Plugin URI: https://yourwebsite.com/
 * Description: Napredni sistem za upravljanje inventarom sa QR kodovima
 * Version: 1.4.3
 * Author: Tvoj Plugin
 * Text Domain: custom-qr-dashboard
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// Start session if not started
if (!session_id() && !headers_sent()) {
    session_start();
}

// ================================================
// SEKCIJA: KONSTANTE I INICIJALIZACIJA
// ================================================

defined('ABSPATH') or die('No direct access!');

// Plugin verzije
define('CQD_VERSION', '1.4.3');
define('CQD_DB_VERSION', '1.0');
define('CQD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CQD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CQD_PLUGIN_BASENAME', plugin_basename(__FILE__));

// System statusi (globalni, ne mogu se menjati)
function cqd_get_system_statuses() {
    return [
        'active' => '✅ Aktivno',
        'inactive' => '⏸️ Neaktivno',
        'maintenance' => '🔧 U servisu',
        'lost' => '🔍 Izgubljeno',
        'sold' => '💰 Prodato',
        'reserved' => '📌 Rezervisano'
    ];
}

// ================================================
// SEKCIJA: HELPER FUNKCIJE
// ================================================

function cqd_get_total_items_count() {
    $total = 0;
    $users = get_users(['role__in' => ['subscriber', 'inventory_user']]);
    
    foreach ($users as $user) {
        $items = get_user_meta($user->ID, 'cqd_stavke', true);
        if (is_array($items)) {
            $total += count($items);
        }
    }
    
    return $total;
}

function cqd_get_total_qr_count() {
    return cqd_get_total_items_count();
}

function cqd_log_activity($type, $description, $item_id = null) {
    error_log('CQD LOG START: ' . $description);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cqd_activity_log';
    
    // EKSTRA PROVERA - detaljnija
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    error_log('CQD TABLE CHECK: ' . ($table_exists ? 'EXISTS' : 'MISSING'));
    
    if (!$table_exists) {
        error_log('CQD ERROR: Table does not exist! Creating...');
        // Kreirajte odmah
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            activity_type varchar(50) NOT NULL,
            description text NOT NULL,
            item_id varchar(100) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY activity_type (activity_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Proveri ponovo
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        error_log('CQD TABLE AFTER CREATE: ' . ($table_exists ? 'CREATED' : 'FAILED'));
    }
    
    $user_id = is_user_logged_in() ? get_current_user_id() : 0;
    
    try {
        $result = $wpdb->insert($table_name, [
            'user_id' => $user_id,
            'activity_type' => sanitize_text_field($type),
            'description' => sanitize_text_field($description),
            'item_id' => $item_id ? sanitize_text_field($item_id) : null,
            'ip_address' => cqd_get_user_ip(),
            'created_at' => current_time('mysql')
        ]);
        
        if ($result === false) {
            error_log('CQD ERROR: Failed to log activity. SQL Error: ' . $wpdb->last_error);
            error_log('CQD ERROR: Query was: ' . $wpdb->last_query);
            return false;
        }
        
        error_log('CQD LOG SUCCESS: ' . $description);
        return true;
    } catch (Exception $e) {
        error_log('CQD EXCEPTION: ' . $e->getMessage());
        return false;
    }
}

function cqd_get_activity_icon($activity_type) {
    $icons = [
        'system' => 'dashicons-admin-tools',
        'user' => 'dashicons-admin-users',
        'item' => 'dashicons-portfolio',
        'settings' => 'dashicons-admin-settings',
        'backup' => 'dashicons-backup',
        'login' => 'dashicons-lock',
        'register' => 'dashicons-plus',
        'test' => 'dashicons-testimonial'
    ];
    
    return $icons[$activity_type] ?? 'dashicons-info';
}

// ================================================
// DODATNI HOOK-OVI ZA LOGOVANJE AKTIVNOSTI
// ================================================

// Logovanje pri registraciji korisnika
function cqd_log_user_registration($user_id) {
    $user = get_user_by('id', $user_id);
    if ($user) {
        cqd_log_activity('register', 'New user registered: ' . $user->user_login, 'user_' . $user_id);
    }
}
add_action('user_register', 'cqd_log_user_registration');

// Logovanje pri prijavi
function cqd_log_user_login($user_login, $user) {
    cqd_log_activity('login', 'User logged in: ' . $user_login, 'user_' . $user->ID);
}
add_action('wp_login', 'cqd_log_user_login', 10, 2);

// ================================================
// POMOĆNA FUNKCIJA: GET USER IP (VERZIJA 1.4.3)
// ================================================

function cqd_get_user_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
            $ip = trim(current(explode(',', $_SERVER[$key])));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return '127.0.0.1'; // Default localhost
}

// ================================================
// SEKCIJA: PROVERA I UPGRADE VERZIJE
// ================================================

function cqd_check_version() {
    $installed_version = get_option('cqd_version', '0');
    
    if (version_compare($installed_version, CQD_VERSION, '<')) {
        // Ažuriraj verziju
        update_option('cqd_version', CQD_VERSION);
        
        // Pokreni upgrade skripte ako su potrebne
        if (version_compare($installed_version, '1.4', '<')) {
            cqd_upgrade_to_1_4_3();
        }
    }
}
add_action('admin_init', 'cqd_check_version');

function cqd_upgrade_to_1_4_3() {
    global $wpdb;
    
    // 1. Kreiraj tabelu za istoriju aktivnosti
    $table_name = $wpdb->prefix . 'cqd_activity_log';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        activity_type varchar(50) NOT NULL,
        description text NOT NULL,
        item_id varchar(100) DEFAULT NULL,
        ip_address varchar(45) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY activity_type (activity_type)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // 2. Dodaj admin capability za upload_files svim rolama
    $roles = ['subscriber', 'inventory_user'];
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->add_cap('upload_files');
        }
    }
    
    // 3. Dodaj default opcije
    add_option('cqd_backup_schedule', 'weekly');
    add_option('cqd_qr_quality', 'high');
    add_option('cqd_items_per_page', 20);
    
    // 4. Loguj upgrade
    cqd_log_activity('system', 'Plugin upgraded to version 1.4.3', 'system');
}

// ================================================
// SEKCIJA: PROVERA TABELE AKTIVNOSTI
// ================================================

function cqd_check_activity_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cqd_activity_log';
    
    // Proveri da li tabela postoji
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        // Kreiraj tabelu odmah
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            activity_type varchar(50) NOT NULL,
            description text NOT NULL,
            item_id varchar(100) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY activity_type (activity_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Dodaj neke test podatke SAMO ako je admin
        if (is_admin()) {
            $users = get_users(['role__in' => ['subscriber', 'inventory_user'], 'number' => 3]);
            foreach ($users as $user) {
                $wpdb->insert($table_name, [
                    'user_id' => $user->ID,
                    'activity_type' => 'user',
                    'description' => 'Korisnik registrovan',
                    'item_id' => 'user_' . $user->ID,
                    'created_at' => $user->user_registered
                ]);
            }
        }
    }
}
add_action('init', 'cqd_check_activity_table');

// ================================================
// SEKCIJA: WORDPRESS ADMIN MENU
// ================================================

function cqd_add_admin_menu() {
    // Glavni menu
    add_menu_page(
        'QR Dashboard Pro',
        'QR Dashboard',
        'manage_options',
        'cqd-dashboard',
        'cqd_admin_dashboard_page',
        'dashicons-qrcode',
        30
    );
    
    // Podmenu stavke
    add_submenu_page(
        'cqd-dashboard',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'cqd-dashboard',
        'cqd_admin_dashboard_page'
    );
    
    add_submenu_page(
        'cqd-dashboard',
        'Svi Korisnici',
        'Korisnici',
        'manage_options',
        'cqd-users',
        'cqd_admin_users_page'
    );
    
    add_submenu_page(
        'cqd-dashboard',
        'Sve Stavke',
        'Stavke Korisnika',
        'manage_options',
        'cqd-all-items',
        'cqd_admin_all_items_page'
    );
    
    add_submenu_page(
        'cqd-dashboard',
        'Import/Export',
        'Backup',
        'manage_options',
        'cqd-backup',
        'cqd_admin_backup_page'
    );
    
    add_submenu_page(
        'cqd-dashboard',
        'Podešavanja',
        'Podešavanja',
        'manage_options',
        'cqd-settings',
        'cqd_admin_settings_page'
    );
}
add_action('admin_menu', 'cqd_add_admin_menu');

// ================================================
// SEKCIJA: ADMIN DASHBOARD PAGE (VERZIJA 1.4.3)
// ================================================

function cqd_admin_dashboard_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Nemate dozvolu za pristup ovoj strani.'));
    }
    
    ?>
    <div class="wrap cqd-admin-wrap">
        <h1 class="wp-heading-inline">📊 QR Dashboard Pro - Admin Panel</h1>
        <hr class="wp-header-end">
        
        <div class="cqd-admin-stats">
            <div class="cqd-stat-box">
                <h3>Ukupno korisnika</h3>
                <p class="stat-number"><?php echo count(get_users(['role__in' => ['subscriber', 'inventory_user']])); ?></p>
            </div>
            <div class="cqd-stat-box">
                <h3>Ukupno stavki</h3>
                <p class="stat-number"><?php echo cqd_get_total_items_count(); ?></p>
            </div>
            <div class="cqd-stat-box">
                <h3>QR kodova generisano</h3>
                <p class="stat-number"><?php echo cqd_get_total_qr_count(); ?></p>
            </div>
            <div class="cqd-stat-box">
                <h3>Verzija sistema</h3>
                <p class="stat-number"><?php echo CQD_VERSION; ?></p>
            </div>
        </div>
        
        <div class="cqd-admin-actions">
            <a href="<?php echo admin_url('admin.php?page=cqd-users'); ?>" class="button button-primary">
                👥 Upravljaj korisnicima
            </a>
            <a href="<?php echo admin_url('admin.php?page=cqd-all-items'); ?>" class="button">
                📋 Pregled svih stavki
            </a>
            <a href="<?php echo admin_url('admin.php?page=cqd-backup'); ?>" class="button">
                💾 Backup podataka
            </a>
            <a href="<?php echo admin_url('admin.php?page=cqd-settings'); ?>" class="button">
                ⚙️ Podešavanja
            </a>
        </div>
        
        <div class="cqd-recent-activity">
            <h2>Nedavna aktivnost</h2>
            <?php cqd_display_recent_activity(); ?>
            
            <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #ddd;">
                <h3>📈 Detaljna istorija aktivnosti</h3>
                <?php cqd_display_advanced_activity_log(); ?>
            </div>
        </div>
        
        <div class="cqd-export-actions" style="margin-top: 20px;">
            <a href="<?php echo admin_url('admin.php?page=cqd-dashboard&export_activities=csv'); ?>" class="button">📥 Export aktivnosti (CSV)</a>
            <a href="<?php echo admin_url('admin.php?page=cqd-dashboard&export_activities=json'); ?>" class="button">📥 Export aktivnosti (JSON)</a>
        </div>
        
        <div class="cqd-quick-links">
            <h2>Brzi linkovi</h2>
            <div class="cqd-quick-links-grid">
                <a href="<?php echo site_url('/register'); ?>" target="_blank" class="cqd-quick-link">
                    <span class="dashicons dashicons-plus"></span>
                    <span>Stranica za registraciju</span>
                </a>
                <a href="<?php echo site_url('/login'); ?>" target="_blank" class="cqd-quick-link">
                    <span class="dashicons dashicons-lock"></span>
                    <span>Stranica za prijavu</span>
                </a>
                <a href="<?php echo site_url('/moj-dashboard'); ?>" target="_blank" class="cqd-quick-link">
                    <span class="dashicons dashicons-dashboard"></span>
                    <span>Korisnički dashboard</span>
                </a>
                <a href="<?php echo site_url('/stavka'); ?>" target="_blank" class="cqd-quick-link">
                    <span class="dashicons dashicons-qrcode"></span>
                    <span>Primer QR strane</span>
                </a>
            </div>
        </div>
    </div>
    <?php
}

function cqd_admin_users_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Nemate dozvolu za pristup ovoj strani.'));
    }
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">👥 Upravljanje korisnicima</h1>
        <hr class="wp-header-end">
        
        <div class="notice notice-info">
            <p>Ova funkcionalnost će biti implementirana u verziji 1.5. Trenutno koristite WordPress korisnike.</p>
        </div>
        
        <p>
            <a href="<?php echo admin_url('users.php'); ?>" class="button button-primary">Idi na WordPress korisnike</a>
            <a href="<?php echo admin_url('user-new.php'); ?>" class="button">Dodaj novog korisnika</a>
        </p>
    </div>
    <?php
}

function cqd_admin_all_items_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Nemate dozvolu za pristup ovoj strani.'));
    }
    
    $selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $selected_user = $selected_user_id ? get_user_by('id', $selected_user_id) : null;
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php if ($selected_user): ?>
                📋 Stavke korisnika: <?php echo esc_html($selected_user->user_login); ?>
                <a href="<?php echo remove_query_arg('user_id'); ?>" class="page-title-action">← Nazad na sve korisnike</a>
            <?php else: ?>
                📋 Sve stavke svih korisnika
            <?php endif; ?>
        </h1>
        <hr class="wp-header-end">
        
        <?php if (!$selected_user): ?>
            <div class="cqd-users-list">
                <h2>Izaberite korisnika za pregled stavki</h2>
                <?php
                $users = get_users(['role__in' => ['subscriber', 'inventory_user']]);
                if (empty($users)) {
                    echo '<p>Nema registriranih korisnika.</p>';
                } else {
                    echo '<div class="cqd-users-grid">';
                    foreach ($users as $user) {
                        $items = get_user_meta($user->ID, 'cqd_stavke', true);
                        $items_count = is_array($items) ? count($items) : 0;
                        
                        echo '<div class="cqd-user-card">';
                        echo '<div class="cqd-user-avatar">' . strtoupper(substr($user->user_login, 0, 1)) . '</div>';
                        echo '<div class="cqd-user-info">';
                        echo '<h3>' . esc_html($user->user_login) . '</h3>';
                        echo '<p>' . esc_html($user->user_email) . '</p>';
                        echo '<p><strong>' . $items_count . '</strong> stavki</p>';
                        echo '</div>';
                        echo '<div class="cqd-user-actions">';
                        echo '<a href="' . add_query_arg('user_id', $user->ID) . '" class="button">Pregled stavki</a>';
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        <?php else: ?>
            <div class="cqd-user-items">
                <?php
                $items = get_user_meta($selected_user_id, 'cqd_stavke', true);
                if (!is_array($items) || empty($items)) {
                    echo '<div class="notice notice-warning"><p>Ovaj korisnik nema nijednu stavku.</p></div>';
                } else {
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>ID</th>';
                    echo '<th>Naziv</th>';
                    echo '<th>Status</th>';
                    echo '<th>Kategorija</th>';
                    echo '<th>Lokacija</th>';
                    echo '<th>Datum kreiranja</th>';
                    echo '<th>Akcije</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    
                    foreach ($items as $index => $item) {
                        echo '<tr>';
                        echo '<td>' . ($index + 1) . '</td>';
                        echo '<td><strong>' . esc_html($item['title'] ?? 'Bez naziva') . '</strong></td>';
                        echo '<td>' . esc_html($item['status'] ?? 'Nepoznato') . '</td>';
                        echo '<td>' . esc_html($item['kategorija'] ?? '—') . '</td>';
                        echo '<td>' . esc_html($item['lokacija'] ?? '—') . '</td>';
                        echo '<td>' . esc_html($item['created_at'] ?? '—') . '</td>';
                        echo '<td>';
                        echo '<button class="button button-small" onclick="alert(\'Edit će biti dostupan u verziji 1.5\')">Pregled</button>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                    
                    echo '<p class="description">Ukupno stavki: ' . count($items) . '</p>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function cqd_admin_backup_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Nemate dozvolu za pristup ovoj strani.'));
    }
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">💾 Backup i Restore podataka</h1>
        <hr class="wp-header-end">
        
        <div class="notice notice-info">
            <p>Ova funkcionalnost će biti implementirana u verziji 1.8.</p>
        </div>
        
        <div class="card">
            <h2>Planirane funkcionalnosti:</h2>
            <ul>
                <li>✅ Export svih podataka u CSV/JSON formatu</li>
                <li>✅ Import podataka iz prethodnog export-a</li>
                <li>✅ Automatski backup po rasporedu</li>
                <li>✅ Istorija backup-a sa datumima</li>
                <li>✅ Po-korisnički export/import</li>
                <li>✅ Sigurnosna kopija QR kodova</li>
            </ul>
        </div>
        
        <div class="card">
            <h3>Preporučene radnje za sada:</h3>
            <p>Koristite WordPress export za sigurnosnu kopiju:</p>
            <p>
                <a href="<?php echo admin_url('export.php'); ?>" class="button button-primary">
                    WordPress Export
                </a>
                <a href="<?php echo admin_url('plugin-editor.php?plugin=' . CQD_PLUGIN_BASENAME); ?>" class="button">
                    Backup koda plugin-a
                </a>
            </p>
        </div>
    </div>
    <?php
}

function cqd_admin_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Nemate dozvolu za pristup ovoj strani.'));
    }
    
    // Obrada forme za podešavanja
    if (isset($_POST['cqd_save_settings']) && wp_verify_nonce($_POST['cqd_settings_nonce'], 'cqd_save_settings')) {
        // Sačuvaj podešavanja
        update_option('cqd_items_per_page', intval($_POST['items_per_page']));
        update_option('cqd_qr_quality', sanitize_text_field($_POST['qr_quality']));
        update_option('cqd_backup_schedule', sanitize_text_field($_POST['backup_schedule']));
        
        echo '<div class="notice notice-success"><p>Podešavanja su sačuvana.</p></div>';
        
        // Log aktivnost
        cqd_log_activity('settings', 'Admin updated plugin settings', 'system');
    }
    
    // Učitaj trenutna podešavanja
    $items_per_page = get_option('cqd_items_per_page', 20);
    $qr_quality = get_option('cqd_qr_quality', 'high');
    $backup_schedule = get_option('cqd_backup_schedule', 'weekly');
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">⚙️ Podešavanja sistema</h1>
        <hr class="wp-header-end">
        
        <form method="post" action="">
            <?php wp_nonce_field('cqd_save_settings', 'cqd_settings_nonce'); ?>
            
            <div class="card">
                <h2>Opšta podešavanja</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="items_per_page">Stavki po strani</label></th>
                        <td>
                            <input type="number" id="items_per_page" name="items_per_page" 
                                   value="<?php echo esc_attr($items_per_page); ?>" min="5" max="100" class="small-text">
                            <p class="description">Broj stavki koji se prikazuje u tabelama</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="qr_quality">Kvalitet QR kodova</label></th>
                        <td>
                            <select id="qr_quality" name="qr_quality">
                                <option value="low" <?php selected($qr_quality, 'low'); ?>>Nizak (brže generisanje)</option>
                                <option value="medium" <?php selected($qr_quality, 'medium'); ?>>Srednji</option>
                                <option value="high" <?php selected($qr_quality, 'high'); ?>>Visok (najbolji kvalitet)</option>
                            </select>
                            <p class="description">Kvalitet generisanih QR kodova</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="backup_schedule">Automatski backup</label></th>
                        <td>
                            <select id="backup_schedule" name="backup_schedule">
                                <option value="daily" <?php selected($backup_schedule, 'daily'); ?>>Dnevno</option>
                                <option value="weekly" <?php selected($backup_schedule, 'weekly'); ?>>Nedeljno</option>
                                <option value="monthly" <?php selected($backup_schedule, 'monthly'); ?>>Mesečno</option>
                                <option value="never" <?php selected($backup_schedule, 'never'); ?>>Nikad</option>
                            </select>
                            <p class="description">Planiran automatski backup podataka</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2>Informacije o sistemu</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Verzija plugin-a</th>
                        <td><?php echo CQD_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Ukupno korisnika</th>
                        <td><?php echo count(get_users(['role__in' => ['subscriber', 'inventory_user']])); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Ukupno stavki</th>
                        <td><?php echo cqd_get_total_items_count(); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Poslednji upgrade</th>
                        <td><?php echo get_option('cqd_last_upgrade', 'Nepoznato'); ?></td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <button type="submit" name="cqd_save_settings" class="button button-primary">
                    Sačuvaj podešavanja
                </button>
            </p>
        </form>
    </div>
    <?php
}

// ================================================
// SEKCIJA: DISPLAY RECENT ACTIVITY
// ================================================

function cqd_display_recent_activity() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cqd_activity_log';
    
    // Proveri da li tabela postoji
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        // Tabela ne postoji - prikaži default poruku
        echo '<p>Nema nedavne aktivnosti. Tabela log-a će biti kreirana pri prvoj akciji.</p>';
        return;
    }
    
    // Dohvati poslednjih 10 aktivnosti
    $activities = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT a.*, u.user_login 
             FROM $table_name a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             ORDER BY a.created_at DESC 
             LIMIT %d",
            10
        )
    );
    
    if (empty($activities)) {
        // Prikaži podrazumevane aktivnosti
        $recent_users = get_users([
            'role__in' => ['subscriber', 'inventory_user'],
            'number' => 5,
            'orderby' => 'registered',
            'order' => 'DESC'
        ]);
        
        if (empty($recent_users)) {
            echo '<p>Nema nedavne aktivnosti.</p>';
            return;
        }
        
        echo '<ul class="cqd-activity-list">';
        foreach ($recent_users as $user) {
            $time = human_time_diff(strtotime($user->user_registered), current_time('timestamp'));
            echo '<li>';
            echo '<span class="dashicons dashicons-admin-users"></span>';
            echo '<div class="activity-content">';
            echo '<span class="activity-desc">Novi korisnik registrovan</span>';
            echo '<span class="activity-user">' . esc_html($user->user_login) . '</span>';
            echo '</div>';
            echo '<span class="time-ago">pre ' . $time . '</span>';
            echo '</li>';
        }
        echo '</ul>';
        return;
    }
    
    echo '<ul class="cqd-activity-list">';
    foreach ($activities as $activity) {
        $time = human_time_diff(strtotime($activity->created_at), current_time('timestamp'));
        $icon = cqd_get_activity_icon($activity->activity_type);
        
        echo '<li>';
        echo '<span class="dashicons ' . esc_attr($icon) . '"></span>';
        echo '<div class="activity-content">';
        echo '<span class="activity-desc">' . esc_html($activity->description) . '</span>';
        if ($activity->user_login) {
            echo '<span class="activity-user">' . esc_html($activity->user_login) . '</span>';
        }
        echo '</div>';
        echo '<span class="time-ago">pre ' . $time . '</span>';
        echo '</li>';
    }
    echo '</ul>';
}

// ================================================
// SEKCIJA: ADVANCED ACTIVITY LOG (VERZIJA 1.4.3)
// ================================================

function cqd_display_advanced_activity_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cqd_activity_log';
    
    // Proveri da li tabela postoji
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        echo '<div class="notice notice-warning"><p>Tabela za log aktivnosti nije kreirana. Aktivirajte plugin ponovo ili dodajte neku aktivnost.</p></div>';
        return;
    }
    
    // Paginacija parametri
    $per_page = 15;
    $current_page = isset($_GET['activity_page']) ? max(1, intval($_GET['activity_page'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Filteri
    $where_conditions = [];
    $where_sql = '';
    
    if (isset($_GET['activity_type']) && !empty($_GET['activity_type'])) {
        $activity_type = sanitize_text_field($_GET['activity_type']);
        $where_conditions[] = $wpdb->prepare("a.activity_type = %s", $activity_type);
    }
    
    if (!empty($where_conditions)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Ukupan broj aktivnosti
    $total_activities = $wpdb->get_var("SELECT COUNT(*) FROM $table_name a $where_sql");
    $total_pages = ceil($total_activities / $per_page);
    
    // Dohvati aktivnosti sa paginacijom
    $activities = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, u.user_login, u.user_email 
         FROM $table_name a
         LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
         $where_sql
         ORDER BY a.created_at DESC 
         LIMIT %d OFFSET %d",
        $per_page, $offset
    ));
    
    ?>
    <div class="cqd-advanced-activity">
        <div class="cqd-activity-header">
            <h3>📊 Detaljna istorija aktivnosti</h3>
            <div class="cqd-activity-stats">
                <span class="cqd-stat-badge">Ukupno: <?php echo intval($total_activities); ?></span>
                <span class="cqd-stat-badge">Strana: <?php echo $current_page; ?>/<?php echo $total_pages; ?></span>
            </div>
        </div>
        
        <?php if (empty($activities)): ?>
            <div class="cqd-empty-activity">
                <p>Nema zapisa o aktivnostima. Prva akcija će kreirati zapis.</p>
            </div>
        <?php else: ?>
            <div class="cqd-activity-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="cqd-dashboard">
                    <select name="activity_type" onchange="this.form.submit()">
                        <option value="">Svi tipovi aktivnosti</option>
                        <?php
                        $activity_types = $wpdb->get_results(
                            "SELECT DISTINCT activity_type FROM $table_name ORDER BY activity_type"
                        );
                        foreach ($activity_types as $type) {
                            $selected = isset($_GET['activity_type']) && $_GET['activity_type'] == $type->activity_type ? 'selected' : '';
                            echo '<option value="' . esc_attr($type->activity_type) . '" ' . $selected . '>' . esc_html($type->activity_type) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <?php if (isset($_GET['activity_type'])): ?>
                        <a href="<?php echo remove_query_arg('activity_type'); ?>" class="button">Poništi filter</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped cqd-activity-table">
                <thead>
                    <tr>
                        <th width="50">ID</th>
                        <th width="120">Datum</th>
                        <th width="100">Tip</th>
                        <th>Opis</th>
                        <th width="150">Korisnik</th>
                        <th width="100">Item ID</th>
                        <th width="80">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity): 
                        $time_ago = human_time_diff(strtotime($activity->created_at), current_time('timestamp'));
                        $icon = cqd_get_activity_icon($activity->activity_type);
                    ?>
                    <tr>
                        <td><?php echo intval($activity->id); ?></td>
                        <td>
                            <div><?php echo date_i18n('d.m.Y', strtotime($activity->created_at)); ?></div>
                            <small><?php echo date_i18n('H:i:s', strtotime($activity->created_at)); ?></small>
                            <div><small>pre <?php echo $time_ago; ?></small></div>
                        </td>
                        <td>
                            <span class="dashicons <?php echo esc_attr($icon); ?>"></span><br>
                            <small><?php echo esc_html($activity->activity_type); ?></small>
                        </td>
                        <td><?php echo esc_html($activity->description); ?></td>
                        <td>
                            <?php if ($activity->user_login): ?>
                                <strong><?php echo esc_html($activity->user_login); ?></strong><br>
                                <small><?php echo esc_html($activity->user_email); ?></small>
                            <?php else: ?>
                                <em>Sistem</em>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo esc_html($activity->item_id ?: '—'); ?></code></td>
                        <td><small><?php echo esc_html($activity->ip_address ?: '—'); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div class="cqd-pagination">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('activity_page', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                        'add_args' => array('page' => 'cqd-dashboard')
                    ));
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="cqd-export-section">
                <p>
                    <strong>Export opcije:</strong>
                    <a href="<?php echo admin_url('admin.php?page=cqd-dashboard&export_activities=csv'); ?>" class="button button-small">📥 Export CSV</a>
                    <a href="<?php echo admin_url('admin.php?page=cqd-dashboard&export_activities=json'); ?>" class="button button-small">📥 Export JSON</a>
                </p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// ================================================
// SEKCIJA: TEST FUNKCIJA ZA LOGOVANJE
// ================================================

function cqd_test_logging() {
    if (isset($_GET['test_log']) && current_user_can('manage_options')) {
        // Testiraj logovanje
        $result = cqd_log_activity('test', 'Test log aktivnost - ' . current_time('mysql'), 'test_id');
        
        if ($result) {
            echo '<div class="notice notice-success"><p>✅ Test log dodat! Osveži stranicu da vidiš promenu.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Greška pri test logovanju!</p></div>';
        }
    }
}
add_action('admin_notices', 'cqd_test_logging');

function cqd_test_manual_logging() {
    if (current_user_can('manage_options') && isset($_GET['test_add_item'])) {
        $test_result = cqd_log_activity('test', 'Manual test of logging system', 'test_' . time());
        
        echo '<div class="notice ' . ($test_result ? 'notice-success' : 'notice-error') . '">';
        echo '<p>Test logovanja: ' . ($test_result ? '✅ USPEŠNO' : '❌ NEUSPEŠNO') . '</p>';
        
        // Proverite tabelu
        global $wpdb;
        $table_name = $wpdb->prefix . 'cqd_activity_log';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo '<p>Ukupno logova u tabeli: ' . $count . '</p>';
        
        echo '</div>';
    }
}
add_action('admin_notices', 'cqd_test_manual_logging');

// ================================================
// SEKCIJA: SHORTCODE: REGISTRACIJA
// ================================================

function cqd_register_shortcode() {
    if (is_user_logged_in()) {
        return '<p>Već ste ulogovani. <a href="' . site_url('/moj-dashboard') . '">Idite na Dashboard</a></p>';
    }

    $output = '';
    
    if (isset($_POST['cqd_register'])) {
        // Nonce verifikacija
        if (!wp_verify_nonce($_POST['cqd_register_nonce'], 'cqd_register_action')) {
            $output .= '<div class="cqd-alert error">Greška u bezbednosti!</div>';
        } else {
            $username = sanitize_user($_POST['username'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $errors = [];

            // Validacija
            if (empty($username) || empty($email) || empty($password)) {
                $errors[] = 'Sva polja su obavezna.';
            }
            
            if ($password !== $confirm_password) {
                $errors[] = 'Lozinke se ne podudaraju.';
            }
            
            if (strlen($password) < 6) {
                $errors[] = 'Lozinka mora imati najmanje 6 karaktera.';
            }
            
            if (username_exists($username)) {
                $errors[] = 'Korisničko ime već postoji.';
            }
            
            if (email_exists($email)) {
                $errors[] = 'Email već postoji.';
            }

            // Registracija
            if (empty($errors)) {
                $user_id = wp_create_user($username, $password, $email);
                
                if (!is_wp_error($user_id)) {
                    // Dodaj defaultne korisničke statusove
                    $default_user_statuses = [
                        'u_garanciji' => '📦 U garanciji',
                        'na_lageru' => '📊 Na lageru',
                        'u_upotrebi' => '🔄 U upotrebi',
                        'za_zamenu' => '🔄 Za zamenu'
                    ];
                    update_user_meta($user_id, 'cqd_custom_statuses', $default_user_statuses);
                    
                    // Log aktivnost - direktan poziv
                    cqd_log_activity('register', 'New user registered: ' . $username, 'user_' . $user_id);
                    
                    // Automatski login
                    wp_set_current_user($user_id, $username);
                    wp_set_auth_cookie($user_id);
                    
                    $output .= '<div class="cqd-alert success">✅ Registracija uspešna! Preusmeravam...</div>';
                    $output .= '<script>setTimeout(() => window.location.href = "' . site_url('/moj-dashboard') . '", 1000);</script>';
                } else {
                    $errors[] = 'Greška: ' . $user_id->get_error_message();
                }
            }

            // Prikaz grešaka
            foreach ($errors as $error) {
                $output .= '<div class="cqd-alert error">' . esc_html($error) . '</div>';
            }
        }
    }

    // Registraciona forma
    $output .= '<div class="cqd-form-container">';
    $output .= '<h2>📝 Registracija</h2>';
    $output .= '<form method="post" id="cqd-register-form">';
    $output .= wp_nonce_field('cqd_register_action', 'cqd_register_nonce', true, false);
    
    $output .= '<div class="cqd-form-group">';
    $output .= '<label for="username">Korisničko ime *</label>';
    $output .= '<input type="text" id="username" name="username" placeholder="unesite korisničko ime" required>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-form-group">';
    $output .= '<label for="email">Email adresa *</label>';
    $output .= '<input type="email" id="email" name="email" placeholder="vas@email.com" required>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-form-group">';
    $output .= '<label for="password">Lozinka *</label>';
    $output .= '<input type="password" id="password" name="password" placeholder="minimum 6 karaktera" required>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-form-group">';
    $output .= '<label for="confirm_password">Potvrdite lozinku *</label>';
    $output .= '<input type="password" id="confirm_password" name="confirm_password" placeholder="ponovite lozinku" required>';
    $output .= '</div>';
    
    $output .= '<button type="submit" name="cqd_register" class="cqd-btn primary">📝 Registruj se</button>';
    $output .= '<p class="cqd-form-footer">Već imate nalog? <a href="' . site_url('/login') . '">Prijavite se ovde</a></p>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}
add_shortcode('custom_register', 'cqd_register_shortcode');

// ================================================
// SEKCIJA: SHORTCODE: LOGIN
// ================================================

function cqd_login_shortcode() {
    if (is_user_logged_in()) {
        return '<p>Već ste ulogovani. <a href="' . site_url('/moj-dashboard') . '">Idite na Dashboard</a></p>';
    }

    $output = '';
    
    if (isset($_POST['cqd_login'])) {
        if (!wp_verify_nonce($_POST['cqd_login_nonce'], 'cqd_login_action')) {
            $output .= '<div class="cqd-alert error">Greška u bezbednosti!</div>';
        } else {
            $username = sanitize_user($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);

            $user = wp_signon([
                'user_login' => $username,
                'user_password' => $password,
                'remember' => $remember
            ], false);

            if (is_wp_error($user)) {
                $output .= '<div class="cqd-alert error">❌ Neispravno korisničko ime ili lozinka.</div>';
            } else {
                // Log aktivnost - direktan poziv
                cqd_log_activity('login', 'User logged in: ' . $username, 'user_' . $user->ID);
                wp_redirect(site_url('/moj-dashboard'));
                exit;
            }
        }
    }

    $output .= '<div class="cqd-form-container">';
    $output .= '<h2>🔐 Prijava</h2>';
    $output .= '<form method="post" id="cqd-login-form">';
    $output .= wp_nonce_field('cqd_login_action', 'cqd_login_nonce', true, false);
    
    $output .= '<div class="cqd-form-group">';
    $output .= '<label for="login-username">Korisničko ime</label>';
    $output .= '<input type="text" id="login-username" name="username" placeholder="unesite korisničko ime" required>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-form-group">';
    $output .= '<label for="login-password">Lozinka</label>';
    $output .= '<input type="password" id="login-password" name="password" placeholder="unesite lozinku" required>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-form-options">';
    $output .= '<label><input type="checkbox" name="remember"> Zapamti me</label>';
    $output .= '</div>';
    
    $output .= '<button type="submit" name="cqd_login" class="cqd-btn primary">🔐 Prijavi se</button>';
    $output .= '<p class="cqd-form-footer">Nemate nalog? <a href="' . site_url('/register') . '">Registrujte se ovde</a></p>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}
add_shortcode('custom_login', 'cqd_login_shortcode');

// ================================================
// SEKCIJA: POMOĆNE FUNKCIJE ZA DASHBOARD
// ================================================

// A. RENDER ADMIN PANEL
function cqd_render_admin_panel($admin_id) {
    $output = '<div class="cqd-admin-panel">';
    $output .= '<div class="cqd-admin-header">';
    $output .= '<h3><span class="cqd-admin-badge">👑 ADMIN</span> Kontrolni panel</h3>';
    $output .= '</div>';
    
    if (!isset($_GET['user'])) {
        $users = get_users([
            'role__in' => ['subscriber', 'inventory_user'],
            'exclude' => [$admin_id]
        ]);
        
        if (!empty($users)) {
            $output .= '<div class="cqd-users-grid">';
            foreach ($users as $user) {
                $stavke = get_user_meta($user->ID, 'cqd_stavke', true);
                $stavke_count = is_array($stavke) ? count($stavke) : 0;
                $custom_statuses = get_user_meta($user->ID, 'cqd_custom_statuses', true);
                $status_count = is_array($custom_statuses) ? count($custom_statuses) : 0;
                
                $output .= '<div class="cqd-user-card">';
                $output .= '<div class="cqd-user-avatar">' . strtoupper(substr($user->user_login, 0, 1)) . '</div>';
                $output .= '<div class="cqd-user-info">';
                $output .= '<h4>' . esc_html($user->user_login) . '</h4>';
                $output .= '<p>' . esc_html($user->user_email) . '</p>';
                $output .= '<div class="cqd-user-stats">';
                $output .= '<span class="cqd-stat"><strong>' . $stavke_count . '</strong> stavki</span>';
                $output .= '<span class="cqd-stat"><strong>' . $status_count . '</strong> statusa</span>';
                $output .= '</div>';
                $output .= '</div>';
                $output .= '<div class="cqd-user-actions">';
                $output .= '<a href="' . add_query_arg('user', $user->ID) . '" class="cqd-btn small">👁️ Pregled</a>';
                $output .= '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';
        } else {
            $output .= '<p>Trenutno nema drugih korisnika.</p>';
        }
    } else {
        $selected_user_id = intval($_GET['user']);
        $selected_user = get_user_by('id', $selected_user_id);
        if ($selected_user) {
            $output .= '<div class="cqd-admin-notice">';
            $output .= '<p>Pregledate stavke korisnika: <strong>' . esc_html($selected_user->user_login) . '</strong></p>';
            $output .= '<p>Upozorenje: Sve izmene će se odnositi na ovog korisnika.</p>';
            $output .= '</div>';
        }
    }
    
    $output .= '</div>';
    return $output;
}

// B. OBRADA AKCIJA - POPRAVLJENA VERZIJA 1.4.3
function cqd_process_dashboard_actions($user_id) {
    $output = '';
    $nonce_action = 'cqd_dashboard_' . $user_id;
    $redirect_needed = false;
    
    // 1. DODAVANJE NOVE STAVKE
    if (isset($_POST['cqd_add_item']) && wp_verify_nonce($_POST['cqd_nonce'], $nonce_action)) {
        // Procesuiraj custom polja
        $status = sanitize_text_field($_POST['status'] ?? '');
        if ($status === 'custom') {
            $status = sanitize_text_field($_POST['custom_status'] ?? '');
        }
        
        $lokacija = sanitize_text_field($_POST['lokacija'] ?? '');
        if ($lokacija === 'custom') {
            $lokacija = sanitize_text_field($_POST['custom_location'] ?? '');
        }
        
        $kategorija = sanitize_text_field($_POST['kategorija'] ?? '');
        if ($kategorija === 'custom') {
            $kategorija = sanitize_text_field($_POST['custom_category'] ?? '');
        }
        
        $new_item = [
            'id' => uniqid('item_', true),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'status' => $status,
            'lokacija' => $lokacija,
            'kategorija' => $kategorija,
            'napomena' => sanitize_textarea_field($_POST['napomena'] ?? ''),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        // Upload slike
        if (!empty($_FILES['image']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            
            $uploaded = media_handle_upload('image', 0);
            if (!is_wp_error($uploaded)) {
                $new_item['image_id'] = $uploaded;
                $new_item['image_url'] = wp_get_attachment_url($uploaded);
            }
        }
        
        // Sačuvaj stavku
        $items = get_user_meta($user_id, 'cqd_stavke', true);
        if (!is_array($items)) $items = [];
        $items[] = $new_item;
        update_user_meta($user_id, 'cqd_stavke', $items);
        
        // Log aktivnost - DIREKTAN POZIV
        cqd_log_activity('item', 'Item added: ' . $new_item['title'], $new_item['id']);
        
        // Set session message
        $_SESSION['cqd_message'] = [
            'type' => 'success',
            'text' => '✅ Stavka "' . esc_html($new_item['title']) . '" je uspešno dodata!'
        ];
        
        $redirect_needed = true;
    }
    
    // 2. BRISANJE STAVKE
    if (isset($_POST['cqd_delete_item']) && isset($_POST['item_index']) && wp_verify_nonce($_POST['cqd_nonce'], $nonce_action)) {
        $index = intval($_POST['item_index']);
        $items = get_user_meta($user_id, 'cqd_stavke', true);
        
        if (is_array($items) && isset($items[$index])) {
            $deleted_title = $items[$index]['title'];
            unset($items[$index]);
            $items = array_values($items);
            update_user_meta($user_id, 'cqd_stavke', $items);
            
            // Log aktivnost - DIREKTAN POZIV
            cqd_log_activity('item', 'Item deleted: ' . $deleted_title, 'user_' . $user_id);
            
            $_SESSION['cqd_message'] = [
                'type' => 'warning',
                'text' => '🗑️ Stavka "' . esc_html($deleted_title) . '" je obrisana.'
            ];
            
            $redirect_needed = true;
        }
    }
    
    // Redirect to prevent form resubmission
    if ($redirect_needed && !wp_doing_ajax()) {
        wp_redirect(remove_query_arg(['action', 'item_index', 'cqd_nonce']));
        exit;
    }
    
    // Display session messages
    if (isset($_SESSION['cqd_message'])) {
        $message = $_SESSION['cqd_message'];
        $output .= '<div class="cqd-alert ' . $message['type'] . '">' . $message['text'] . '</div>';
        unset($_SESSION['cqd_message']);
    }
    
    return $output;
}

// C. RENDER STATISTIKE
function cqd_render_stats($items) {
    if (empty($items)) return '';
    
    $status_count = [];
    foreach ($items as $item) {
        $status = $item['status'] ?? 'unknown';
        $status_count[$status] = isset($status_count[$status]) ? $status_count[$status] + 1 : 1;
    }
    
    $output = '<div class="cqd-stats-grid">';
    $output .= '<div class="cqd-stat-card">';
    $output .= '<div class="cqd-stat-icon">📦</div>';
    $output .= '<div class="cqd-stat-content">';
    $output .= '<h3>' . count($items) . '</h3>';
    $output .= '<p>Ukupno stavki</p>';
    $output .= '</div>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-stat-card">';
    $output .= '<div class="cqd-stat-icon">🏷️</div>';
    $output .= '<div class="cqd-stat-content">';
    $output .= '<h3>' . count($status_count) . '</h3>';
    $output .= '<p>Različitih statusa</p>';
    $output .= '</div>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-stat-card">';
    $output .= '<div class="cqd-stat-icon">📍</div>';
    $output .= '<div class="cqd-stat-content">';
    $locations = array_unique(array_column($items, 'lokacija'));
    $locations = array_filter($locations);
    $output .= '<h3>' . count($locations) . '</h3>';
    $output .= '<p>Lokacija</p>';
    $output .= '</div>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-stat-card">';
    $output .= '<div class="cqd-stat-icon">📷</div>';
    $output .= '<div class="cqd-stat-content">';
    $with_images = 0;
    foreach ($items as $item) {
        if (!empty($item['image_url'])) $with_images++;
    }
    $output .= '<h3>' . $with_images . '</h3>';
    $output .= '<p>Sa slikama</p>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';
    
    return $output;
}

// D. RENDER TABELE STAVKI
function cqd_render_items_table($items, $user_id, $is_admin = false) {
    $output = '<div class="cqd-table-container">';
    $output .= '<div class="cqd-table-header">';
    $output .= '<h3>📋 Lista stavki</h3>';
    $output .= '<div class="cqd-table-actions">';
    $output .= '<button class="cqd-btn small" onclick="cqdExportCSV()">📥 Export CSV</button>';
    $output .= '</div>';
    $output .= '</div>';
    
    $output .= '<table class="cqd-table" id="cqd-items-table">';
    $output .= '<thead>';
    $output .= '<tr>';
    $output .= '<th width="50">#</th>';
    $output .= '<th width="80">Slika</th>';
    $output .= '<th>Naslov</th>';
    $output .= '<th width="120">Status</th>';
    $output .= '<th width="150">Lokacija</th>';
    $output .= '<th width="100">QR Kod</th>';
    $output .= '<th width="200">Akcije</th>';
    $output .= '</tr>';
    $output .= '</thead>';
    $output .= '<tbody>';
    
    foreach ($items as $index => $item) {
        $item_id = $item['id'] ?? 'item_' . $index;
        $qr_data = urlencode(site_url('/stavka?id=' . $item_id));
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . $qr_data;
        
        $output .= '<tr data-item-id="' . $item_id . '">';
        $output .= '<td class="text-center">' . ($index + 1) . '</td>';
        $output .= '<td class="text-center">';
        if (!empty($item['image_url'])) {
            $output .= '<img src="' . esc_url($item['image_url']) . '" class="cqd-thumb" alt="Thumbnail">';
        } else {
            $output .= '<div class="cqd-no-image">🖼️</div>';
        }
        $output .= '</td>';
        $output .= '<td>';
        $output .= '<strong>' . esc_html($item['title'] ?? 'Bez naslova') . '</strong>';
        if (!empty($item['kategorija'])) {
            $output .= '<br><small class="text-muted">' . esc_html($item['kategorija']) . '</small>';
        }
        $output .= '</td>';
        $output .= '<td><span class="cqd-status-badge">' . esc_html($item['status'] ?? 'Nepoznato') . '</span></td>';
        $output .= '<td>' . esc_html($item['lokacija'] ?? '—') . '</td>';
        $output .= '<td class="text-center">';
        $output .= '<img src="' . esc_url($qr_url) . '" class="cqd-qr-thumb" alt="QR Code" data-qr-url="' . esc_url($qr_url) . '">';
        $output .= '</td>';
        $output .= '<td class="cqd-actions-cell">';
        $output .= '<div class="cqd-action-buttons">';
        
        // Dugme za preuzimanje QR
        $output .= '<button class="cqd-btn-icon" onclick="cqdDownloadQR(\'' . esc_url($qr_url) . '\', \'' . esc_attr($item['title']) . '\')" title="Preuzmi QR kod">';
        $output .= '📥';
        $output .= '</button>';
        
        // Dugme za edit
        $output .= '<button class="cqd-btn-icon" onclick="cqdOpenEditModal(' . $index . ')" title="Izmeni stavku">';
        $output .= '✏️';
        $output .= '</button>';
        
        // Dugme za brisanje
        $output .= '<form method="post" style="display:inline;" onsubmit="return confirm(\'Da li ste sigurni da želite da obrišete ovu stavku?\')">';
        $output .= wp_nonce_field('cqd_dashboard_' . $user_id, 'cqd_nonce', true, false);
        $output .= '<input type="hidden" name="item_index" value="' . $index . '">';
        $output .= '<button type="submit" name="cqd_delete_item" class="cqd-btn-icon danger" title="Obriši stavku">';
        $output .= '🗑️';
        $output .= '</button>';
        $output .= '</form>';
        
        $output .= '</div>';
        $output .= '</td>';
        $output .= '</tr>';
    }
    
    $output .= '</tbody>';
    $output .= '</table>';
    $output .= '</div>';
    
    return $output;
}

// E. RENDER FORME ZA DODAVANJE/EDIT - POPRAVLJENA VERZIJA
function cqd_render_item_form($user_id) {
    $system_statuses = cqd_get_system_statuses();
    $custom_statuses = get_user_meta($user_id, 'cqd_custom_statuses', true);
    $custom_locations = get_user_meta($user_id, 'cqd_custom_locations', true);
    $custom_categories = get_user_meta($user_id, 'cqd_custom_categories', true);
    
    if (!is_array($custom_statuses)) $custom_statuses = [];
    if (!is_array($custom_locations)) $custom_locations = [];
    if (!is_array($custom_categories)) $custom_categories = [];
    
    $output = '<div class="cqd-form-section">';
    $output .= '<h3>➕ Dodaj novu stavku</h3>';
    
    $output .= '<form method="post" enctype="multipart/form-data" class="cqd-item-form" id="cqd-add-item-form">';
    $output .= wp_nonce_field('cqd_dashboard_' . $user_id, 'cqd_nonce', true, false);
    
    $output .= '<div class="cqd-form-row">';
    $output .= '<div class="cqd-form-group">';
    $output .= '<label>Naslov stavke *</label>';
    $output .= '<input type="text" name="title" placeholder="Naziv vaše stavke" required>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-form-group">';
    $output .= '<label>Status</label>';
    $output .= '<select name="status" id="status-select" onchange="cqdToggleCustomField(this, \'status\')">';
    $output .= '<option value="">Izaberite status</option>';
    
    // Sistemski statusi
    foreach ($system_statuses as $key => $label) {
        $output .= '<option value="' . esc_attr($label) . '">' . esc_html($label) . '</option>';
    }
    
    // Korisnički statusi
    if (!empty($custom_statuses)) {
        foreach ($custom_statuses as $key => $label) {
            $output .= '<option value="' . esc_attr($label) . '">' . esc_html($label) . '</option>';
        }
    }
    
    $output .= '<option value="custom">➕ Dodaj novi status</option>';
    $output .= '</select>';
    $output .= '<input type="text" name="custom_status" id="custom-status-input" placeholder="Unesite novi status" style="display:none; margin-top:5px;">';
    $output .= '</div>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-form-row">';
    $output .= '<div class="cqd-form-group">';
    $output .= '<label>Lokacija</label>';
    $output .= '<select name="lokacija" id="location-select" onchange="cqdToggleCustomField(this, \'location\')">';
    $output .= '<option value="">Izaberite lokaciju</option>';
    
    // Predefinisane lokacije
    $default_locations = ['Kancelarija', 'Magacin', 'Kuća', 'Skladiste', 'Radnja'];
    foreach ($default_locations as $location) {
        $output .= '<option value="' . esc_attr($location) . '">' . esc_html($location) . '</option>';
    }
    
    // Korisničke lokacije
    if (!empty($custom_locations)) {
        foreach ($custom_locations as $location) {
            $output .= '<option value="' . esc_attr($location) . '">' . esc_html($location) . '</option>';
        }
    }
    
    $output .= '<option value="custom">➕ Dodaj novu lokaciju</option>';
    $output .= '</select>';
    $output .= '<input type="text" name="custom_location" id="custom-location-input" placeholder="Unesite novu lokaciju" style="display:none; margin-top:5px;">';
    $output .= '</div>';
    
    $output .= '<div class="cqd-form-group">';
    $output .= '<label>Kategorija</label>';
    $output .= '<select name="kategorija" id="category-select" onchange="cqdToggleCustomField(this, \'category\')">';
    $output .= '<option value="">Izaberite kategoriju</option>';
    
    // Predefinisane kategorije
    $default_categories = ['Elektronika', 'Oprema', 'Alat', 'Nameštaj', 'Vozilo', 'Dokument'];
    foreach ($default_categories as $category) {
        $output .= '<option value="' . esc_attr($category) . '">' . esc_html($category) . '</option>';
    }
    
    // Korisničke kategorije
    if (!empty($custom_categories)) {
        foreach ($custom_categories as $category) {
            $output .= '<option value="' . esc_attr($category) . '">' . esc_html($category) . '</option>';
        }
    }
    
    $output .= '<option value="custom">➕ Dodaj novu kategoriju</option>';
    $output .= '</select>';
    $output .= '<input type="text" name="custom_category" id="custom-category-input" placeholder="Unesite novu kategoriju" style="display:none; margin-top:5px;">';
    $output .= '</div>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-form-group">';
    $output .= '<label>Napomena</label>';
    $output .= '<textarea name="napomena" placeholder="Dodatne informacije, opis, karakteristike..." rows="3"></textarea>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-form-group">';
    $output .= '<label>Slika stavke (opciono)</label>';
    $output .= '<input type="file" name="image" accept="image/*" id="item-image">';
    $output .= '<div id="image-preview"></div>';
    $output .= '</div>';
    
    $output .= '<button type="submit" name="cqd_add_item" class="cqd-btn primary">➕ Dodaj stavku</button>';
    $output .= '</form>';
    $output .= '</div>';
    
    return $output;
}

// F. RENDER EDIT MODAL
function cqd_render_edit_modal() {
    $output = '<div id="cqd-edit-modal" class="cqd-modal" style="display:none;">';
    $output .= '<div class="cqd-modal-content">';
    $output .= '<div class="cqd-modal-header">';
    $output .= '<h3>✏️ Izmena stavke</h3>';
    $output .= '<button class="cqd-modal-close" onclick="cqdCloseEditModal()">&times;</button>';
    $output .= '</div>';
    $output .= '<div class="cqd-modal-body" id="cqd-edit-form-container">';
    $output .= '<!-- Forma će biti učitana preko AJAX-a -->';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';
    
    return $output;
}

// ================================================
// SEKCIJA: DASHBOARD SHORTCODE - VERZIJA 1.4.3
// ================================================

function cqd_dashboard_shortcode() {
    if (!is_user_logged_in()) {
        wp_redirect(site_url('/login'));
        exit;
    }

    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $is_admin = current_user_can('administrator');
    $output = '';
    
    // ================================================
    // A. ADMIN PANEL (samo za administratore)
    // ================================================
    if ($is_admin) {
        $output .= cqd_render_admin_panel($current_user_id);
        
        // Ako admin gleda drugog korisnika, promeni kontekst
        if (isset($_GET['user']) && is_numeric($_GET['user'])) {
            $selected_user_id = intval($_GET['user']);
            $selected_user = get_user_by('id', $selected_user_id);
            if ($selected_user && $selected_user_id != 1) {
                $current_user_id = $selected_user_id;
                $current_user = $selected_user;
            }
        }
    }

    // ================================================
    // B. DASHBOARD HEADER
    // ================================================
    $output .= '<div class="cqd-dashboard-header">';
    $output .= '<div class="cqd-header-left">';
    $output .= '<h1>📊 ' . esc_html($current_user->user_login) . '\'s Dashboard</h1>';
    $output .= '<p class="cqd-subtitle">Upravljajte svojim inventarom</p>';
    $output .= '</div>';
    $output .= '<div class="cqd-header-right">';
    $output .= '<a href="' . wp_logout_url(site_url('/login')) . '" class="cqd-btn logout">🚪 Odjavi se</a>';
    if ($is_admin && $current_user_id != get_current_user_id()) {
        $output .= '<a href="' . remove_query_arg('user') . '" class="cqd-btn secondary">👈 Nazad na listu</a>';
    }
    $output .= '</div>';
    $output .= '</div>';

    // ================================================
    // C. OBRADA AKCIJA (dodavanje, brisanje, edit)
    // ================================================
    $action_result = cqd_process_dashboard_actions($current_user_id);
    if ($action_result) {
        $output .= $action_result;
    }

    // ================================================
    // D. STATISTIKE
    // ================================================
    $stavke = get_user_meta($current_user_id, 'cqd_stavke', true);
    if (!is_array($stavke)) $stavke = [];
    
    $output .= cqd_render_stats($stavke);

    // ================================================
    // E. TABELA STAVKI
    // ================================================
    if (!empty($stavke)) {
        $output .= cqd_render_items_table($stavke, $current_user_id, $is_admin);
    } else {
        $output .= '<div class="cqd-empty-state">';
        $output .= '<div class="cqd-empty-icon">📭</div>';
        $output .= '<h3>Nema stavki</h3>';
        $output .= '<p>Dodajte svoju prvu stavku kako biste počeli da koristite sistem.</p>';
        $output .= '</div>';
    }

    // ================================================
    // F. FORMA ZA DODAVANJE/EDIT STAVKE
    // ================================================
    $output .= cqd_render_item_form($current_user_id);

    // ================================================
    // G. MODAL ZA EDIT (HTML za JavaScript)
    // ================================================
    $output .= cqd_render_edit_modal();

    return $output;
}
add_shortcode('custom_dashboard', 'cqd_dashboard_shortcode');

// ================================================
// SEKCIJA: STRANICA ZA QR KOD (PUBLIC VIEW)
// ================================================

function cqd_stavka_page_shortcode() {
    if (!isset($_GET['id'])) {
        return '<div class="cqd-error">❌ Stavka nije pronađena.</div>';
    }
    
    $item_id = sanitize_text_field($_GET['id']);
    $output = '';
    
    // Pronađi stavku u svim korisnicima
    $all_users = get_users();
    $found_item = null;
    $item_owner = null;
    
    foreach ($all_users as $user) {
        $items = get_user_meta($user->ID, 'cqd_stavke', true);
        if (is_array($items)) {
            foreach ($items as $item) {
                if (($item['id'] ?? '') === $item_id) {
                    $found_item = $item;
                    $item_owner = $user;
                    break 2;
                }
            }
        }
    }
    
    if (!$found_item) {
        return '<div class="cqd-error">❌ Stavka nije pronađena ili je obrisana.</div>';
    }
    
    // Log pregled - direktan poziv
    cqd_log_activity('item', 'QR code scanned for item: ' . ($found_item['title'] ?? $item_id), $item_id);
    
    // Javni prikaz stavke
    $output .= '<div class="cqd-public-view">';
    $output .= '<div class="cqd-public-header">';
    $output .= '<h1>' . esc_html($found_item['title']) . '</h1>';
    $output .= '<div class="cqd-public-qr">';
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode(get_permalink());
    $output .= '<img src="' . esc_url($qr_url) . '" alt="QR Code">';
    $output .= '</div>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-public-details">';
    
    if (!empty($found_item['image_url'])) {
        $output .= '<div class="cqd-public-image">';
        $output .= '<img src="' . esc_url($found_item['image_url']) . '" alt="' . esc_attr($found_item['title']) . '">';
        $output .= '</div>';
    }
    
    $output .= '<div class="cqd-public-info">';
    $output .= '<div class="cqd-info-row"><strong>Status:</strong> ' . esc_html($found_item['status'] ?? 'Nepoznato') . '</div>';
    
    if (!empty($found_item['lokacija'])) {
        $output .= '<div class="cqd-info-row"><strong>Lokacija:</strong> ' . esc_html($found_item['lokacija']) . '</div>';
    }
    
    if (!empty($found_item['kategorija'])) {
        $output .= '<div class="cqd-info-row"><strong>Kategorija:</strong> ' . esc_html($found_item['kategorija']) . '</div>';
    }
    
    if (!empty($found_item['napomena'])) {
        $output .= '<div class="cqd-info-row"><strong>Napomena:</strong><br>' . nl2br(esc_html($found_item['napomena'])) . '</div>';
    }
    
    $output .= '<div class="cqd-info-row"><strong>Kreirano:</strong> ' . esc_html($found_item['created_at'] ?? 'Nepoznato') . '</div>';
    
    if ($item_owner) {
        $output .= '<div class="cqd-info-row"><strong>Vlasnik:</strong> ' . esc_html($item_owner->user_login) . '</div>';
    }
    
    $output .= '</div>';
    $output .= '</div>';
    
    $output .= '<div class="cqd-public-footer">';
    $output .= '<p>Ova stranica je generisana automatski iz sistema za upravljanje inventarom.</p>';
    $output .= '<p><small>Skenirano: ' . current_time('d.m.Y H:i') . '</small></p>';
    $output .= '</div>';
    $output .= '</div>';
    
    return $output;
}
add_shortcode('custom_stavka_page', 'cqd_stavka_page_shortcode');

// ================================================
// SEKCIJA: KREIRANJE STRANICA PRI AKTIVACIJI
// ================================================

function cqd_create_pages() {
    $pages = [
        'Registracija' => 'register',
        'Login' => 'login',
        'Moj Dashboard' => 'moj-dashboard',
        'Pregled Stavke' => 'stavka'
    ];

    foreach ($pages as $title => $slug) {
        if (!get_page_by_path($slug)) {
            $shortcode_map = [
                'register' => 'custom_register',
                'login' => 'custom_login',
                'moj-dashboard' => 'custom_dashboard',
                'stavka' => 'custom_stavka_page'
            ];

            $page_id = wp_insert_post([
                'post_title' => $title,
                'post_name' => $slug,
                'post_content' => '[' . $shortcode_map[$slug] . ']',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => 1
            ]);
            
            // Log kreiranje stranice - direktan poziv
            if ($page_id && !is_wp_error($page_id)) {
                cqd_log_activity('system', 'Created page: ' . $title, 'page_' . $page_id);
            }
        }
    }

    // Kreiraj custom role ako ne postoji
    if (!get_role('inventory_user')) {
        add_role('inventory_user', 'Inventory User', [
            'read' => true,
            'upload_files' => true,
            'edit_posts' => false,
            'delete_posts' => false
        ]);
        
        // Log kreiranje role - direktan poziv
        cqd_log_activity('system', 'Created inventory_user role', 'system');
    }
    
    // Dodaj capability za subscriber role
    $subscriber = get_role('subscriber');
    if ($subscriber) {
        $subscriber->add_cap('upload_files');
    }
    
    // Postavi inicijalnu verziju
    update_option('cqd_version', CQD_VERSION);
    update_option('cqd_last_upgrade', current_time('mysql'));
    
    // Log aktivaciju - direktan poziv
    cqd_log_activity('system', 'Plugin activated - Version ' . CQD_VERSION, 'system');
}
register_activation_hook(__FILE__, 'cqd_create_pages');

// ================================================
// SEKCIJA: DEAKTIVACIJA I ČIŠĆENJE
// ================================================

function cqd_deactivation() {
    // Log deaktivaciju - direktan poziv
    cqd_log_activity('system', 'Plugin deactivated', 'system');
    
    // Očisti scheduled events ako postoje
    wp_clear_scheduled_hook('cqd_daily_backup');
}
register_deactivation_hook(__FILE__, 'cqd_deactivation');

// ================================================
// SEKCIJA: ENQUEUE SCRIPTS I STYLES
// ================================================

function cqd_enqueue_assets() {
    // Proveri da li smo na našim stranicama
    global $post;
    if (!is_a($post, 'WP_Post')) return;
    
    $our_pages = ['register', 'login', 'moj-dashboard', 'stavka'];
    $is_our_page = false;
    
    foreach ($our_pages as $page) {
        if (has_shortcode($post->post_content, 'custom_' . str_replace('-', '_', $page)) ||
            (isset($post->post_name) && $post->post_name === $page)) {
            $is_our_page = true;
            break;
        }
    }
    
    if (!$is_our_page) return;
    
    // CSS
    wp_enqueue_style('cqd-styles', CQD_PLUGIN_URL . 'assets/css/style.css', [], CQD_VERSION);
    
    // JavaScript
    wp_enqueue_script('cqd-scripts', CQD_PLUGIN_URL . 'assets/js/main.js', ['jquery'], CQD_VERSION, true);
    
    // Localize script za AJAX
    wp_localize_script('cqd-scripts', 'cqd_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cqd_ajax_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'cqd_enqueue_assets');

// ================================================
// SEKCIJA: ADMIN CSS I STYLES (VERZIJA 1.4.3)
// ================================================

function cqd_admin_styles() {
    ?>
    <style>
    /* Admin Panel Styles */
    .cqd-admin-wrap { padding: 20px; }
    .cqd-admin-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }
    .cqd-stat-box {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
    }
    .cqd-stat-box h3 {
        margin-top: 0;
        color: #666;
        font-size: 14px;
    }
    .stat-number {
        font-size: 36px;
        font-weight: bold;
        margin: 10px 0;
        color: #2271b1;
    }
    .cqd-admin-actions {
        margin: 30px 0;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .cqd-recent-activity {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin: 20px 0;
    }
    .cqd-activity-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .cqd-activity-list li {
        padding: 10px 0;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .cqd-activity-list li:last-child {
        border-bottom: none;
    }
    .cqd-activity-list .dashicons {
        margin-right: 10px;
        color: #2271b1;
    }
    .cqd-activity-list .time-ago {
        color: #666;
        font-size: 12px;
        white-space: nowrap;
    }
    .activity-content {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .activity-desc {
        font-size: 14px;
    }
    .activity-user {
        font-size: 12px;
        color: #666;
        margin-top: 2px;
    }
    
    /* Users List */
    .cqd-users-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }
    .cqd-user-card {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        display: flex;
        align-items: center;
        border: 1px solid #e9ecef;
    }
    .cqd-user-avatar {
        width: 50px;
        height: 50px;
        background: #4a90e2;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 20px;
        margin-right: 15px;
    }
    .cqd-user-info {
        flex: 1;
    }
    .cqd-user-info h3, .cqd-user-info h4 {
        margin: 0 0 5px;
        color: #333;
    }
    .cqd-user-info p {
        margin: 0;
        color: #666;
        font-size: 14px;
    }
    .cqd-user-stats {
        display: flex;
        gap: 10px;
        margin-top: 8px;
    }
    .cqd-stat {
        font-size: 12px;
        color: #777;
        background: white;
        padding: 2px 8px;
        border-radius: 10px;
    }
    .cqd-stat strong {
        color: #333;
    }
    .cqd-user-actions {
        margin-left: 10px;
    }
    
    /* Quick Links */
    .cqd-quick-links {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin: 20px 0;
    }
    .cqd-quick-links-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    .cqd-quick-link {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        text-decoration: none;
        color: #333;
        text-align: center;
        transition: all 0.3s;
    }
    .cqd-quick-link:hover {
        background: #e9ecef;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .cqd-quick-link .dashicons {
        font-size: 32px;
        width: 32px;
        height: 32px;
        margin-bottom: 10px;
        color: #2271b1;
    }
    
    /* Advanced Activity Log Styles */
    .cqd-advanced-activity {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .cqd-activity-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    .cqd-activity-stats {
        display: flex;
        gap: 10px;
    }
    .cqd-stat-badge {
        background: #f0f2f5;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 500;
    }
    .cqd-activity-filters {
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 6px;
    }
    .cqd-activity-filters select {
        min-width: 200px;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .cqd-activity-table th {
        font-weight: 600;
        background: #f8f9fa;
    }
    .cqd-activity-table td {
        vertical-align: top;
        padding: 12px 10px;
    }
    .cqd-activity-table small {
        color: #666;
        font-size: 11px;
        display: block;
        line-height: 1.4;
    }
    .cqd-activity-table code {
        background: #f1f1f1;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        font-family: 'Courier New', monospace;
        color: #333;
    }
    .cqd-pagination {
        margin-top: 20px;
        text-align: center;
        padding: 15px 0;
    }
    .cqd-pagination .page-numbers {
        display: inline-block;
        padding: 6px 12px;
        margin: 0 2px;
        border: 1px solid #ddd;
        background: white;
        text-decoration: none;
        border-radius: 4px;
    }
    .cqd-pagination .current {
        background: #2271b1;
        color: white;
        border-color: #2271b1;
    }
    .cqd-pagination a:hover {
        background: #f0f2f5;
    }
    .cqd-export-section {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #eee;
        text-align: center;
    }
    .cqd-empty-activity {
        text-align: center;
        padding: 40px 20px;
        color: #666;
        font-style: italic;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .cqd-admin-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        .cqd-users-grid {
            grid-template-columns: 1fr;
        }
        .cqd-quick-links-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .cqd-user-card {
            flex-direction: column;
            text-align: center;
        }
        .cqd-user-avatar {
            margin-right: 0;
            margin-bottom: 10px;
        }
        .cqd-user-actions {
            margin-left: 0;
            margin-top: 10px;
            width: 100%;
        }
        .cqd-activity-table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }
        .cqd-activity-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
    }
    </style>
    <?php
    
    // Inline CSS za frontend
    global $post;
    if (!is_a($post, 'WP_Post')) return;
    
    if (has_shortcode($post->post_content, 'custom_register') ||
        has_shortcode($post->post_content, 'custom_login') ||
        has_shortcode($post->post_content, 'custom_dashboard') ||
        has_shortcode($post->post_content, 'custom_stavka_page')) {
        
        echo '<style>
        /* Basic Reset */
        .cqd-form-container, .cqd-dashboard-header, .cqd-admin-panel, 
        .cqd-table-container, .cqd-form-section, .cqd-modal {
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        /* Form Styles */
        .cqd-form-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .cqd-form-container h2 {
            margin-top: 0;
            color: #333;
            text-align: center;
        }
        
        .cqd-form-group {
            margin-bottom: 20px;
        }
        
        .cqd-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .cqd-form-group input[type="text"],
        .cqd-form-group input[type="email"],
        .cqd-form-group input[type="password"],
        .cqd-form-group select,
        .cqd-form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .cqd-form-group input:focus,
        .cqd-form-group select:focus,
        .cqd-form-group textarea:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }
        
        /* Button Styles */
        .cqd-btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .cqd-btn.primary {
            background: linear-gradient(135deg, #4a90e2, #357ae8);
            color: white;
            width: 100%;
        }
        
        .cqd-btn.primary:hover {
            background: linear-gradient(135deg, #357ae8, #2a65cc);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 144, 226, 0.3);
        }
        
        .cqd-btn.secondary {
            background: #f0f2f5;
            color: #555;
        }
        
        .cqd-btn.secondary:hover {
            background: #e4e6e9;
        }
        
        .cqd-btn.logout {
            background: #ff6b6b;
            color: white;
        }
        
        .cqd-btn.logout:hover {
            background: #ff5252;
        }
        
        .cqd-btn.small {
            padding: 6px 12px;
            font-size: 14px;
        }
        
        .cqd-btn-icon {
            background: none;
            border: none;
            padding: 8px;
            margin: 0 2px;
            cursor: pointer;
            font-size: 18px;
            border-radius: 6px;
            transition: background 0.2s;
        }
        
        .cqd-btn-icon:hover {
            background: #f0f2f5;
        }
        
        .cqd-btn-icon.danger:hover {
            background: #ffebee;
            color: #f44336;
        }
        
        /* Dashboard Header */
        .cqd-dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .cqd-header-left h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        
        .cqd-subtitle {
            margin: 5px 0 0;
            color: #666;
        }
        
        /* Admin Panel */
        .cqd-admin-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #ff9800;
        }
        
        .cqd-admin-badge {
            background: #ff9800;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-right: 10px;
        }
        
        /* Stats Grid */
        .cqd-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .cqd-stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .cqd-stat-icon {
            font-size: 32px;
            margin-right: 15px;
            opacity: 0.8;
        }
        
        .cqd-stat-content h3 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        
        .cqd-stat-content p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
        }
        
        /* Table Styles */
        .cqd-table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .cqd-table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .cqd-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .cqd-table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            color: #555;
            border-bottom: 2px solid #e9ecef;
        }
        
        .cqd-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .cqd-table tr:hover {
            background: #f8f9fa;
        }
        
        .cqd-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        
        .cqd-no-image {
            width: 50px;
            height: 50px;
            background: #f8f9fa;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #999;
        }
        
        .cqd-status-badge {
            display: inline-block;
            padding: 4px 10px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .cqd-qr-thumb {
            width: 50px;
            height: 50px;
            border: 1px solid #e9ecef;
            padding: 3px;
            background: white;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .cqd-qr-thumb:hover {
            transform: scale(1.1);
        }
        
        .cqd-actions-cell {
            white-space: nowrap;
        }
        
        .cqd-action-buttons {
            display: flex;
            gap: 5px;
        }
        
        /* Form Section */
        .cqd-form-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .cqd-form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .cqd-form-row .cqd-form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        /* Modal Styles */
        .cqd-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .cqd-modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .cqd-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .cqd-modal-header h3 {
            margin: 0;
        }
        
        .cqd-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .cqd-modal-body {
            padding: 20px;
        }
        
        /* Alerts */
        .cqd-alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .cqd-alert.success {
            background: #e8f5e9;
            border-left-color: #4caf50;
            color: #2e7d32;
        }
        
        .cqd-alert.error {
            background: #ffebee;
            border-left-color: #f44336;
            color: #c62828;
        }
        
        .cqd-alert.warning {
            background: #fff3e0;
            border-left-color: #ff9800;
            color: #ef6c00;
        }
        
        /* Empty State */
        .cqd-empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            margin: 20px 0;
        }
        
        .cqd-empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        /* Public View */
        .cqd-public-view {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .cqd-public-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .cqd-public-qr {
            margin: 20px 0;
        }
        
        .cqd-public-details {
            display: flex;
            gap: 30px;
            margin: 30px 0;
        }
        
        .cqd-public-image {
            flex: 1;
        }
        
        .cqd-public-image img {
            width: 100%;
            max-width: 300px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .cqd-public-info {
            flex: 2;
        }
        
        .cqd-info-row {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .cqd-info-row:last-child {
            border-bottom: none;
        }
        
        /* Utility Classes */
        .text-center { text-align: center; }
        .text-muted { color: #666; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .cqd-form-row {
                flex-direction: column;
                gap: 20px;
            }
            .cqd-dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .cqd-header-right {
                width: 100%;
            }
            .cqd-public-details {
                flex-direction: column;
            }
            .cqd-table {
                display: block;
                overflow-x: auto;
            }
            .cqd-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .cqd-stats-grid {
                grid-template-columns: 1fr;
            }
            .cqd-form-container {
                margin: 20px auto;
                padding: 20px;
            }
        }
        </style>';
    }
}
add_action('admin_head', 'cqd_admin_styles');
add_action('wp_head', 'cqd_admin_styles');

// ================================================
// SEKCIJA: AJAX HANDLERS (VERZIJA 1.4.3)
// ================================================

function cqd_ajax_get_item() {
    // Proveri nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cqd_ajax_nonce')) {
        wp_die('Security check failed');
    }
    
    $user_id = get_current_user_id();
    $item_index = intval($_POST['item_index']);
    
    $items = get_user_meta($user_id, 'cqd_stavke', true);
    if (!is_array($items) || !isset($items[$item_index])) {
        wp_die('Item not found');
    }
    
    // Log aktivnost - direktan poziv
    cqd_log_activity('item', 'Item viewed via AJAX', 'item_index_' . $item_index);
    
    wp_send_json_success($items[$item_index]);
}
add_action('wp_ajax_cqd_get_item', 'cqd_ajax_get_item');

function cqd_ajax_update_item() {
    if (!wp_verify_nonce($_POST['nonce'], 'cqd_ajax_nonce')) {
        wp_die('Security check failed');
    }
    
    $user_id = get_current_user_id();
    $item_index = intval($_POST['item_index']);
    
    $items = get_user_meta($user_id, 'cqd_stavke', true);
    if (!is_array($items) || !isset($items[$item_index])) {
        wp_send_json_error('Item not found');
    }
    
    // Update item data
    $items[$item_index]['title'] = sanitize_text_field($_POST['title'] ?? '');
    $items[$item_index]['status'] = sanitize_text_field($_POST['status'] ?? '');
    $items[$item_index]['lokacija'] = sanitize_text_field($_POST['lokacija'] ?? '');
    $items[$item_index]['kategorija'] = sanitize_text_field($_POST['kategorija'] ?? '');
    $items[$item_index]['napomena'] = sanitize_textarea_field($_POST['napomena'] ?? '');
    $items[$item_index]['updated_at'] = current_time('mysql');
    
    update_user_meta($user_id, 'cqd_stavke', $items);
    
    // Log aktivnost - direktan poziv
    cqd_log_activity('item', 'Updated item: ' . $items[$item_index]['title'], $items[$item_index]['id']);
    
    wp_send_json_success(['message' => 'Stavka je ažurirana']);
}
add_action('wp_ajax_cqd_update_item', 'cqd_ajax_update_item');

function cqd_ajax_update_item_with_image() {
    if (!wp_verify_nonce($_POST['nonce'], 'cqd_ajax_nonce')) {
        wp_die('Security check failed');
    }
    
    $user_id = get_current_user_id();
    $item_index = intval($_POST['item_index']);
    
    $items = get_user_meta($user_id, 'cqd_stavke', true);
    if (!is_array($items) || !isset($items[$item_index])) {
        wp_send_json_error('Item not found');
    }
    
    // Update osnovnih podataka
    $items[$item_index]['title'] = sanitize_text_field($_POST['title'] ?? '');
    $items[$item_index]['status'] = sanitize_text_field($_POST['status'] ?? '');
    $items[$item_index]['lokacija'] = sanitize_text_field($_POST['lokacija'] ?? '');
    $items[$item_index]['kategorija'] = sanitize_text_field($_POST['kategorija'] ?? '');
    $items[$item_index]['napomena'] = sanitize_textarea_field($_POST['napomena'] ?? '');
    $items[$item_index]['updated_at'] = current_time('mysql');
    
    // Obrada nove slike ako je uploadovana
    if (!empty($_FILES['new_image']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $uploaded = media_handle_upload('new_image', 0);
        if (!is_wp_error($uploaded)) {
            // Obriši staru sliku ako postoji
            if (!empty($items[$item_index]['image_id'])) {
                wp_delete_attachment($items[$item_index]['image_id'], true);
            }
            
            $items[$item_index]['image_id'] = $uploaded;
            $items[$item_index]['image_url'] = wp_get_attachment_url($uploaded);
        }
    }
    
    update_user_meta($user_id, 'cqd_stavke', $items);
    
    // Log aktivnost - direktan poziv
    cqd_log_activity('item', 'Updated item with image: ' . $items[$item_index]['title'], $items[$item_index]['id']);
    
    wp_send_json_success(['message' => 'Stavka je ažurirana']);
}
add_action('wp_ajax_cqd_update_item_with_image', 'cqd_ajax_update_item_with_image');

// ================================================
// SEKCIJA: EXPORT FUNKCIONALNOST
// ================================================

function cqd_export_activities() {
    if (!current_user_can('manage_options') || !isset($_GET['export_activities'])) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cqd_activity_log';
    $format = $_GET['export_activities'];
    
    // Dohvati sve aktivnosti
    $activities = $wpdb->get_results(
        "SELECT a.*, u.user_login 
         FROM $table_name a
         LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
         ORDER BY a.created_at DESC"
    );
    
    if ($format === 'csv') {
        // CSV export
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=cqd_activities_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Datum', 'Tip', 'Opis', 'Korisnik', 'Item ID']);
        
        foreach ($activities as $activity) {
            fputcsv($output, [
                $activity->id,
                $activity->created_at,
                $activity->activity_type,
                $activity->description,
                $activity->user_login ?: 'System',
                $activity->item_id
            ]);
        }
        
        fclose($output);
        exit;
        
    } elseif ($format === 'json') {
        // JSON export
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=cqd_activities_' . date('Y-m-d') . '.json');
        
        $data = [];
        foreach ($activities as $activity) {
            $data[] = [
                'id' => $activity->id,
                'date' => $activity->created_at,
                'type' => $activity->activity_type,
                'description' => $activity->description,
                'user' => $activity->user_login ?: 'System',
                'item_id' => $activity->item_id
            ];
        }
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
add_action('admin_init', 'cqd_export_activities');

// ================================================
// SEKCIJA: JAVASCRIPT FUNKCIONALNOST
// ================================================

function cqd_inline_scripts() {
    global $post;
    if (!is_a($post, 'WP_Post')) return;
    
    if (has_shortcode($post->post_content, 'custom_dashboard')) {
        ?>
        <script>
// Globalne funkcije za Dashboard - VERZIJA 1.4.3

// 1. Preuzimanje QR koda
function cqdDownloadQR(qrUrl, fileName) {
    const link = document.createElement('a');
    link.href = qrUrl;
    link.download = (fileName || 'qr_code') + '.png';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// 2. Kontrola custom polja
function cqdToggleCustomField(selectElement, fieldType) {
    const customInputId = 'custom-' + fieldType + '-input';
    const customInput = document.getElementById(customInputId);
    
    if (selectElement.value === 'custom') {
        customInput.style.display = 'block';
        customInput.required = true;
        selectElement.disabled = true;
    } else {
        customInput.style.display = 'none';
        customInput.required = false;
        customInput.value = '';
    }
}

// 3. Export CSV
function cqdExportCSV() {
    alert('CSV export će biti poboljšan u narednoj verziji.');
    
    // Za sada koristimo jednostavan export
    const table = document.getElementById('cqd-items-table');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    // Headers
    const headers = [];
    table.querySelectorAll('th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(','));
    
    // Data rows
    for (let i = 1; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td');
        
        for (let j = 0; j < cols.length; j++) {
            // Preskoči kolonu sa slikama i QR kodom
            if (j !== 1 && j !== 5) {
                row.push('"' + cols[j].textContent.trim().replace(/"/g, '""') + '"');
            }
        }
        
        csv.push(row.join(','));
    }
    
    // Download
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'moje_stavke_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
}

// 4. Modal funkcije
function cqdOpenEditModal(itemIndex) {
    const modal = document.getElementById('cqd-edit-modal');
    const container = document.getElementById('cqd-edit-form-container');
    
    // Prikaži loading
    container.innerHTML = '<p>Učitavanje...</p>';
    modal.style.display = 'flex';
    
    // AJAX za dobijanje podataka
    jQuery.ajax({
        url: cqd_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'cqd_get_item',
            nonce: cqd_ajax.nonce,
            item_index: itemIndex
        },
        success: function(response) {
            if (response.success) {
                const item = response.data;
                container.innerHTML = cqdGenerateEditForm(item, itemIndex);
            } else {
                container.innerHTML = '<p class="cqd-alert error">Greška pri učitavanju</p>';
            }
        }
    });
}

function cqdCloseEditModal() {
    document.getElementById('cqd-edit-modal').style.display = 'none';
}

function cqdGenerateEditForm(item, itemIndex) {
    const systemStatuses = <?php echo json_encode(cqd_get_system_statuses()); ?>;
    
    let statusOptions = '<option value="">Izaberite status</option>';
    
    // Sistemski statusi
    Object.values(systemStatuses).forEach(status => {
        statusOptions += `<option value="${status}" ${item.status === status ? 'selected' : ''}>${status}</option>`;
    });

    return `
    <form id="cqd-edit-form" onsubmit="cqdSubmitEditForm(event, ${itemIndex})">
        <div class="cqd-form-group">
            <label>Naslov *</label>
            <input type="text" name="title" value="${item.title || ''}" required>
        </div>
        
        <div class="cqd-form-group">
            <label>Status</label>
            <select name="status">
                ${statusOptions}
            </select>
        </div>
        
        <div class="cqd-form-group">
            <label>Lokacija</label>
            <input type="text" name="lokacija" value="${item.lokacija || ''}">
        </div>
        
        <div class="cqd-form-group">
            <label>Kategorija</label>
            <input type="text" name="kategorija" value="${item.kategorija || ''}">
        </div>
        
        <div class="cqd-form-group">
            <label>Napomena</label>
            <textarea name="napomena" rows="3">${item.napomena || ''}</textarea>
        </div>
        
        <div class="cqd-form-actions">
            <button type="button" class="cqd-btn secondary" onclick="cqdCloseEditModal()">Otkaži</button>
            <button type="submit" class="cqd-btn primary">Sačuvaj izmene</button>
        </div>
    </form>
    `;
}

function cqdSubmitEditForm(event, itemIndex) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    jQuery.ajax({
        url: cqd_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'cqd_update_item',
            nonce: cqd_ajax.nonce,
            item_index: itemIndex,
            title: formData.get('title'),
            status: formData.get('status'),
            lokacija: formData.get('lokacija'),
            kategorija: formData.get('kategorija'),
            napomena: formData.get('napomena')
        },
        success: function(response) {
            if (response.success) {
                alert('✅ Stavka je uspešno ažurirana!');
                location.reload();
            } else {
                alert('❌ Greška pri ažuriranju: ' + response.data);
            }
        }
    });
}

// 5. Image preview
document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('item-image');
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            const preview = document.getElementById('image-preview');
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 200px; margin-top: 10px; border-radius: 6px;">';
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    // Inicijalizacija custom polja
    const statusSelect = document.getElementById('status-select');
    const locationSelect = document.getElementById('location-select');
    const categorySelect = document.getElementById('category-select');
    
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            cqdToggleCustomField(this, 'status');
        });
    }
    
    if (locationSelect) {
        locationSelect.addEventListener('change', function() {
            cqdToggleCustomField(this, 'location');
        });
    }
    
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            cqdToggleCustomField(this, 'category');
        });
    }
});

// Klik van modala zatvara modal
window.onclick = function(event) {
    const modal = document.getElementById('cqd-edit-modal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>
        <?php
    }
}
add_action('wp_footer', 'cqd_inline_scripts');

// ================================================
// SEKCIJA: PLUGIN META LINKOVI
// ================================================

function cqd_plugin_meta_links($links, $file) {
    if ($file === CQD_PLUGIN_BASENAME) {
        // Settings link
        $settings_link = '<a href="' . admin_url('admin.php?page=cqd-settings') . '">Podešavanja</a>';
        array_unshift($links, $settings_link);
        
        // Documentation link
        $docs_link = '<a href="' . admin_url('admin.php?page=cqd-dashboard') . '" style="font-weight:bold;">📊 Dashboard</a>';
        array_unshift($links, $docs_link);
    }
    return $links;
}
add_filter('plugin_row_meta', 'cqd_plugin_meta_links', 10, 2);

function cqd_plugin_action_links($actions) {
    $actions[] = '<a href="' . admin_url('admin.php?page=cqd-settings') . '">Podešavanja</a>';
    $actions[] = '<a href="' . admin_url('admin.php?page=cqd-dashboard') . '" style="font-weight:bold;">Dashboard</a>';
    return $actions;
}
add_filter('plugin_action_links_' . CQD_PLUGIN_BASENAME, 'cqd_plugin_action_links');

// ================================================
// SEKCIJA: CRON JOBS ZA BACKUP
// ================================================

function cqd_setup_cron_jobs() {
    if (!wp_next_scheduled('cqd_daily_backup')) {
        wp_schedule_event(time(), 'daily', 'cqd_daily_backup');
    }
}
add_action('init', 'cqd_setup_cron_jobs');

function cqd_daily_backup_task() {
    $backup_schedule = get_option('cqd_backup_schedule', 'weekly');
    
    if ($backup_schedule === 'daily') {
        // Log backup pokušaj - direktan poziv
        cqd_log_activity('backup', 'Daily backup task started', 'system');
        // Ovde će biti implementiran backup u verziji 1.8
    }
}
add_action('cqd_daily_backup', 'cqd_daily_backup_task');

// ================================================
// SEKCIJA: KREIRAJ POTREBNE FOLDERE
// ================================================

function cqd_create_folders_on_activation() {
    $folders = [
        CQD_PLUGIN_DIR . 'assets',
        CQD_PLUGIN_DIR . 'assets/css',
        CQD_PLUGIN_DIR . 'assets/js',
        CQD_PLUGIN_DIR . 'assets/images',
        CQD_PLUGIN_DIR . 'exports',
        CQD_PLUGIN_DIR . 'backups'
    ];
    
    foreach ($folders as $folder) {
        if (!file_exists($folder)) {
            wp_mkdir_p($folder);
            // Dodaj .htaccess za zaštitu
            if (strpos($folder, 'exports') !== false || strpos($folder, 'backups') !== false) {
                file_put_contents($folder . '/.htaccess', 'Deny from all');
            }
        }
    }
}
register_activation_hook(__FILE__, 'cqd_create_folders_on_activation');

// ================================================
// SEKCIJA: DEBUG FUNKCIJE
// ================================================

function cqd_debug_activity_system() {
    // Pristup samo administratorima
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Provera GET parametra
    if (isset($_GET['cqd_debug'])) {
        echo '<div class="notice notice-info" style="padding: 15px; margin: 20px 0;">';
        echo '<h3>CQD Debug Informacije - Verzija 1.4.3</h3>';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cqd_activity_log';
        
        // 1. Provera da li tabela postoji
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        echo '<p><strong>Tabela cqd_activity_log postoji:</strong> ' . ($table_exists ? '✅ DA' : '❌ NE') . '</p>';
        
        // 2. Struktura tabele
        if ($table_exists) {
            $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
            echo '<p><strong>Kolone u tabeli:</strong></p>';
            echo '<ul>';
            foreach ($columns as $col) {
                echo '<li>' . $col->Field . ' (' . $col->Type . ')</li>';
            }
            echo '</ul>';
            
            // 3. Broj redova
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            echo '<p><strong>Ukupno logova:</strong> ' . $count . '</p>';
            
            // 4. Poslednjih 5 logova
            $recent_logs = $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5"
            );
            
            if ($recent_logs) {
                echo '<p><strong>Poslednjih 5 aktivnosti:</strong></p>';
                echo '<table style="width: 100%; border-collapse: collapse;">';
                echo '<tr style="background: #f1f1f1;">';
                echo '<th style="padding: 8px; border: 1px solid #ddd;">ID</th>';
                echo '<th style="padding: 8px; border: 1px solid #ddd;">Datum</th>';
                echo '<th style="padding: 8px; border: 1px solid #ddd;">Tip</th>';
                echo '<th style="padding: 8px; border: 1px solid #ddd;">Opis</th>';
                echo '<th style="padding: 8px; border: 1px solid #ddd;">Korisnik</th>';
                echo '</tr>';
                
                foreach ($recent_logs as $log) {
                    $user = $log->user_id ? get_user_by('id', $log->user_id) : null;
                    echo '<tr>';
                    echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $log->id . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $log->created_at . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $log->activity_type . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $log->description . '</td>';
                    echo '<td style="padding: 8px; border: 1px solid #ddd;">' . ($user ? $user->user_login : 'Sistem') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>⚠️ Nema logova u tabeli</p>';
            }
        } else {
            echo '<p><strong>Pokušaj kreiranja tabele:</strong> ';
            
            // Pokušaj da se kreira tabela
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                activity_type varchar(50) NOT NULL,
                description text NOT NULL,
                item_id varchar(100) DEFAULT NULL,
                ip_address varchar(45) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY activity_type (activity_type)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $result = dbDelta($sql);
            
            if (is_array($result)) {
                echo 'Tabela kreirana ili ažurirana</p>';
                echo '<pre>' . print_r($result, true) . '</pre>';
            } else {
                echo 'Greška pri kreiranju</p>';
            }
        }
        
        // 5. Test logovanja
        echo '<p><strong>Test logovanja:</strong> ';
        $test_result = cqd_log_activity('test', 'Test log iz debug funkcije', 'debug_' . time());
        echo $test_result ? '✅ Uspešno' : '❌ Neuspešno';
        echo '</p>';
        
        // 6. Provera da li se funkcija poziva
        echo '<p><strong>cqd_log_activity se poziva pri:</strong></p>';
        echo '<ul>';
        echo '<li>Registraciji: ' . (has_action('user_register') ? '✅ DA' : '❌ NE') . '</li>';
        echo '<li>Prijavi: ' . (has_action('wp_login') ? '✅ DA' : '❌ NE') . '</li>';
        echo '<li>AJAX dobavljanju stavke: ' . (has_action('wp_ajax_cqd_get_item') ? '✅ DA' : '❌ NE') . '</li>';
        echo '<li>AJAX update-u stavke: ' . (has_action('wp_ajax_cqd_update_item') ? '✅ DA' : '❌ NE') . '</li>';
        echo '</ul>';
        
        echo '</div>';
    }
}
add_action('admin_notices', 'cqd_debug_activity_system');

// ================================================
// KRAJ PLUGIN-A - VERZIJA 1.4.3
// ================================================

// Uključi dodatne fajlove ako postoje
if (file_exists(CQD_PLUGIN_DIR . 'includes/class-core.php')) {
    require_once CQD_PLUGIN_DIR . 'includes/class-core.php';
}
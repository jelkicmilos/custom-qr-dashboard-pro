<?php
/**
 * Admin functionality for Custom QR Dashboard Pro
 * 
 * @package CustomQrDashboardPro
 * @subpackage Admin
 * @since 1.5.0
 */

// Exit if accessed directly
defined('ABSPATH') or die('No direct access!');

/**
 * Add admin menu
 */
function cqd_add_admin_menu() {
    // Main menu
    add_menu_page(
        'QR Dashboard Pro',
        'QR Dashboard',
        'manage_options',
        'cqd-dashboard',
        'cqd_admin_dashboard_page',
        'dashicons-qrcode',
        30
    );
    
    // Submenu items
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

/**
 * Admin dashboard page
 */
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

/**
 * Admin users page
 */
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

/**
 * Admin all items page
 */
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

/**
 * Admin backup page
 */
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
                <a href="<?php echo admin_url('export.php'); ?>" class="button button-primary"> WordPress Export </a>
                <a href="<?php echo admin_url('plugin-editor.php?plugin=' . CQD_PLUGIN_BASENAME); ?>" class="button"> Backup koda plugin-a </a>
            </p>
        </div>
    </div>
    <?php
}

/**
 * Admin settings page
 */
function cqd_admin_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Nemate dozvolu za pristup ovoj strani.'));
    }
    
    // Process settings form
    if (isset($_POST['cqd_save_settings']) && wp_verify_nonce($_POST['cqd_settings_nonce'], 'cqd_save_settings')) {
        // Save settings
        update_option('cqd_items_per_page', intval($_POST['items_per_page']));
        update_option('cqd_qr_quality', sanitize_text_field($_POST['qr_quality']));
        update_option('cqd_backup_schedule', sanitize_text_field($_POST['backup_schedule']));
        
        echo '<div class="notice notice-success"><p>Podešavanja su sačuvana.</p></div>';
        
        // Log activity
        cqd_log_activity('settings', 'Admin updated plugin settings', 'system');
    }
    
    // Load current settings
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
                            <input type="number" id="items_per_page" name="items_per_page" value="<?php echo esc_attr($items_per_page); ?>" min="5" max="100" class="small-text">
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

/**
 * Display recent activity
 */
function cqd_display_recent_activity() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cqd_activity_log';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if (!$table_exists) {
        // Table doesn't exist - show default message
        echo '<p>Nema nedavne aktivnosti. Tabela log-a će biti kreirana pri prvoj akciji.</p>';
        return;
    }
    
    // Get last 10 activities
    $activities = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT a.*, u.user_login FROM $table_name a LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID ORDER BY a.created_at DESC LIMIT %d",
            10
        )
    );
    
    if (empty($activities)) {
        // Show default activities
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

/**
 * Display advanced activity log
 */
function cqd_display_advanced_activity_log() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cqd_activity_log';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if (!$table_exists) {
        echo '<div class="notice notice-warning"><p>Tabela za log aktivnosti nije kreirana. Aktivirajte plugin ponovo ili dodajte neku aktivnost.</p></div>';
        return;
    }
    
    // Pagination parameters
    $per_page = 15;
    $current_page = isset($_GET['activity_page']) ? max(1, intval($_GET['activity_page'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Filters
    $where_conditions = [];
    $where_sql = '';
    if (isset($_GET['activity_type']) && !empty($_GET['activity_type'])) {
        $activity_type = sanitize_text_field($_GET['activity_type']);
        $where_conditions[] = $wpdb->prepare("a.activity_type = %s", $activity_type);
    }
    
    if (!empty($where_conditions)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Total activities count
    $total_activities = $wpdb->get_var("SELECT COUNT(*) FROM $table_name a $where_sql");
    $total_pages = ceil($total_activities / $per_page);
    
    // Get activities with pagination
    $activities = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, u.user_login, u.user_email FROM $table_name a LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID $where_sql ORDER BY a.created_at DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
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

/**
 * Export activities
 */
function cqd_export_activities() {
    if (!current_user_can('manage_options') || !isset($_GET['export_activities'])) {
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cqd_activity_log';
    $format = $_GET['export_activities'];
    
    // Get all activities
    $activities = $wpdb->get_results(
        "SELECT a.*, u.user_login FROM $table_name a LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID ORDER BY a.created_at DESC"
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

// Hook for export functionality
add_action('admin_init', 'cqd_export_activities');

<?php
/**
 * Frontend dashboard functionality for Custom QR Dashboard Pro
 * 
 * @package CustomQrDashboardPro
 * @subpackage Frontend
 * @since 1.5.0
 */

// Exit if accessed directly
defined('ABSPATH') or die('No direct access!');

/**
 * Dashboard shortcode
 */
function cqd_dashboard_shortcode() {
    if (!is_user_logged_in()) {
        wp_redirect(site_url('/login'));
        exit;
    }
    
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $is_admin = current_user_can('administrator');
    
    $output = '';
    
    // Admin panel (only for administrators)
    if ($is_admin) {
        $output .= cqd_render_admin_panel($current_user_id);
        
        // If admin is viewing another user, change context
        if (isset($_GET['user']) && is_numeric($_GET['user'])) {
            $selected_user_id = intval($_GET['user']);
            $selected_user = get_user_by('id', $selected_user_id);
            if ($selected_user && $selected_user_id != 1) {
                $current_user_id = $selected_user_id;
                $current_user = $selected_user;
            }
        }
    }
    
    // Dashboard header
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
    
    // Process actions (add, delete, edit)
    $action_result = cqd_process_dashboard_actions($current_user_id);
    if ($action_result) {
        $output .= $action_result;
    }
    
    // Statistics
    $stavke = get_user_meta($current_user_id, 'cqd_stavke', true);
    if (!is_array($stavke)) $stavke = [];
    $output .= cqd_render_stats($stavke);
    
    // Items table
    if (!empty($stavke)) {
        $output .= cqd_render_items_table($stavke, $current_user_id, $is_admin);
    } else {
        $output .= '<div class="cqd-empty-state">';
        $output .= '<div class="cqd-empty-icon">📭</div>';
        $output .= '<h3>Nema stavki</h3>';
        $output .= '<p>Dodajte svoju prvu stavku kako biste počeli da koristite sistem.</p>';
        $output .= '</div>';
    }
    
    // Form for adding/editing items
    $output .= cqd_render_item_form($current_user_id);
    
    // Edit modal (HTML for JavaScript)
    $output .= cqd_render_edit_modal();
    
    return $output;
}

/**
 * Render admin panel
 */
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

/**
 * Process dashboard actions
 */
function cqd_process_dashboard_actions($user_id) {
    $output = '';
    $nonce_action = 'cqd_dashboard_' . $user_id;
    $redirect_needed = false;
    
    // Add new item
    if (isset($_POST['cqd_add_item']) && wp_verify_nonce($_POST['cqd_nonce'], $nonce_action)) {
        // Process custom fields
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
        
        // Upload image
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
        
        // Save item
        $items = get_user_meta($user_id, 'cqd_stavke', true);
        if (!is_array($items)) $items = [];
        $items[] = $new_item;
        update_user_meta($user_id, 'cqd_stavke', $items);
        
        // Log activity
        cqd_log_activity('item', 'Item added: ' . $new_item['title'], $new_item['id']);
        
        // Set session message
        $_SESSION['cqd_message'] = [
            'type' => 'success',
            'text' => '✅ Stavka "' . esc_html($new_item['title']) . '" je uspešno dodata!'
        ];
        
        $redirect_needed = true;
    }
    
    // Delete item
    if (isset($_POST['cqd_delete_item']) && isset($_POST['item_index']) && wp_verify_nonce($_POST['cqd_nonce'], $nonce_action)) {
        $index = intval($_POST['item_index']);
        $items = get_user_meta($user_id, 'cqd_stavke', true);
        
        if (is_array($items) && isset($items[$index])) {
            $deleted_title = $items[$index]['title'];
            unset($items[$index]);
            $items = array_values($items);
            update_user_meta($user_id, 'cqd_stavke', $items);
            
            // Log activity
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

/**
 * Render statistics
 */
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

/**
 * Render items table
 */
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
        
        // Download QR button
        $output .= '<button class="cqd-btn-icon" onclick="cqdDownloadQR(\'' . esc_url($qr_url) . '\', \'' . esc_attr($item['title']) . '\')" title="Preuzmi QR kod">';
        $output .= '📥';
        $output .= '</button>';
        
        // Edit button
        $output .= '<button class="cqd-btn-icon" onclick="cqdOpenEditModal(' . $index . ')" title="Izmeni stavku">';
        $output .= '✏️';
        $output .= '</button>';
        
        // Delete button
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

/**
 * Render item form
 */
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
    
    // System statuses
    foreach ($system_statuses as $key => $label) {
        $output .= '<option value="' . esc_attr($label) . '">' . esc_html($label) . '</option>';
    }
    
    // User statuses
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
    
    // Default locations
    $default_locations = ['Kancelarija', 'Magacin', 'Kuća', 'Skladiste', 'Radnja'];
    foreach ($default_locations as $location) {
        $output .= '<option value="' . esc_attr($location) . '">' . esc_html($location) . '</option>';
    }
    
    // User locations
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
    
    // Default categories
    $default_categories = ['Elektronika', 'Oprema', 'Alat', 'Nameštaj', 'Vozilo', 'Dokument'];
    foreach ($default_categories as $category) {
        $output .= '<option value="' . esc_attr($category) . '">' . esc_html($category) . '</option>';
    }
    
    // User categories
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

/**
 * Render edit modal
 */
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

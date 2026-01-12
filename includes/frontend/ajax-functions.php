<?php
/**
 * AJAX functionality for Custom QR Dashboard Pro
 * 
 * @package CustomQrDashboardPro
 * @subpackage Frontend
 * @since 1.5.0
 */

// Exit if accessed directly
defined('ABSPATH') or die('No direct access!');

/**
 * AJAX get item
 */
function cqd_ajax_get_item() {
    // Check nonce
    if (!wp_verify_nonce($_POST['nonce'], 'cqd_ajax_nonce')) {
        wp_die('Security check failed');
    }
    
    $user_id = get_current_user_id();
    $item_index = intval($_POST['item_index']);
    $items = get_user_meta($user_id, 'cqd_stavke', true);
    
    if (!is_array($items) || !isset($items[$item_index])) {
        wp_die('Item not found');
    }
    
    // Log activity
    cqd_log_activity('item', 'Item viewed via AJAX', 'item_index_' . $item_index);
    
    wp_send_json_success($items[$item_index]);
}

/**
 * AJAX update item
 */
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
    
    // Log activity
    cqd_log_activity('item', 'Updated item: ' . $items[$item_index]['title'], $items[$item_index]['id']);
    
    wp_send_json_success(['message' => 'Stavka je ažurirana']);
}

/**
 * AJAX update item with image
 */
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
    
    // Update basic data
    $items[$item_index]['title'] = sanitize_text_field($_POST['title'] ?? '');
    $items[$item_index]['status'] = sanitize_text_field($_POST['status'] ?? '');
    $items[$item_index]['lokacija'] = sanitize_text_field($_POST['lokacija'] ?? '');
    $items[$item_index]['kategorija'] = sanitize_text_field($_POST['kategorija'] ?? '');
    $items[$item_index]['napomena'] = sanitize_textarea_field($_POST['napomena'] ?? '');
    $items[$item_index]['updated_at'] = current_time('mysql');
    
    // Process new image if uploaded
    if (!empty($_FILES['new_image']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $uploaded = media_handle_upload('new_image', 0);
        if (!is_wp_error($uploaded)) {
            // Delete old image if exists
            if (!empty($items[$item_index]['image_id'])) {
                wp_delete_attachment($items[$item_index]['image_id'], true);
            }
            
            $items[$item_index]['image_id'] = $uploaded;
            $items[$item_index]['image_url'] = wp_get_attachment_url($uploaded);
        }
    }
    
    update_user_meta($user_id, 'cqd_stavke', $items);
    
    // Log activity
    cqd_log_activity('item', 'Updated item with image: ' . $items[$item_index]['title'], $items[$item_index]['id']);
    
    wp_send_json_success(['message' => 'Stavka je ažurirana']);
}

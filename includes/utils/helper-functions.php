<?php
/**
 * Helper functions for Custom QR Dashboard Pro
 * 
 * @package CustomQrDashboardPro
 * @subpackage Utils
 * @since 1.5.0
 */

// Exit if accessed directly
defined('ABSPATH') or die('No direct access!');

/**
 * Get system statuses
 * 
 * @return array
 */
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

/**
 * Get total items count across all users
 * 
 * @return int
 */
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

/**
 * Get total QR count
 * 
 * @return int
 */
function cqd_get_total_qr_count() {
    return cqd_get_total_items_count();
}

/**
 * Get user IP address
 * 
 * @return string
 */
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

/**
 * Get activity icon based on activity type
 * 
 * @param string $activity_type
 * @return string
 */
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

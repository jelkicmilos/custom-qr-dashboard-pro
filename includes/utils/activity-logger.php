<?php
/**
 * Activity logging functionality for Custom QR Dashboard Pro
 * 
 * @package CustomQrDashboardPro
 * @subpackage Utils
 * @since 1.5.0
 */

// Exit if accessed directly
defined('ABSPATH') or die('No direct access!');

/**
 * Log activity to database
 * 
 * @param string $type
 * @param string $description
 * @param string|null $item_id
 * @return bool
 */
function cqd_log_activity($type, $description, $item_id = null) {
    error_log('CQD LOG START: ' . $description);
    global $wpdb;
    $table_name = $wpdb->prefix . 'cqd_activity_log';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    error_log('CQD TABLE CHECK: ' . ($table_exists ? 'EXISTS' : 'MISSING'));
    
    if (!$table_exists) {
        error_log('CQD ERROR: Table does not exist! Creating...');
        // Create table immediately
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
        
        // Check again
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

/**
 * Check and create activity table if needed
 */
function cqd_check_activity_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cqd_activity_log';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        // Create table immediately
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
        
        // Add some test data only if admin
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

// Hook to check table on init
add_action('init', 'cqd_check_activity_table');

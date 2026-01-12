<?php
/**
 * Module loader for Custom QR Dashboard Pro
 * 
 * @package CustomQrDashboardPro
 * @subpackage Modules
 * @since 1.5.0
 */

// Exit if accessed directly
defined('ABSPATH') or die('No direct access!');

/**
 * Load module based on user settings or system configuration
 * 
 * @param string $module_name
 * @return bool
 */
function cqd_load_module($module_name) {
    $module_file = CQD_PLUGIN_DIR . 'includes/modules/' . $module_name . '.php';
    
    if (file_exists($module_file)) {
        require_once $module_file;
        return true;
    }
    
    return false;
}

/**
 * Get available modules
 * 
 * @return array
 */
function cqd_get_available_modules() {
    return [
        'asset_management' => [
            'name' => 'Asset Management',
            'description' => 'Classic asset management system for companies',
            'file' => 'asset-management.php'
        ],
        'personal_inventory' => [
            'name' => 'Personal Inventory',
            'description' => 'Personal inventory tracking for end users',
            'file' => 'personal-inventory.php'
        ],
        'specialized_qr' => [
            'name' => 'Specialized QR System',
            'description' => 'Specialized QR code system with custom messages',
            'file' => 'specialized-qr.php'
        ]
    ];
}

/**
 * Get user module preference
 * 
 * @param int $user_id
 * @return string
 */
function cqd_get_user_module_preference($user_id) {
    return get_user_meta($user_id, 'cqd_module_preference', true) ?: 'asset_management';
}

/**
 * Set user module preference
 * 
 * @param int $user_id
 * @param string $module
 * @return bool
 */
function cqd_set_user_module_preference($user_id, $module) {
    $available_modules = cqd_get_available_modules();
    
    if (array_key_exists($module, $available_modules)) {
        return update_user_meta($user_id, 'cqd_module_preference', $module);
    }
    
    return false;
}

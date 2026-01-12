<?php
/**
 * Asset Management Module for Custom QR Dashboard Pro
 * 
 * @package CustomQrDashboardPro
 * @subpackage Modules
 * @since 1.5.0
 */

// Exit if accessed directly
defined('ABSPATH') or die('No direct access!');

/**
 * Initialize Asset Management module
 */
function cqd_init_asset_management_module() {
    // Add module-specific functionality here
    add_action('cqd_dashboard_after_stats', 'cqd_asset_management_dashboard_stats');
    add_action('cqd_dashboard_after_items', 'cqd_asset_management_dashboard_items');
}

/**
 * Display asset management specific statistics
 */
function cqd_asset_management_dashboard_stats() {
    $user_id = get_current_user_id();
    $items = get_user_meta($user_id, 'cqd_stavke', true);
    
    if (!is_array($items)) $items = [];
    
    // Calculate asset-specific stats
    $total_value = 0;
    $warranty_items = 0;
    $maintenance_items = 0;
    
    foreach ($items as $item) {
        // Add value calculation if item has value field
        if (isset($item['value']) && is_numeric($item['value'])) {
            $total_value += floatval($item['value']);
        }
        
        // Count warranty items
        if (isset($item['status']) && strpos($item['status'], 'garanciji') !== false) {
            $warranty_items++;
        }
        
        // Count maintenance items
        if (isset($item['status']) && strpos($item['status'], 'servisu') !== false) {
            $maintenance_items++;
        }
    }
    
    echo '<div class="cqd-module-stats">';
    echo '<h3>📈 Asset Management Statistics</h3>';
    echo '<div class="cqd-stats-grid">';
    
    echo '<div class="cqd-stat-card">';
    echo '<div class="cqd-stat-icon">💰</div>';
    echo '<div class="cqd-stat-content">';
    echo '<h3>' . cqd_format_currency($total_value) . '</h3>';
    echo '<p>Ukupna vrijednost</p>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="cqd-stat-card">';
    echo '<div class="cqd-stat-icon">🔧</div>';
    echo '<div class="cqd-stat-content">';
    echo '<h3>' . $maintenance_items . '</h3>';
    echo '<p>U servisu</p>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="cqd-stat-card">';
    echo '<div class="cqd-stat-icon">📅</div>';
    echo '<div class="cqd-stat-content">';
    echo '<h3>' . $warranty_items . '</h3>';
    echo '<p>U garanciji</p>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
}

/**
 * Display asset management specific items
 */
function cqd_asset_management_dashboard_items() {
    // Add asset management specific item display
    echo '<div class="cqd-module-items">';
    echo '<h3>🏢 Asset Management Items</h3>';
    // Additional asset management specific content can be added here
    echo '</div>';
}

/**
 * Format currency
 * 
 * @param float $amount
 * @return string
 */
function cqd_format_currency($amount) {
    return number_format($amount, 2, ',', '.') . ' RSD';
}

// Initialize the module
cqd_init_asset_management_module();

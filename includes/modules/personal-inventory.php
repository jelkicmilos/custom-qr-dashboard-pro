<?php
/**
 * Personal Inventory Module for Custom QR Dashboard Pro
 * 
 * @package CustomQrDashboardPro
 * @subpackage Modules
 * @since 1.5.0
 */

// Exit if accessed directly
defined('ABSPATH') or die('No direct access!');

/**
 * Initialize Personal Inventory module
 */
function cqd_init_personal_inventory_module() {
    // Add module-specific functionality here
    add_action('cqd_dashboard_after_stats', 'cqd_personal_inventory_dashboard_stats');
    add_action('cqd_dashboard_after_items', 'cqd_personal_inventory_dashboard_items');
}

/**
 * Display personal inventory specific statistics
 */
function cqd_personal_inventory_dashboard_stats() {
    $user_id = get_current_user_id();
    $items = get_user_meta($user_id, 'cqd_stavke', true);
    
    if (!is_array($items)) $items = [];
    
    // Calculate personal inventory specific stats
    $collections = 0;
    $tools = 0;
    $electronics = 0;
    
    foreach ($items as $item) {
        // Count collections
        if (isset($item['kategorija']) && in_array($item['kategorija'], ['Kolekcija', 'Kolekcionarski predmeti'])) {
            $collections++;
        }
        
        // Count tools
        if (isset($item['kategorija']) && in_array($item['kategorija'], ['Alat', 'Alati'])) {
            $tools++;
        }
        
        // Count electronics
        if (isset($item['kategorija']) && in_array($item['kategorija'], ['Elektronika', 'Elektronski uređaji'])) {
            $electronics++;
        }
    }
    
    echo '<div class="cqd-module-stats">';
    echo '<h3>🏠 Personal Inventory Statistics</h3>';
    echo '<div class="cqd-stats-grid">';
    
    echo '<div class="cqd-stat-card">';
    echo '<div class="cqd-stat-icon">📚</div>';
    echo '<div class="cqd-stat-content">';
    echo '<h3>' . $collections . '</h3>';
    echo '<p>Kolekcije</p>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="cqd-stat-card">';
    echo '<div class="cqd-stat-icon">🔧</div>';
    echo '<div class="cqd-stat-content">';
    echo '<h3>' . $tools . '</h3>';
    echo '<p>Alati</p>';
    echo '</div>';
    echo '</div>';
    
    echo '<div class="cqd-stat-card">';
    echo '<div class="cqd-stat-icon">💻</div>';
    echo '<div class="cqd-stat-content">';
    echo '<h3>' . $electronics . '</h3>';
    echo '<p>Elektronika</p>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
}

/**
 * Display personal inventory specific items
 */
function cqd_personal_inventory_dashboard_items() {
    // Add personal inventory specific item display
    echo '<div class="cqd-module-items">';
    echo '<h3>👤 Personal Inventory Items</h3>';
    // Additional personal inventory specific content can be added here
    echo '</div>';
}

// Initialize the module
cqd_init_personal_inventory_module();

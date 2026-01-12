<?php
/**
 * Shortcode functionality for Custom QR Dashboard Pro
 * 
 * @package CustomQrDashboardPro
 * @subpackage Frontend
 * @since 1.5.0
 */

// Exit if accessed directly
defined('ABSPATH') or die('No direct access!');

/**
 * Register shortcode
 */
function cqd_register_shortcode() {
    if (is_user_logged_in()) {
        return '<p>Već ste ulogovani. <a href="' . site_url('/moj-dashboard') . '">Idite na Dashboard</a></p>';
    }
    
    $output = '';
    
    if (isset($_POST['cqd_register'])) {
        // Nonce verification
        if (!wp_verify_nonce($_POST['cqd_register_nonce'], 'cqd_register_action')) {
            $output .= '<div class="cqd-alert error">Greška u bezbednosti!</div>';
        } else {
            $username = sanitize_user($_POST['username'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $errors = [];
            
            // Validation
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
            
            // Registration
            if (empty($errors)) {
                $user_id = wp_create_user($username, $password, $email);
                if (!is_wp_error($user_id)) {
                    // Add default user statuses
                    $default_user_statuses = [
                        'u_garanciji' => '📦 U garanciji',
                        'na_lageru' => '📊 Na lageru',
                        'u_upotrebi' => '🔄 U upotrebi',
                        'za_zamenu' => '🔄 Za zamenu'
                    ];
                    update_user_meta($user_id, 'cqd_custom_statuses', $default_user_statuses);
                    
                    // Log activity
                    cqd_log_activity('register', 'New user registered: ' . $username, 'user_' . $user_id);
                    
                    // Auto login
                    wp_set_current_user($user_id, $username);
                    wp_set_auth_cookie($user_id);
                    
                    $output .= '<div class="cqd-alert success">✅ Registracija uspešna! Preusmeravam...</div>';
                    $output .= '<script>setTimeout(() => window.location.href = "' . site_url('/moj-dashboard') . '", 1000);</script>';
                } else {
                    $errors[] = 'Greška: ' . $user_id->get_error_message();
                }
            }
            
            // Display errors
            foreach ($errors as $error) {
                $output .= '<div class="cqd-alert error">' . esc_html($error) . '</div>';
            }
        }
    }
    
    // Registration form
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

/**
 * Login shortcode
 */
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
                // Log activity
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

/**
 * Public item page shortcode
 */
function cqd_stavka_page_shortcode() {
    if (!isset($_GET['id'])) {
        return '<div class="cqd-error">❌ Stavka nije pronađena.</div>';
    }
    
    $item_id = sanitize_text_field($_GET['id']);
    $output = '';
    
    // Find item in all users
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
    
    // Log view
    cqd_log_activity('item', 'QR code scanned for item: ' . ($found_item['title'] ?? $item_id), $item_id);
    
    // Public item view
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

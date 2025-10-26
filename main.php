<?php
/**
 * Plugin Name: Commission System
 * Description: WordPress WooCommerce Commission System Plugin
 * Version: 1.0.0
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('COMMISSION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('COMMISSION_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Create tables on plugin activation
register_activation_hook(__FILE__, 'commission_create_tables');

// Include other files
require_once COMMISSION_PLUGIN_PATH . 'table.php';
require_once COMMISSION_PLUGIN_PATH . 'admin-page.php';
require_once COMMISSION_PLUGIN_PATH . 'api.php';
require_once COMMISSION_PLUGIN_PATH . 'reports-extension.php';
require_once COMMISSION_PLUGIN_PATH . 'frontend-reports.php';
// Note: block-checkout.php no longer needed as we use WooCommerce native coupons

// Initialize plugin
add_action('plugins_loaded', 'commission_init');

function commission_init() {
    // Load text domain
    load_plugin_textdomain('commission-system', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Register scripts and styles
    add_action('wp_enqueue_scripts', 'commission_enqueue_scripts');
    add_action('admin_enqueue_scripts', 'commission_admin_enqueue_scripts');
    
    // Add WooCommerce hooks only if WooCommerce is active
    if (class_exists('WooCommerce')) {
        // Hook into WooCommerce coupon usage to detect commission coupons
        add_action('woocommerce_applied_coupon', 'track_commission_coupon_usage');
        
        // Save commission coupon data when order is processed
        add_action('woocommerce_checkout_order_processed', 'save_commission_coupon_to_order');
        
        // Process commission only when WooCommerce order is completed
        add_action('woocommerce_order_status_completed', 'process_commission_on_order_complete');
    }
}

function commission_enqueue_scripts() {
    wp_enqueue_script('commission-js', COMMISSION_PLUGIN_URL . 'js.js', array('jquery'), '1.0.0', true);
    wp_localize_script('commission-js', 'commission_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('commission_nonce')
    ));
}

function commission_admin_enqueue_scripts() {
    wp_enqueue_script('commission-admin-js', COMMISSION_PLUGIN_URL . 'js.js', array('jquery'), '1.0.0', true);
    wp_localize_script('commission-admin-js', 'commission_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('commission_nonce')
    ));
}

function process_commission_on_order_complete($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    // Check if commission already processed to avoid duplicates
    global $wpdb;
    $records_table = $wpdb->prefix . 'commission_records';
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $records_table WHERE order_id = %d",
        $order_id
    ));
    
    if ($existing) {
        return; // Already processed
    }
    
    // Check if commission coupon was used (stored in order meta)
    $commission_coupon = get_post_meta($order_id, '_commission_coupon_used', true);
    
    if (!$commission_coupon) {
        // Check WooCommerce coupons used in this order
        $coupons_used = $order->get_coupon_codes();
        $found_commission_coupon = false;
        
        foreach ($coupons_used as $coupon_code) {
            // Check if this is a commission system coupon
            $coupon_data = get_commission_coupon_data($coupon_code);
            if ($coupon_data) {
                // Save commission coupon data to order meta
                update_post_meta($order_id, '_commission_coupon_used', $coupon_code);
                update_post_meta($order_id, '_commission_coupon_data', $coupon_data);
                
                $order->add_order_note("Commission coupon detected: $coupon_code", false, true);
                
                $commission_coupon = $coupon_code;
                $found_commission_coupon = true;
                break;
            }
        }
        
        if (!$found_commission_coupon) {
            // Add a note that no commission coupon was used
            $order->add_order_note("No commission coupon was used for this order.", false, true);
            return;
        }
    }
    
    $coupon_data = get_commission_coupon_data($commission_coupon);
    if ($coupon_data) {
        // Process downline relationship
        $customer_email = $order->get_billing_email();
        add_downline($coupon_data['holder_email'], $customer_email);
        
        // Calculate and record commission
        $order_total = $order->get_total();
        $commission_result = calculate_and_record_commission($coupon_data, $order_total, $order_id);
        
        // Add order note with commission details
        if ($commission_result) {
            add_commission_order_note($order, $coupon_data, $order_total, $commission_result);
        }
        
        // Log for debugging
        error_log("Commission processed for Order ID: $order_id, Coupon: $commission_coupon, Total: $order_total");
    } else {
        // If no coupon data found, add a note about it
        $order->add_order_note("Commission processing attempted but no valid coupon data found for coupon: $commission_coupon", false, true);
    }
}

function get_commission_coupon_data($coupon_code) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_coupons';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE coupon_code = %s",
        $coupon_code
    ), ARRAY_A);
}

function add_downline($holder_email, $customer_email) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_downlines';
    
    // Check if relationship already exists (using correct column name)
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE holder_email = %s AND downline_email = %s",
        $holder_email, $customer_email
    ));
    
    if (!$existing) {
        $wpdb->insert(
            $table_name,
            array(
                'holder_email' => $holder_email,
                'downline_email' => $customer_email,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
    }
}

// 修改後的 calculateDynamicCommission 函數（確保邏輯正確）
function calculateDynamicCommission($coupon_data, $order_total, $wpdb) {
    $holder_email = $coupon_data['holder_email'];

    // 1. 查詢該持有者的下線數量
    $downlines_table = $wpdb->prefix . 'commission_downlines';
    $downline_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$downlines_table} WHERE holder_email = %s",
        $holder_email
    ));

    // 2. 根據下線數量確定基礎佣金率（從設定選項中讀取）
    $base_commission_rate = 0;

    // 從 WordPress 選項中獲取佣金率設定，如果沒有設定則使用預設值
    $rate_0_100 = get_option('commission_rate_0_100', 30);
    $rate_101_200 = get_option('commission_rate_101_200', 35);
    $rate_200_plus = get_option('commission_rate_200_plus', 40);

    if ($downline_count >= 0 && $downline_count <= 100) {
        $base_commission_rate = $rate_0_100;
    } elseif ($downline_count >= 101 && $downline_count <= 200) {
        $base_commission_rate = $rate_101_200;
    } elseif ($downline_count > 200) {
        $base_commission_rate = $rate_200_plus;
    }

    // 3. 檢查是否有 teacher 和 director，並扣除他們的 rate
    $teacher_rate = isset($coupon_data['teacher_rate']) ? floatval($coupon_data['teacher_rate']) : 0;
    $director_rate = isset($coupon_data['director_rate']) ? floatval($coupon_data['director_rate']) : 0;

    // 計算 holder 的最終佣金率（基礎率減去 teacher 和 director 的 rate）
    $holder_commission_rate = $base_commission_rate - $teacher_rate - $director_rate;

    // 確保 holder 佣金率不會是負數
    $holder_commission_rate = max(0, $holder_commission_rate);

    // 4. 計算 holder 佣金金額
    $holder_commission_amount = $order_total * ($holder_commission_rate / 100);

    // 記錄計算過程（用於調試）
    error_log("Dynamic Commission Calculation:");
    error_log("- Holder: {$holder_email}");
    error_log("- Downline Count: {$downline_count}");
    error_log("- Base Rate: {$base_commission_rate}%");
    error_log("- Teacher Rate: {$teacher_rate}%");
    error_log("- Director Rate: {$director_rate}%");
    error_log("- Holder Final Rate: {$holder_commission_rate}%");
    error_log("- Order Total: {$order_total}");
    error_log("- Holder Commission Amount: {$holder_commission_amount}");

    return [
        'commission_rate' => $holder_commission_rate,
        'commission_amount' => $holder_commission_amount,
        'downline_count' => $downline_count,
        'base_rate' => $base_commission_rate,
        'teacher_rate' => $teacher_rate,
        'director_rate' => $director_rate
    ];
}

function calculate_and_record_commission($coupon_data, $order_total, $order_id) {
    global $wpdb;
    $records_table = $wpdb->prefix . 'commission_records';

    // Calculate commission amount
    $commission_result = calculateDynamicCommission($coupon_data, $order_total, $wpdb);
    $holder_commission_rate = $commission_result['commission_rate'];
    $holder_commission_amount = $commission_result['commission_amount'];

    // 計算 teacher 和 director 的佣金
    $teacher_commission = 0.00;
    $director_commission = 0.00;
    $teacher_rate = 0.00;
    $director_rate = 0.00;

    // 如果有 teacher_email 且有 teacher_rate，計算 teacher 佣金
    if (!empty($coupon_data['teacher_email']) && isset($coupon_data['teacher_rate']) && $coupon_data['teacher_rate'] > 0) {
        $teacher_rate = floatval($coupon_data['teacher_rate']);
        $teacher_commission = $order_total * ($teacher_rate / 100);
    }

    // 如果有 director_email 且有 director_rate，計算 director 佣金
    if (!empty($coupon_data['director_email']) && isset($coupon_data['director_rate']) && $coupon_data['director_rate'] > 0) {
        $director_rate = floatval($coupon_data['director_rate']);
        $director_commission = $order_total * ($director_rate / 100);
    }

    // 記錄佣金計算詳情
    error_log("Commission Calculation Summary:");
    error_log("- Order Total: {$order_total}");
    error_log("- Holder Commission: {$holder_commission_amount} ({$holder_commission_rate}%)");
    error_log("- Teacher Commission: {$teacher_commission} ({$teacher_rate}%)");
    error_log("- Director Commission: {$director_commission} ({$director_rate}%)");
    error_log("- Total Commission: " . ($holder_commission_amount + $teacher_commission + $director_commission));

    // Record commission (using correct column names from table structure)
    $result = $wpdb->insert(
        $records_table,
        array(
            'order_id' => $order_id,
            'coupon_code' => $coupon_data['coupon_code'],
            'holder_email' => $coupon_data['holder_email'],
            'teacher_email' => isset($coupon_data['teacher_email']) ? $coupon_data['teacher_email'] : '',
            'director_email' => isset($coupon_data['director_email']) ? $coupon_data['director_email'] : '',
            'order_total' => $order_total,
            'holder_commission' => $holder_commission_amount,
            'teacher_commission' => $teacher_commission,
            'director_commission' => $director_commission,
            'holder_rate' => $holder_commission_rate,
            'teacher_rate' => $teacher_rate,
            'director_rate' => $director_rate,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s')
    );

    if ($result) {
        $record_id = $wpdb->insert_id;

        // Auto-settle bonus (you can modify this behavior)
        commission_auto_settle_bonus($record_id);

        // Return commission details for order note
        return array(
            'record_id' => $record_id,
            'total_commission' => $holder_commission_amount + $teacher_commission + $director_commission,
            'holder_commission' => $holder_commission_amount,
            'teacher_commission' => $teacher_commission,
            'director_commission' => $director_commission,
            'holder_rate' => $holder_commission_rate,
            'teacher_rate' => $teacher_rate,
            'director_rate' => $director_rate,
            // 保持向後兼容性
            'commission_amount' => $holder_commission_amount,
            'commission_percentage' => $holder_commission_rate
        );
    }
    
    return false;
}

// Track when a commission coupon is applied
function track_commission_coupon_usage($coupon_code) {
    // Check if this is a commission system coupon
    $coupon_data = get_commission_coupon_data($coupon_code);
    if ($coupon_data) {
        // Store in session for order processing
        WC()->session->set('commission_coupon_used', $coupon_code);
        WC()->session->set('commission_coupon_data', $coupon_data);
    }
}

// Save commission coupon to order (simplified for WooCommerce integration)
function save_commission_coupon_to_order($order_id) {
    // Check session first
    $coupon_code = WC()->session->get('commission_coupon_used');
    $coupon_data = WC()->session->get('commission_coupon_data');
    
    if ($coupon_code && $coupon_data) {
        update_post_meta($order_id, '_commission_coupon_used', $coupon_code);
        update_post_meta($order_id, '_commission_coupon_data', $coupon_data);
        
        // Clear session
        WC()->session->__unset('commission_coupon_used');
        WC()->session->__unset('commission_coupon_data');
        
        // Add order note
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note('Commission coupon saved: ' . $coupon_code . ' (Holder: ' . $coupon_data['holder_email'] . ')', false, true);
        }
    }
}

// DEPRECATED: Old commission coupon field (keeping for reference)
function add_commission_coupon_field_deprecated() {
    ?>
    <div id="commission-coupon-section" class="commission-coupon-wrapper">
        <h3>Discount Code</h3>
        <div class="commission-coupon-form">
            <input type="text" id="commission-coupon-code" placeholder="Enter discount code" />
            <button type="button" id="apply-commission-coupon" class="button">Apply</button>
        </div>
        <div id="commission-coupon-message"></div>
        <div id="applied-commission-coupon" style="display: none;">
            <div class="applied-coupon-info">
                <span id="applied-coupon-text"></span>
                <button type="button" id="remove-commission-coupon" class="button-link">Remove</button>
            </div>
        </div>
    </div>
    
    <style>
    .commission-coupon-wrapper {
        margin: 20px 0;
        padding: 15px;
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .commission-coupon-form {
        display: flex;
        gap: 10px;
        margin: 10px 0;
    }
    #commission-coupon-code {
        flex: 1;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 3px;
    }
    .applied-coupon-info {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
        padding: 10px;
        border-radius: 3px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    #commission-coupon-message {
        margin: 10px 0;
    }
    .coupon-error {
        color: #721c24;
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        padding: 8px;
        border-radius: 3px;
    }
    .coupon-success {
        color: #155724;
        background: #d4edda;
        border: 1px solid #c3e6cb;
        padding: 8px;
        border-radius: 3px;
    }
    </style>
    <?php
}

// DEPRECATED: Old AJAX handlers (now using WooCommerce native coupons)
function apply_commission_coupon_ajax_deprecated() {
    if (!wp_verify_nonce($_POST['nonce'], 'commission_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    $coupon_code = sanitize_text_field($_POST['coupon_code']);
    
    if (empty($coupon_code)) {
        wp_send_json_error('Please enter a coupon code');
    }
    
    // Validate coupon
    $coupon_data = validate_commission_coupon($coupon_code);
    
    if (!$coupon_data) {
        wp_send_json_error('Invalid or expired coupon code');
    }
    
    // Store coupon in session and mark as explicitly applied
    WC()->session->set('commission_coupon', $coupon_data);
    WC()->session->set('commission_coupon_applied', true);
    
    // Calculate discount
    $cart_total = WC()->cart->get_subtotal();
    $discount_amount = calculate_coupon_discount($coupon_data, $cart_total);
    
    wp_send_json_success(array(
        'message' => 'Coupon applied successfully!',
        'coupon_code' => $coupon_code,
        'discount_amount' => $discount_amount,
        'discount_text' => format_discount_text($coupon_data)
    ));
}

// AJAX handler for removing commission coupon
function remove_commission_coupon_ajax() {
    if (!wp_verify_nonce($_POST['nonce'], 'commission_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Remove coupon from session and clear applied flag
    WC()->session->__unset('commission_coupon');
    WC()->session->__unset('commission_coupon_applied');
    
    wp_send_json_success('Coupon removed successfully');
}

// AJAX handler for checking applied commission coupon
function check_applied_commission_coupon_ajax() {
    if (!wp_verify_nonce($_POST['nonce'], 'commission_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Check if coupon is applied in session
    $coupon_data = WC()->session->get('commission_coupon');
    
    if ($coupon_data) {
        wp_send_json_success(array(
            'coupon_applied' => true,
            'coupon_code' => $coupon_data['coupon_code'],
            'discount_text' => format_discount_text($coupon_data)
        ));
    } else {
        wp_send_json_success(array(
            'coupon_applied' => false
        ));
    }
}

// Validate commission coupon
function validate_commission_coupon($coupon_code) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_coupons';
    
    $coupon_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE coupon_code = %s AND status = 'active'",
        $coupon_code
    ), ARRAY_A);
    
    return $coupon_data ? $coupon_data : false;
}

// Calculate coupon discount amount
function calculate_coupon_discount($coupon_data, $cart_total) {
    // Calculate discount: 商品總金額 * 折扣百分比
    $percentage = isset($coupon_data['commission_percentage']) ? $coupon_data['commission_percentage'] : $coupon_data['discount_percentage'];
    $discount = $cart_total * ($percentage / 100);
    
    return $discount;
}

// Format discount text for display
function format_discount_text($coupon_data) {
    $percentage = isset($coupon_data['commission_percentage']) ? $coupon_data['commission_percentage'] : $coupon_data['discount_percentage'];
    return $percentage . '% off';
}

// Apply commission discount to cart - THIS IS THE KEY FUNCTION
function apply_commission_discount() {
    // Don't run in admin unless it's AJAX
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    // Don't apply discount if we're not on checkout page
    if (!is_checkout()) {
        return;
    }
    
    // Check if this is a fresh checkout session (no explicit coupon application)
    $coupon_explicitly_applied = WC()->session->get('commission_coupon_applied');
    
    // Get coupon data from session
    $coupon_data = WC()->session->get('commission_coupon');
    
    // Only apply discount if coupon was explicitly applied in this session
    if (!$coupon_data || !$coupon_explicitly_applied) {
        return;
    }
    
    // Get cart subtotal (商品總金額)
    $cart_subtotal = WC()->cart->get_subtotal();
    
    // Calculate discount amount (折扣金額 = 商品總金額 * 折扣百分比)
    $discount_amount = calculate_coupon_discount($coupon_data, $cart_subtotal);
    
    if ($discount_amount > 0) {
        // Add discount as negative fee (這會在checkout頁面顯示為折扣項目)
        $percentage = isset($coupon_data['commission_percentage']) ? $coupon_data['commission_percentage'] : $coupon_data['discount_percentage'];
        $fee_label = 'Discount (' . $coupon_data['coupon_code'] . ') -' . $percentage . '%';
        WC()->cart->add_fee($fee_label, -$discount_amount);
    }
}

// Function to process missing commission records
function process_missing_commissions() {
    global $wpdb;
    
    // Get all orders with commission coupons that don't have commission records
    $orders_with_coupons = $wpdb->get_results("
        SELECT pm.post_id as order_id, pm.meta_value as coupon_code
        FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->prefix}commission_records cr ON pm.post_id = cr.order_id
        WHERE pm.meta_key = '_commission_coupon_used'
        AND cr.id IS NULL
    ");
    
    $processed_count = 0;
    
    foreach ($orders_with_coupons as $order_data) {
        $order_id = $order_data->order_id;
        $order = wc_get_order($order_id);
        
        if (!$order) continue;
        
        // Only process if order is completed
        if ($order->get_status() === 'completed') {
            // Process commission for this order
            process_commission_on_order_complete($order_id);
            $processed_count++;
        }
    }
    
    return $processed_count;
}

// Add commission details to WooCommerce order notes
function add_commission_order_note($order, $coupon_data, $order_total, $commission_result) {
    // Get customer email for downline info
    $customer_email = $order->get_billing_email();
    
    $note = sprintf(
        "=== COMMISSION RECORD CREATED ===\n" .
        "Coupon Code: %s\n" .
        "Referrer (Holder): %s\n" .
        "Customer (Downline): %s\n" .
        "Order Total: $%s\n" .
        "Commission Rate: %s%%\n" .
        "Commission Amount: $%s\n" .
        "Record ID: %d\n" .
        "Status: Pending\n" .
        "Created: %s\n" .
        "=== END COMMISSION INFO ===",
        $coupon_data['coupon_code'],
        $coupon_data['holder_email'],
        $customer_email,
        number_format($order_total, 2),
        $commission_result['commission_percentage'],
        number_format($commission_result['commission_amount'], 2),
        $commission_result['record_id'],
        current_time('Y-m-d H:i:s')
    );
    
    // Add note to order (visible to admin only)
    $order->add_order_note($note, false, true);
    
    // Also add a customer-visible note
    $customer_note = sprintf(
        "Thank you for using coupon code %s! You are now in %s's referral network.",
        $coupon_data['coupon_code'],
        $coupon_data['holder_email']
    );
    $order->add_order_note($customer_note, true, false);
}

// Clear commission coupon session after order completion
function clear_commission_coupon_session($order_id) {
    // Force clear any remaining commission coupon session data
    if (WC()->session) {
        WC()->session->__unset('commission_coupon');
        WC()->session->__unset('commission_coupon_applied');
    }
    
    // Also clear any related session data
    if (isset($_SESSION)) {
        unset($_SESSION['commission_coupon']);
        unset($_SESSION['commission_coupon_applied']);
    }
}

// Clear old commission coupon session when entering checkout page
function clear_old_commission_coupon_on_checkout() {
    // Only run on checkout page
    if (!is_checkout() || is_admin()) {
        return;
    }
    
    // Don't clear if this is an AJAX request (coupon application)
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    
    // Don't clear if this is a page reload after coupon application (check for commission fee in cart)
    $cart_has_commission_fee = false;
    if (WC()->cart) {
        foreach (WC()->cart->get_fees() as $fee) {
            if (strpos($fee->name, 'Discount (') !== false && $fee->amount < 0) {
                $cart_has_commission_fee = true;
                break;
            }
        }
    }
    
    // Check if there's an old commission coupon without explicit application flag
    if (WC()->session) {
        $has_coupon = WC()->session->get('commission_coupon');
        $explicitly_applied = WC()->session->get('commission_coupon_applied');
        
        // If there's a coupon but no explicit application flag AND no commission fee in cart, clear it
        if ($has_coupon && !$explicitly_applied && !$cart_has_commission_fee) {
            WC()->session->__unset('commission_coupon');
            WC()->session->__unset('commission_coupon_applied');
        }
        
        // If there's a commission fee in cart but no session, try to restore from fee
        if (!$has_coupon && $cart_has_commission_fee) {
            foreach (WC()->cart->get_fees() as $fee) {
                if (strpos($fee->name, 'Discount (') !== false && $fee->amount < 0) {
                    if (preg_match('/Discount \(([^)]+)\)/', $fee->name, $matches)) {
                        $coupon_code = $matches[1];
                        $coupon_data = validate_commission_coupon($coupon_code);
                        if ($coupon_data) {
                            WC()->session->set('commission_coupon', $coupon_data);
                            WC()->session->set('commission_coupon_applied', true);
                            break;
                        }
                    }
                }
            }
        }
    }
}
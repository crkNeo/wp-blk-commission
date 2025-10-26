<?php
/**
 * Referral Links System
 * 推薦鏈接系統 - 通過URL和Cookie追蹤推薦關係
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Cookie名稱和有效期設定
define('COMMISSION_REFERRAL_COOKIE', 'commission_ref_code');
define('COMMISSION_REFERRAL_EXPIRY', 30 * DAY_IN_SECONDS); // 30天

/**
 * 初始化推薦鏈接系統
 */
function commission_referral_init() {
    // 捕獲URL中的推薦碼並存入Cookie
    add_action('init', 'commission_capture_referral_code', 1);

    // 在結帳頁面自動套用推薦碼
    add_action('woocommerce_before_checkout_form', 'commission_auto_apply_referral_coupon', 5);

    // 在用戶註冊時建立推薦關係
    add_action('user_register', 'commission_bind_referral_on_registration', 10, 1);

    // 在訂單處理時也檢查Cookie（備用機制）
    add_action('woocommerce_checkout_order_processed', 'commission_bind_referral_on_order', 5, 1);

    // 註冊短代碼用於推薦頁面
    add_shortcode('commission_referral_info', 'commission_referral_info_shortcode');
}
add_action('plugins_loaded', 'commission_referral_init');

/**
 * 捕獲URL中的推薦碼並存入Cookie
 * 支援多種URL格式：
 * - https://beamclub.com/TEST1234
 * - https://beamclub.com/?ref=TEST1234
 * - https://beamclub.com/?referral=TEST1234
 */
function commission_capture_referral_code() {
    // 避免在後台執行
    if (is_admin()) {
        return;
    }

    $referral_code = null;

    // 方法1: 從查詢參數中獲取 (?ref=TEST1234 或 ?referral=TEST1234)
    if (isset($_GET['ref']) && !empty($_GET['ref'])) {
        $referral_code = sanitize_text_field($_GET['ref']);
    } elseif (isset($_GET['referral']) && !empty($_GET['referral'])) {
        $referral_code = sanitize_text_field($_GET['referral']);
    }
    // 方法2: 從URL路徑中獲取 (/TEST1234)
    else {
        $request_uri = $_SERVER['REQUEST_URI'];
        // 移除查詢字符串
        $path = strtok($request_uri, '?');
        // 移除前後斜線
        $path = trim($path, '/');

        // 檢查是否為簡單的折扣碼路徑（只有一段，且不包含其他路徑）
        $path_segments = explode('/', $path);

        // 如果路徑只有一段，且符合折扣碼格式（字母數字組合）
        if (count($path_segments) === 1 && !empty($path_segments[0])) {
            $potential_code = $path_segments[0];

            // 驗證格式：只允許字母、數字、連字符、底線
            if (preg_match('/^[A-Za-z0-9_-]+$/', $potential_code)) {
                // 檢查是否為有效的折扣碼
                if (commission_validate_referral_code($potential_code)) {
                    $referral_code = $potential_code;
                }
            }
        }
    }

    // 如果找到了推薦碼，驗證並存入Cookie
    if ($referral_code && commission_validate_referral_code($referral_code)) {
        // 檢查Cookie是否已經存在相同的推薦碼
        $existing_code = isset($_COOKIE[COMMISSION_REFERRAL_COOKIE]) ? $_COOKIE[COMMISSION_REFERRAL_COOKIE] : '';

        if ($existing_code !== $referral_code) {
            // 設定Cookie（30天有效期）
            $expiry = time() + COMMISSION_REFERRAL_EXPIRY;
            setcookie(COMMISSION_REFERRAL_COOKIE, $referral_code, $expiry, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

            // 同時設定到 $_COOKIE 全局變量，讓當前請求也能使用
            $_COOKIE[COMMISSION_REFERRAL_COOKIE] = $referral_code;

            // 記錄日誌
            error_log("Commission Referral: Code '$referral_code' saved to cookie");

            // 如果用戶已登入，立即建立推薦關係
            if (is_user_logged_in()) {
                $user = wp_get_current_user();
                commission_bind_user_to_referral($user->ID, $referral_code);
            }
        }
    }
}

/**
 * 驗證推薦碼是否有效
 */
function commission_validate_referral_code($code) {
    // 檢查是否為有效的折扣碼
    $coupon_data = get_commission_coupon_data($code);

    if ($coupon_data && $coupon_data['status'] === 'active') {
        return true;
    }

    return false;
}

/**
 * 在用戶註冊時建立推薦關係
 */
function commission_bind_referral_on_registration($user_id) {
    // 檢查Cookie中是否有推薦碼
    $referral_code = isset($_COOKIE[COMMISSION_REFERRAL_COOKIE]) ? $_COOKIE[COMMISSION_REFERRAL_COOKIE] : '';

    if (!empty($referral_code)) {
        commission_bind_user_to_referral($user_id, $referral_code);

        error_log("Commission Referral: User $user_id registered via referral code '$referral_code'");
    }
}

/**
 * 將用戶綁定到推薦人
 */
function commission_bind_user_to_referral($user_id, $referral_code) {
    // 檢查是否已經綁定過推薦關係
    $existing_referral = get_user_meta($user_id, '_commission_referral_code', true);

    if (!empty($existing_referral)) {
        // 已經有推薦關係，不重複綁定
        return false;
    }

    // 驗證推薦碼
    $coupon_data = get_commission_coupon_data($referral_code);

    if (!$coupon_data) {
        return false;
    }

    // 獲取用戶email
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }

    $customer_email = $user->user_email;
    $holder_email = $coupon_data['holder_email'];

    // 避免自己推薦自己
    if ($customer_email === $holder_email) {
        return false;
    }

    // 建立推薦關係
    add_downline($holder_email, $customer_email);

    // 儲存推薦碼到用戶meta
    update_user_meta($user_id, '_commission_referral_code', $referral_code);
    update_user_meta($user_id, '_commission_referrer_email', $holder_email);
    update_user_meta($user_id, '_commission_referral_date', current_time('mysql'));

    error_log("Commission Referral: User $user_id bound to holder $holder_email via code '$referral_code'");

    return true;
}

/**
 * 在結帳頁面自動套用推薦碼
 */
function commission_auto_apply_referral_coupon() {
    // 檢查Cookie中是否有推薦碼
    $referral_code = isset($_COOKIE[COMMISSION_REFERRAL_COOKIE]) ? $_COOKIE[COMMISSION_REFERRAL_COOKIE] : '';

    if (empty($referral_code)) {
        return;
    }

    // 驗證推薦碼是否有效
    if (!commission_validate_referral_code($referral_code)) {
        return;
    }

    // 檢查購物車中是否已經套用了折扣碼
    $applied_coupons = WC()->cart->get_applied_coupons();

    if (in_array($referral_code, $applied_coupons)) {
        // 已經套用了這個折扣碼
        return;
    }

    // 自動套用折扣碼
    $result = WC()->cart->apply_coupon($referral_code);

    if ($result) {
        wc_add_notice(
            sprintf(__('推薦折扣碼 "%s" 已自動套用！', 'commission-system'), $referral_code),
            'success'
        );

        error_log("Commission Referral: Auto-applied coupon '$referral_code' at checkout");
    }
}

/**
 * 在訂單處理時檢查Cookie並建立推薦關係（備用機制）
 */
function commission_bind_referral_on_order($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $user_id = $order->get_user_id();

    // 只處理已登入用戶
    if (!$user_id) {
        return;
    }

    // 檢查是否已經建立過推薦關係
    $existing_referral = get_user_meta($user_id, '_commission_referral_code', true);
    if (!empty($existing_referral)) {
        return;
    }

    // 檢查Cookie
    $referral_code = isset($_COOKIE[COMMISSION_REFERRAL_COOKIE]) ? $_COOKIE[COMMISSION_REFERRAL_COOKIE] : '';

    if (!empty($referral_code)) {
        commission_bind_user_to_referral($user_id, $referral_code);
    }
}

/**
 * 獲取當前訪客的推薦碼
 */
function commission_get_current_referral_code() {
    return isset($_COOKIE[COMMISSION_REFERRAL_COOKIE]) ? $_COOKIE[COMMISSION_REFERRAL_COOKIE] : '';
}

/**
 * 短代碼：顯示推薦信息
 * 使用方式：[commission_referral_info]
 */
function commission_referral_info_shortcode($atts) {
    // 從URL或Cookie獲取推薦碼
    $referral_code = '';

    if (isset($_GET['ref'])) {
        $referral_code = sanitize_text_field($_GET['ref']);
    } elseif (isset($_GET['referral'])) {
        $referral_code = sanitize_text_field($_GET['referral']);
    } else {
        $referral_code = commission_get_current_referral_code();
    }

    if (empty($referral_code)) {
        return '<div class="commission-referral-info">無效的推薦鏈接</div>';
    }

    // 獲取折扣碼資料
    $coupon_data = get_commission_coupon_data($referral_code);

    if (!$coupon_data || $coupon_data['status'] !== 'active') {
        return '<div class="commission-referral-info">推薦碼已過期或無效</div>';
    }

    // 獲取推薦人資訊
    $holder_email = $coupon_data['holder_email'];
    $discount_percentage = $coupon_data['discount_percentage'];

    // 嘗試從email獲取用戶資訊
    $holder_user = get_user_by('email', $holder_email);
    $holder_name = $holder_user ? $holder_user->display_name : $holder_email;

    ob_start();
    ?>
    <div class="commission-referral-info-box">
        <h3>🎉 歡迎來到 <?php echo esc_html($holder_name); ?> 的專屬頁面！</h3>
        <div class="referral-details">
            <p><strong>推薦人：</strong><?php echo esc_html($holder_name); ?></p>
            <p><strong>您的專屬折扣：</strong><?php echo esc_html($discount_percentage); ?>% OFF</p>
            <p><strong>折扣碼：</strong><code><?php echo esc_html($referral_code); ?></code></p>
        </div>
        <div class="referral-benefits">
            <h4>✨ 專屬優惠</h4>
            <ul>
                <li>✅ 立即享有 <?php echo esc_html($discount_percentage); ?>% 折扣</li>
                <li>✅ 註冊後自動成為 <?php echo esc_html($holder_name); ?> 的專屬會員</li>
                <li>✅ 未來購物自動套用折扣</li>
            </ul>
        </div>
        <div class="referral-cta">
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button">
                開始購物
            </a>
            <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="button button-secondary">
                註冊/登入
            </a>
        </div>
        <p class="referral-note">
            <small>💡 您的專屬折扣碼已保存30天，在此期間購物將自動套用折扣。</small>
        </p>
    </div>

    <style>
    .commission-referral-info-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 10px;
        margin: 20px 0;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .commission-referral-info-box h3 {
        color: white;
        margin-top: 0;
        font-size: 24px;
    }
    .commission-referral-info-box h4 {
        color: white;
        margin-top: 20px;
    }
    .referral-details {
        background: rgba(255,255,255,0.1);
        padding: 15px;
        border-radius: 5px;
        margin: 15px 0;
    }
    .referral-details p {
        margin: 8px 0;
        font-size: 16px;
    }
    .referral-details code {
        background: rgba(255,255,255,0.2);
        padding: 5px 10px;
        border-radius: 3px;
        font-size: 18px;
        font-weight: bold;
    }
    .referral-benefits ul {
        list-style: none;
        padding: 0;
        margin: 10px 0;
    }
    .referral-benefits li {
        padding: 5px 0;
        font-size: 16px;
    }
    .referral-cta {
        margin: 20px 0;
        display: flex;
        gap: 10px;
    }
    .referral-cta .button {
        flex: 1;
        text-align: center;
        background: white;
        color: #667eea;
        border: none;
        padding: 12px 24px;
        font-size: 16px;
        font-weight: bold;
        text-decoration: none;
        border-radius: 5px;
        transition: all 0.3s;
    }
    .referral-cta .button:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    .referral-cta .button-secondary {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    .referral-note {
        margin: 15px 0 0 0;
        font-size: 14px;
        opacity: 0.9;
    }
    </style>
    <?php

    return ob_get_clean();
}

/**
 * 獲取用戶的推薦統計
 */
function commission_get_user_referral_stats($user_email) {
    global $wpdb;
    $downlines_table = $wpdb->prefix . 'commission_downlines';

    $stats = array();

    // 獲取下線總數
    $stats['total_referrals'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $downlines_table WHERE holder_email = %s",
        $user_email
    ));

    // 獲取本月新增下線
    $stats['month_referrals'] = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $downlines_table
        WHERE holder_email = %s
        AND MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())",
        $user_email
    ));

    return $stats;
}

/**
 * 生成推薦鏈接
 */
function commission_generate_referral_link($coupon_code, $page_id = null) {
    if ($page_id) {
        // 如果指定了頁面ID，生成到該頁面的推薦鏈接
        $base_url = get_permalink($page_id);
        return add_query_arg('ref', $coupon_code, $base_url);
    } else {
        // 生成簡短的推薦鏈接（首頁 + 折扣碼）
        $home_url = trailingslashit(home_url());
        return $home_url . $coupon_code;
    }
}

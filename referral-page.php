<?php
/**
 * Referral Page for WooCommerce My Account
 * 推薦頁面 - 顯示 QR Code 和推薦網址
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize referral page
 */
function commission_referral_page_init() {
    if (class_exists('WooCommerce')) {
        // Add endpoint to WooCommerce My Account
        add_action('init', 'commission_add_referral_page_endpoint');

        // Add menu item to WooCommerce My Account
        add_filter('woocommerce_account_menu_items', 'commission_add_referral_page_menu_item', 10, 1);

        // Add content for the endpoint
        add_action('woocommerce_account_referral-link_endpoint', 'commission_referral_page_content');

        // Register query var
        add_filter('woocommerce_get_query_vars', 'commission_add_referral_page_query_vars');

        // Enqueue scripts
        add_action('wp_enqueue_scripts', 'commission_referral_page_scripts');
    }
}
add_action('plugins_loaded', 'commission_referral_page_init');

/**
 * Add WooCommerce My Account endpoint
 */
function commission_add_referral_page_endpoint() {
    add_rewrite_endpoint('referral-link', EP_ROOT | EP_PAGES);
}

/**
 * Add query vars for WooCommerce
 */
function commission_add_referral_page_query_vars($vars) {
    $vars['referral-link'] = 'referral-link';
    return $vars;
}

/**
 * Add menu item to WooCommerce My Account
 */
function commission_add_referral_page_menu_item($items) {
    // Check if user is a holder
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();

        // Check if user is a holder (has referral codes)
        if (commission_user_is_holder($current_user->user_email)) {
            // Insert after commission-reports or at the end
            $new_items = array();
            foreach ($items as $key => $value) {
                $new_items[$key] = $value;
                if ($key === 'commission-reports') {
                    $new_items['referral-link'] = '我的推薦碼';
                }
            }

            // If commission-reports doesn't exist, add at the end
            if (!isset($items['commission-reports'])) {
                $new_items['referral-link'] = '我的推薦碼';
            }

            return $new_items;
        }
    }

    return $items;
}

/**
 * Check if user is a holder
 */
function commission_user_is_holder($user_email) {
    global $wpdb;
    $coupons_table = $wpdb->prefix . 'commission_coupons';

    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $coupons_table
         WHERE status = 'active' AND holder_email = %s",
        $user_email
    ));

    return $result > 0;
}

/**
 * Get user's referral codes
 */
function commission_get_user_referral_codes($user_email) {
    global $wpdb;
    $coupons_table = $wpdb->prefix . 'commission_coupons';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $coupons_table
         WHERE status = 'active' AND holder_email = %s
         ORDER BY created_at DESC",
        $user_email
    ), ARRAY_A);
}

/**
 * Enqueue scripts for referral page
 */
function commission_referral_page_scripts() {
    if (is_wc_endpoint_url('referral-link')) {
        wp_enqueue_script('qrcode-js', 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js', array(), '1.0.0', true);

        wp_add_inline_script('qrcode-js', '
            jQuery(document).ready(function($) {
                // Copy to clipboard function
                $(".copy-referral-link").on("click", function(e) {
                    e.preventDefault();
                    var link = $(this).data("link");
                    var $temp = $("<input>");
                    $("body").append($temp);
                    $temp.val(link).select();
                    document.execCommand("copy");
                    $temp.remove();

                    // Show success message
                    var $btn = $(this);
                    var originalText = $btn.text();
                    $btn.text("✓ 已複製！");
                    $btn.css("background-color", "#10b981");

                    setTimeout(function() {
                        $btn.text(originalText);
                        $btn.css("background-color", "");
                    }, 2000);
                });

                // Generate QR codes
                $(".qrcode-container").each(function() {
                    var link = $(this).data("link");
                    var qrcodeId = $(this).attr("id");

                    new QRCode(document.getElementById(qrcodeId), {
                        text: link,
                        width: 200,
                        height: 200,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                });
            });
        ');
    }
}

/**
 * Display referral page content
 */
function commission_referral_page_content() {
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;

    // Get user's referral codes
    $referral_codes = commission_get_user_referral_codes($user_email);

    if (empty($referral_codes)) {
        echo '<div class="woocommerce-message woocommerce-message--info woocommerce-info">';
        echo '您目前沒有推薦碼。';
        echo '</div>';
        return;
    }

    // Get holder level info
    $holder_level = commission_get_holder_level($user_email);

    ?>
    <div class="commission-referral-page">

        <!-- Header with Level Badge -->
        <div class="referral-header">
            <h2>我的推薦碼</h2>
        </div>

        <!-- Referral Codes -->
        <?php foreach ($referral_codes as $index => $code): ?>
            <?php
            $referral_link = commission_generate_referral_link($code['coupon_code']);
            $qrcode_id = 'qrcode-' . $index;
            ?>

            <div class="referral-code-card">
                <div class="card-header">
                    <h3>推薦碼：<span class="code-highlight"><?php echo esc_html($code['coupon_code']); ?></span></h3>
                </div>

                <div class="card-content">
                    <!-- QR Code Section -->
                    <div class="qrcode-section">
                        <div class="qrcode-wrapper">
                            <div id="<?php echo $qrcode_id; ?>"
                                 class="qrcode-container"
                                 data-link="<?php echo esc_url($referral_link); ?>">
                            </div>
                        </div>
                        <p class="qrcode-hint">掃描 QR Code 分享給朋友</p>
                    </div>

                    <!-- Referral Link Section -->
                    <div class="link-section">
                        <label class="link-label">推薦網址</label>
                        <div class="link-input-group">
                            <input type="text"
                                   class="link-input"
                                   value="<?php echo esc_url($referral_link); ?>"
                                   readonly>
                            <button class="copy-referral-link button"
                                    data-link="<?php echo esc_url($referral_link); ?>">
                                📋 複製連結
                            </button>
                        </div>

                        <!-- Commission Info -->
                        <div class="commission-info">
                            <div class="info-row">
                                <span class="info-label">您當前等級：</span>
                                <span class="info-value"><?php echo $holder_level['level']; ?>級</span>
                            </div>
                        </div>

                        <!-- Sharing Tips -->
                        <div class="sharing-tips">
                            <h5>💡 分享小提示</h5>
                            <ul>
                                <li>掃描 QR Code 或使用推薦網址分享給朋友</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        <?php endforeach; ?>

        <!-- Level Progress -->
        <div class="level-progress-card">
            <h3>等級進度</h3>
            <?php
            $current_count = $holder_level['downline_count'];
            if ($current_count <= 100) {
                $next_level = 'P級';
                $next_target = 101;
                $needed = $next_target - $current_count;
                $progress = ($current_count / $next_target) * 100;
            } elseif ($current_count <= 200) {
                $next_level = 'A級';
                $next_target = 201;
                $needed = $next_target - $current_count;
                $progress = (($current_count - 100) / 101) * 100;
            } else {
                $next_level = '已達最高級';
                $needed = 0;
                $progress = 100;
            }
            ?>

            <?php if ($needed > 0): ?>
                <p class="progress-text">
                    再推薦 <strong><?php echo $needed; ?></strong> 人即可升級至 <strong><?php echo $next_level; ?></strong>！
                </p>
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: <?php echo min(100, $progress); ?>%;"></div>
                </div>
                <div class="progress-labels">
                    <span><?php echo $current_count; ?> 人</span>
                    <span><?php echo $next_target; ?> 人</span>
                </div>
            <?php else: ?>
                <p class="progress-text">
                    🎉 恭喜！您已達到最高等級 <strong>A級</strong>！
                </p>
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: 100%;"></div>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <style>
    /* Referral Page Styles */
    .commission-referral-page {
        max-width: 1200px;
        margin: 0 auto;
    }

    .referral-header {
        text-align: center;
        margin-bottom: 30px;
        padding: 10px;
        color: black;
        border-radius: 12px;
    }

    .referral-header h2 {
        color: black;
    }

    .level-badge-large {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .downline-count {
        font-size: 1.1em;
        opacity: 0.95;
    }

    .referral-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .stat-icon {
        font-size: 2em;
        margin-bottom: 10px;
    }

    .stat-value {
        font-size: 1.8em;
        font-weight: bold;
        color: #667eea;
        margin-bottom: 5px;
    }

    .stat-label {
        color: #666;
        font-size: 0.9em;
    }

    .referral-code-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        margin-bottom: 25px;
        overflow: hidden;
    }

    .card-header {
        background: darkgray;
        color: white;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .card-header h3 {
        margin: 0;
        color: white;
        font-size: 1.3em;
    }

    .code-highlight {
        background: rgba(255,255,255,0.2);
        padding: 5px 12px;
        border-radius: 6px;
        font-family: monospace;
        font-size: 1.1em;
    }

    .discount-badge {
        background: rgba(255,255,255,0.3);
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 1.1em;
    }

    .card-content {
        padding: 30px;
        display: grid;
        align-items: center;
        grid-template-columns: auto 1fr;
        gap: 30px;
    }

    .qrcode-section {
        text-align: center;
    }

    .qrcode-wrapper {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        display: inline-block;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .qrcode-container {
        display: inline-block;
    }

    .qrcode-hint {
        margin-top: 10px;
        color: #666;
        font-size: 0.9em;
    }

    .link-section {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .link-label {
        font-weight: bold;
        color: #333;
        font-size: 1.1em;
    }

    .link-input-group {
        display: flex;
        gap: 10px;
    }

    .link-input {
        flex: 1;
        padding: 12px 15px;
        border: 2px solid #e5e7eb;
        border-radius: 6px;
        font-size: 1em;
        background: #f9fafb;
        font-family: monospace;
    }

    .copy-referral-link {
        background: #667eea;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 6px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .copy-referral-link:hover {
        background: #5568d3;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .commission-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 6px;
        border-left: 4px solid #667eea;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        color: #666;
    }

    .info-value {
        font-weight: bold;
        color: #667eea;
    }

    .sharing-tips {
        background: #fef3c7;
        padding: 15px;
        border-radius: 6px;
        border-left: 4px solid #f59e0b;
    }

    .sharing-tips h5 {
        margin: 0 0 10px 0;
        color: #92400e;
    }

    .sharing-tips ul {
        margin: 0;
        padding-left: 20px;
        color: #78350f;
    }

    .sharing-tips li {
        margin: 5px 0;
    }

    .level-progress-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        margin-top: 25px;
    }

    .level-progress-card h3 {
        margin: 0 0 15px 0;
        color: #333;
    }

    .progress-text {
        text-align: center;
        font-size: 1.1em;
        margin-bottom: 15px;
        color: #666;
    }

    .progress-bar-container {
        background: #e5e7eb;
        height: 30px;
        border-radius: 15px;
        overflow: hidden;
        margin-bottom: 10px;
    }

    .progress-bar {
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        height: 100%;
        transition: width 1s ease;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 10px;
        color: white;
        font-weight: bold;
    }

    .progress-labels {
        display: flex;
        justify-content: space-between;
        color: #666;
        font-size: 0.9em;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .card-content {
            grid-template-columns: 1fr;
        }

        .referral-stats {
            grid-template-columns: repeat(2, 1fr);
        }

        .link-input-group {
            flex-direction: column;
        }

        .card-header {
            flex-direction: column;
            text-align: center;
        }
    }
    </style>

    <?php
}

// Flush rewrite rules on activation
register_activation_hook(__FILE__, 'commission_referral_page_flush_rewrite');
function commission_referral_page_flush_rewrite() {
    commission_add_referral_page_endpoint();
    flush_rewrite_rules();
}
?>

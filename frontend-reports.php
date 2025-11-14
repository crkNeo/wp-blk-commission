<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add frontend reports to user dashboard
add_action('init', 'commission_add_frontend_reports');

function commission_add_frontend_reports() {
    // Add rewrite rules for frontend reports
    add_rewrite_rule('^commission-reports/?$', 'index.php?commission_reports=1', 'top');
    add_rewrite_rule('^commission-reports/export/?$', 'index.php?commission_reports=1&export=csv', 'top');
    
    // Add query vars
    add_filter('query_vars', 'commission_add_query_vars');
    
    // Handle the frontend reports page
    add_action('template_redirect', 'commission_handle_frontend_reports');
    
    // Add menu item to user account menu (if theme supports it)
    add_filter('wp_nav_menu_items', 'commission_add_menu_item', 10, 2);
    
    // Add to WooCommerce My Account menu
    if (class_exists('WooCommerce')) {
        add_filter('woocommerce_account_menu_items', 'commission_add_wc_account_menu');
        add_action('woocommerce_account_commission-reports_endpoint', 'commission_wc_account_content');
        add_action('init', 'commission_add_wc_endpoint');
        
        // Also add the endpoint on WooCommerce init
        add_action('woocommerce_init', 'commission_add_wc_endpoint');
    }
}

// Add WooCommerce My Account endpoint
function commission_add_wc_endpoint() {
    add_rewrite_endpoint('commission-reports', EP_ROOT | EP_PAGES);
}

// Alternative method: Add endpoint using WooCommerce's method
add_filter('woocommerce_get_query_vars', 'commission_add_query_vars_to_wc');
function commission_add_query_vars_to_wc($vars) {
    $vars['commission-reports'] = 'commission-reports';
    return $vars;
}

// Debug function to check if endpoint is registered
function commission_debug_endpoints() {
    if (current_user_can('manage_options') && isset($_GET['debug_commission'])) {
        global $wp_rewrite;
        echo '<pre>';
        echo "WooCommerce endpoints:\n";
        if (function_exists('WC')) {
            $endpoints = WC()->query->get_query_vars();
            print_r($endpoints);
        }
        echo "\nWordPress rewrite endpoints:\n";
        print_r($wp_rewrite->endpoints);
        echo '</pre>';
        exit;
    }
}
add_action('init', 'commission_debug_endpoints', 999);

// Force flush rewrite rules when plugin is activated or when visiting admin
add_action('admin_init', 'commission_maybe_flush_rewrite_rules');
function commission_maybe_flush_rewrite_rules() {
    if (get_option('commission_flush_rewrite_rules_flag')) {
        flush_rewrite_rules();
        delete_option('commission_flush_rewrite_rules_flag');
    }
}

// Add menu item to WooCommerce My Account
function commission_add_wc_account_menu($items) {
    // Check if user has commission records in the system
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();

        // Only show menu if user has actual commission records
        if (commission_is_user_in_system($current_user->user_email)) {
            // Insert after orders to control position
            $new_items = array();
            foreach ($items as $key => $value) {
                $new_items[$key] = $value;
                if ($key === 'orders') {
                    $new_items['commission-reports'] = '我的分潤';
                }
            }
            // If orders doesn't exist, add at the end
            if (!isset($items['orders'])) {
                $new_items['commission-reports'] = '我的分潤';
            }
            return $new_items;
        }
    }

    return $items;
}

// WooCommerce My Account content
function commission_wc_account_content() {
    $current_user = wp_get_current_user();
    $user_data = commission_get_user_commission_data($current_user->user_email);
    commission_show_wc_account_reports_content($user_data);
}

// Add menu item to regular navigation menu
function commission_add_menu_item($items, $args) {
    // Only add for logged in users who have commission records
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();

        // Check if user has actual commission records
        if (commission_is_user_in_system($current_user->user_email)) {
            $menu_item = '<li class="menu-item"><a href="' . home_url('/commission-reports/') . '">我的分潤</a></li>';
            $items .= $menu_item;
        }
    }

    return $items;
}

function commission_add_query_vars($vars) {
    $vars[] = 'commission_reports';
    $vars[] = 'export';
    return $vars;
}

function commission_handle_frontend_reports() {
    if (get_query_var('commission_reports')) {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(home_url('/commission-reports/')));
            exit;
        }
        
        if (get_query_var('export') === 'csv') {
            commission_handle_frontend_export();
        } else {
            commission_show_frontend_reports();
        }
        exit;
    }
    
    // Handle WooCommerce My Account export
    if (is_wc_endpoint_url('commission-reports') && isset($_GET['export']) && $_GET['export'] === 'csv') {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url());
            exit;
        }
        commission_handle_frontend_export();
        exit;
    }
}

function commission_show_frontend_reports() {
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;
    
    // Get user's commission data
    $user_data = commission_get_user_commission_data($user_email);
    
    get_header();
    commission_show_frontend_reports_content($user_data);
    get_footer();
}

function commission_show_frontend_reports_content($user_data = null) {
    if ($user_data === null) {
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        $user_data = commission_get_user_commission_data($user_email);
    }
    ?>
    
    <div class="commission-frontend-reports">
        <div class="container">
            <h1>我的分潤報表</h1>
            
            <?php if (empty($user_data['reports'])): ?>
                <div class="no-data">
                    <p>目前沒有分潤記錄。</p>
                </div>
            <?php else: ?>
                
                <!-- Summary Cards -->
                <div class="commission-summary">
                    <div class="summary-card">
                        <h3>總團隊人數</h3>
                        <span class="number"><?php echo $user_data['summary']['downline_count']; ?></span>
                    </div>
                    <div class="summary-card">
                        <h3>總銷售額</h3>
                        <span class="number">$<?php echo number_format($user_data['summary']['total_sales'], 2); ?></span>
                    </div>
                    <div class="summary-card">
                        <h3>總分潤</h3>
                        <span class="number">$<?php echo number_format($user_data['summary']['total_commission'], 2); ?></span>
                    </div>
                    <div class="summary-card">
                        <h3>我的角色</h3>
                        <span class="role"><?php echo $user_data['user_type']; ?></span>
                        <?php if (!empty($user_data['summary']['downline_count'])): ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Export Button -->
                <div class="export-section">
                    <a href="<?php echo home_url('/commission-reports/export/'); ?>" class="export-btn">匯出CSV報表</a>
                </div>

                <!-- Reports Table -->
                <div class="reports-table-container">
                    <table class="commission-reports-table">
                        <thead>
                            <tr>
                                <th>訂單編號</th>
                                <th>折扣碼</th>
                                <th>訂單總額</th>
                                <th>我的分潤</th>
                                <th>分潤比例</th>
                                <th>類型</th>
                                <th>狀態</th>
                                <th>日期</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_data['reports'] as $report): ?>
                            <tr>
                                <td><?php echo esc_html($report['order_id']); ?></td>
                                <td><?php echo esc_html($report['coupon_code']); ?></td>
                                <td>$<?php echo number_format($report['order_total'], 2); ?></td>
                                <td>$<?php echo number_format($report['commission'], 2); ?></td>
                                <td><?php echo esc_html($report['commission_rate']); ?>%</td>
                                <td>
                                    <span class="type-badge type-<?php echo esc_attr($report['type']); ?>">
                                        <?php echo $report['type'] === 'bonus' ? '獎金' : ($report['type'] === 'point' ? '點數' : '待處理'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($report['status']); ?>">
                                        <?php echo $report['status'] === 'completed' ? '已完成' : '處理中'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($report['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </div>
    </div>
<?php }

// WooCommerce My Account specific content (without header/footer)
function commission_show_wc_account_reports_content($user_data) {
    ?>

    <?php if (empty($user_data['reports'])): ?>
        <div class="woocommerce-message woocommerce-message--info woocommerce-info">
            目前沒有分潤記錄。
        </div>
    <?php else: ?>

        <!-- Summary Cards -->
        <div class="commission-summary wc-account">
            <div class="summary-card">
                <h3>總團隊人數</h3>
                <span class="number"><?php echo $user_data['summary']['downline_count']; ?></span>
            </div>
            <div class="summary-card">
                <h3>總銷售額</h3>
                <span class="number">$<?php echo number_format($user_data['summary']['total_sales'], 2); ?></span>
            </div>
            <div class="summary-card">
                <h3>總分潤</h3>
                <span class="number">$<?php echo number_format($user_data['summary']['total_commission'], 2); ?></span>
            </div>
            <div class="summary-card">
                <h3>我的等級</h3>
                <span class="role"><?php echo $user_data['user_type']; ?></span>
            </div>
        </div>

        <!-- Export Button -->
        <div class="export-section wc-account">
            <a href="<?php echo add_query_arg('export', 'csv', wc_get_account_endpoint_url('commission-reports')); ?>" class="button wc-forward">匯出CSV報表</a>
        </div>

        <!-- Reports Table -->
        <div class="woocommerce-table woocommerce-table--order-details shop_table shop_table_responsive">
            <table class="woocommerce-table__table table">
                <thead>
                    <tr>
                        <th class="woocommerce-table__product-name product-name">訂單編號</th>
                        <th class="woocommerce-table__product-table product-total">推薦碼</th>
                        <th class="woocommerce-table__product-table product-total">訂單總額</th>
                        <th class="woocommerce-table__product-table product-total">我的分潤</th>
                        <th class="woocommerce-table__product-table product-total">分潤比例</th>
                        <th class="woocommerce-table__product-table product-total">類型</th>
                        <th class="woocommerce-table__product-table product-total">狀態</th>
                        <th class="woocommerce-table__product-table product-total">日期</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($user_data['reports'] as $report): ?>
                    <tr class="woocommerce-table__line-item order_item">
                        <td data-label="訂單編號" class="woocommerce-table__product-name product-name">
                            <?php echo esc_html($report['order_id']); ?>
                        </td>
                        <td data-label="折扣碼" class="woocommerce-table__product-total product-total">
                            <?php echo esc_html($report['coupon_code']); ?>
                        </td>
                        <td data-label="訂單總額" class="woocommerce-table__product-total product-total">
                            $<?php echo number_format($report['order_total'], 2); ?>
                        </td>
                        <td data-label="我的分潤" class="woocommerce-table__product-total product-total">
                            <strong>$<?php echo number_format($report['commission'], 2); ?></strong>
                        </td>
                        <td data-label="分潤比例" class="woocommerce-table__product-total product-total">
                            <?php echo esc_html($report['commission_rate']); ?>%
                        </td>
                        <td data-label="類型" class="woocommerce-table__product-total product-total">
                            <span class="commission-type-badge type-<?php echo esc_attr($report['type']); ?>">
                                <?php echo $report['type'] === 'bonus' ? '獎金' : ($report['type'] === 'point' ? '點數' : '待處理'); ?>
                            </span>
                        </td>
                        <td data-label="狀態" class="woocommerce-table__product-total product-total">
                            <span class="commission-status-badge status-<?php echo esc_attr($report['status']); ?>">
                                <?php echo $report['status'] === 'completed' ? '已完成' : '處理中'; ?>
                            </span>
                        </td>
                        <td data-label="日期" class="woocommerce-table__product-total product-total">
                            <?php echo date('Y-m-d H:i', strtotime($report['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

    <style>
    /* Enhanced styles for WooCommerce My Account reports */
    .commission-summary.wc-account {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }

    .commission-summary.wc-account .summary-card {
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .commission-summary.wc-account .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
    }

    .commission-summary.wc-account .summary-card h3 {
        margin: 0 0 8px 0;
        color: #6b7280;
        font-size: 0.85em;
        font-weight: 500;
        text-transform: uppercase;
    }

    .commission-summary.wc-account .summary-card .number {
        font-size: 1.75em;
        font-weight: 600;
        color: #1d4ed8;
        display: block;
    }

    .commission-summary.wc-account .summary-card .role {
        font-size: 1.2em;
        font-weight: 500;
        color: #059669;
        display: block;
    }

    .export-section.wc-account {
        text-align: right;
        margin-bottom: 20px;
    }

    .export-section.wc-account .button {
        background-color: #2563eb !important;
        color: white !important;
        border-radius: 6px !important;
        padding: 10px 20px !important;
        font-weight: 500 !important;
    }
    .export-section.wc-account .button:hover {
        background-color: #1d4ed8 !important;
        color: white !important;
    }

    /* The woocommerce-table is styled by the theme, we just add specifics */
    .woocommerce-table--order-details .commission-type-badge,
    .woocommerce-table--order-details .commission-status-badge {
        padding: 5px 12px;
        border-radius: 9999px;
        font-size: 0.8em;
        font-weight: 600;
        text-transform: capitalize;
        display: inline-block;
    }

    .woocommerce-table--order-details .type-bonus,
    .woocommerce-table--order-details .status-completed {
        background-color: #dcfce7;
        color: #166534;
    }

    .woocommerce-table--order-details .type-point {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .woocommerce-table--order-details .type-pending {
        background-color: #fef3c7;
        color: #92400e;
    }

    .woocommerce-table--order-details .status-pending {
        background-color: #fee2e2;
        color: #991b1b;
    }

    /* Responsive Table for Mobile */
    @media (max-width: 768px) {
        .commission-summary.wc-account {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .commission-summary.wc-account .summary-card {
            padding: 15px 10px;
        }

        .commission-summary.wc-account .summary-card .number,
        .commission-summary.wc-account .summary-card .role {
            font-size: 1.3em;
        }

        /* Transform table for mobile */
        .woocommerce-table--order-details thead {
            display: none;
        }

        .woocommerce-table--order-details tbody,
        .woocommerce-table--order-details tr,
        .woocommerce-table--order-details td {
            display: block;
            width: 100%;
        }

        .woocommerce-table--order-details tr {
            margin-bottom: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px;
        }

        .woocommerce-table--order-details td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-align: right !important;
            border: none;
            padding: 8px 0;
        }

        .woocommerce-table--order-details td::before {
            content: attr(data-label);
            font-weight: 600;
            text-align: left;
            margin-right: 10px;
            color: #374151;
        }

        .woocommerce-table--order-details td strong {
            font-weight: 500;
        }
    }
    </style>

    <?php
}

// Original frontend reports styles
function commission_add_frontend_styles() {
    ?>
    <style>
    .commission-frontend-reports {
        padding: 40px 0;
        background: #f8f9fa;
        min-height: 80vh;
    }

    .commission-frontend-reports .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .commission-frontend-reports h1 {
        text-align: center;
        margin-bottom: 40px;
        color: #333;
        font-size: 2.5em;
    }

    .commission-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .summary-card {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        text-align: center;
    }

    .summary-card h3 {
        margin: 0 0 15px 0;
        color: #666;
        font-size: 1.1em;
    }

    .summary-card .number {
        font-size: 2.2em;
        font-weight: bold;
        color: #2c5aa0;
    }

    .summary-card .role {
        font-size: 1.5em;
        font-weight: bold;
        color: #28a745;
    }

    .export-section {
        text-align: center;
        margin-bottom: 30px;
    }

    .export-btn {
        background: #007cba;
        color: white;
        padding: 12px 30px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
        transition: background 0.3s;
    }

    .export-btn:hover {
        background: #005a87;
        color: white;
    }

    .reports-table-container {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .commission-reports-table {
        width: 100%;
        border-collapse: collapse;
    }

    .commission-reports-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: left;
        font-weight: bold;
        color: #333;
        border-bottom: 2px solid #dee2e6;
    }

    .commission-reports-table td {
        padding: 15px;
        border-bottom: 1px solid #dee2e6;
    }

    .commission-reports-table tr:hover {
        background: #f8f9fa;
    }

    .type-badge, .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: bold;
    }

    .type-bonus {
        background: #d4edda;
        color: #155724;
    }

    .type-point {
        background: #cce5ff;
        color: #004085;
    }

    .type-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-completed {
        background: #d4edda;
        color: #155724;
    }

    .status-pending {
        background: #f8d7da;
        color: #721c24;
    }

    .no-data {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .no-data p {
        font-size: 1.2em;
        color: #666;
        margin: 0;
    }

    @media (max-width: 768px) {
        .commission-summary {
            grid-template-columns: 1fr;
        }

        .commission-reports-table {
            font-size: 0.9em;
        }

        .commission-reports-table th,
        .commission-reports-table td {
            padding: 10px 8px;
        }
    }
    </style>

    <?php
}

function commission_handle_frontend_export() {
    if (!is_user_logged_in()) {
        wp_die('請先登入');
    }

    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;

    // Get user's commission data
    $user_data = commission_get_user_commission_data($user_email);

    if (empty($user_data['reports'])) {
        wp_die('沒有可匯出的數據');
    }

    $filename = 'my_commission_report_' . date('Y-m-d_H-i-s') . '.csv';

    // Set headers for CSV download with UTF-8 BOM
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");

    // CSV headers
    fputcsv($output, array(
        'Order ID',
        'Coupon Code',
        'Email',
        'Order Total',
        'Commission',
        'Commission Rate',
        'Type',
        'Created Date'
    ));

    // CSV data
    foreach ($user_data['reports'] as $report) {
        fputcsv($output, array(
            $report['order_id'],
            $report['coupon_code'],
            $user_email,
            '$' . number_format($report['order_total'], 2),
            '$' . number_format($report['commission'], 2),
            $report['commission_rate'] . '%',
            $report['type'],
            $report['created_at']
        ));
    }

    fclose($output);
    exit;
}

function commission_get_user_commission_data($user_email) {
    global $wpdb;
    $records_table = $wpdb->prefix . 'commission_records';
    $payments_table = $wpdb->prefix . 'commission_payments';

    // Determine user type and get appropriate data
    $user_type = '';
    $reports = array();
    $summary = array(
        'total_orders' => 0,
        'total_sales' => 0,
        'total_commission' => 0
    );

    // Check if user is a holder
    $holder_records = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, 
                p_bonus.payment_type as bonus_payment_type,
                p_bonus.status as bonus_status,
                p_bonus.created_at as bonus_created_at,
                p_point.payment_type as point_payment_type,
                p_point.status as point_status,
                p_point.created_at as point_created_at
         FROM $records_table r
         LEFT JOIN $payments_table p_bonus ON r.id = p_bonus.commission_record_id 
             AND p_bonus.user_email = r.holder_email 
             AND p_bonus.user_type = 'holder' 
             AND p_bonus.payment_type = 'bonus'
         LEFT JOIN $payments_table p_point ON r.id = p_point.commission_record_id 
             AND p_point.user_email = r.holder_email 
             AND p_point.user_type = 'holder' 
             AND p_point.payment_type = 'point'
         WHERE r.holder_email = %s 
         ORDER BY r.created_at DESC",
        $user_email
    ), ARRAY_A);

    if (!empty($holder_records)) {
        // Get holder's level based on downline count
        $holder_level = commission_get_holder_level($user_email);
        $user_type = $holder_level['display_name'];

        $reports = commission_format_user_reports($holder_records, 'holder');
        $summary['total_orders'] = count($holder_records);
        $summary['total_sales'] = array_sum(array_column($holder_records, 'order_total'));
        $summary['total_commission'] = array_sum(array_column($holder_records, 'holder_commission'));
        $summary['downline_count'] = $holder_level['downline_count'];
        $summary['level_badge'] = $holder_level['badge'];
    }

    // Check if user is a teacher
    if (empty($reports)) {
        $teacher_records = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, 
                    p_bonus.payment_type as teacher_bonus_payment_type,
                    p_bonus.status as teacher_bonus_status,
                    p_bonus.created_at as teacher_bonus_created_at,
                    p_point.payment_type as teacher_point_payment_type,
                    p_point.status as teacher_point_status,
                    p_point.created_at as teacher_point_created_at
             FROM $records_table r
             LEFT JOIN $payments_table p_bonus ON r.id = p_bonus.commission_record_id 
                 AND p_bonus.user_email = r.teacher_email 
                 AND p_bonus.user_type = 'teacher' 
                 AND p_bonus.payment_type = 'bonus'
             LEFT JOIN $payments_table p_point ON r.id = p_point.commission_record_id 
                 AND p_point.user_email = r.teacher_email 
                 AND p_point.user_type = 'teacher' 
                 AND p_point.payment_type = 'point'
             WHERE r.teacher_email = %s AND r.teacher_commission > 0
             ORDER BY r.created_at DESC",
            $user_email
        ), ARRAY_A);

        if (!empty($teacher_records)) {
            $user_type = '講師';
            $reports = commission_format_user_reports($teacher_records, 'teacher');
            $summary['total_orders'] = count($teacher_records);
            $summary['total_sales'] = array_sum(array_column($teacher_records, 'order_total'));
            $summary['total_commission'] = array_sum(array_column($teacher_records, 'teacher_commission'));
        }
    }

    // Check if user is a director
    if (empty($reports)) {
        $director_records = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, 
                    p_bonus.payment_type as director_bonus_payment_type,
                    p_bonus.status as director_bonus_status,
                    p_bonus.created_at as director_bonus_created_at,
                    p_point.payment_type as director_point_payment_type,
                    p_point.status as director_point_status,
                    p_point.created_at as director_point_created_at
             FROM $records_table r
             LEFT JOIN $payments_table p_bonus ON r.id = p_bonus.commission_record_id 
                 AND p_bonus.user_email = r.director_email 
                 AND p_bonus.user_type = 'director' 
                 AND p_bonus.payment_type = 'bonus'
             LEFT JOIN $payments_table p_point ON r.id = p_point.commission_record_id 
                 AND p_point.user_email = r.director_email 
                 AND p_point.user_type = 'director' 
                 AND p_point.payment_type = 'point'
             WHERE r.director_email = %s AND r.director_commission > 0
             ORDER BY r.created_at DESC",
            $user_email
        ), ARRAY_A);

        if (!empty($director_records)) {
            $user_type = '業務總監';
            $reports = commission_format_user_reports($director_records, 'director');
            $summary['total_orders'] = count($director_records);
            $summary['total_sales'] = array_sum(array_column($director_records, 'order_total'));
            $summary['total_commission'] = array_sum(array_column($director_records, 'director_commission'));
        }
    }

    return array(
        'user_type' => $user_type ?: '無角色',
        'reports' => $reports,
        'summary' => $summary
    );
}

function commission_format_user_reports($records, $role) {
    $formatted_reports = array();

    foreach ($records as $record) {
        $commission_field = $role . '_commission';
        $rate_field = $role . '_rate';

        // Handle bonus records
        $bonus_field = ($role === 'holder') ? 'bonus_payment_type' : $role . '_bonus_payment_type';
        $bonus_date_field = ($role === 'holder') ? 'bonus_created_at' : $role . '_bonus_created_at';

        if (!empty($record[$bonus_field]) && $record[$bonus_field] === 'bonus') {
            $formatted_reports[] = array(
                'order_id' => $record['order_id'],
                'coupon_code' => $record['coupon_code'],
                'order_total' => $record['order_total'],
                'commission' => $record[$commission_field],
                'commission_rate' => $record[$rate_field],
                'type' => 'bonus',
                'status' => 'completed',
                'created_at' => $record[$bonus_date_field] ?: $record['created_at']
            );
        }

        // Handle point records
        $point_field = ($role === 'holder') ? 'point_payment_type' : $role . '_point_payment_type';
        $point_date_field = ($role === 'holder') ? 'point_created_at' : $role . '_point_created_at';
        $points_sent_field = ($role === 'holder') ? 'points_sent' : $role . '_points_sent';

        if (!empty($record[$point_field]) && $record[$point_field] === 'point') {
            $formatted_reports[] = array(
                'order_id' => $record['order_id'],
                'coupon_code' => $record['coupon_code'],
                'order_total' => $record['order_total'],
                'commission' => $record[$commission_field],
                'commission_rate' => $record[$rate_field],
                'type' => 'point',
                'status' => 'completed',
                'created_at' => $record[$point_date_field] ?: $record['created_at']
            );
        } elseif (isset($record[$points_sent_field]) && $record[$points_sent_field]) {
            // Handle legacy points_sent flag
            $formatted_reports[] = array(
                'order_id' => $record['order_id'],
                'coupon_code' => $record['coupon_code'],
                'order_total' => $record['order_total'],
                'commission' => $record[$commission_field],
                'commission_rate' => $record[$rate_field],
                'type' => 'point',
                'status' => 'completed',
                'created_at' => $record['created_at']
            );
        }

        // If neither bonus nor points exist, show pending
        if (empty($record[$bonus_field]) && empty($record[$point_field]) && (!isset($record[$points_sent_field]) || !$record[$points_sent_field])) {
            $formatted_reports[] = array(
                'order_id' => $record['order_id'],
                'coupon_code' => $record['coupon_code'],
                'order_total' => $record['order_total'],
                'commission' => $record[$commission_field],
                'commission_rate' => $record[$rate_field],
                'type' => 'pending',
                'status' => 'pending',
                'created_at' => $record['created_at']
            );
        }
    }

    return $formatted_reports;
}

// Add shortcode for displaying reports anywhere
add_shortcode('commission_reports', 'commission_reports_shortcode');

function commission_reports_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '<p>請先登入查看您的分潤報表。<a href="' . wp_login_url(get_permalink()) . '">登入</a></p>';
    }

    $current_user = wp_get_current_user();
    $user_data = commission_get_user_commission_data($current_user->user_email);

    if (empty($user_data['reports'])) {
        return '<p>目前沒有分潤記錄。</p>';
    }

    ob_start();
    commission_show_frontend_reports_content($user_data);
    return ob_get_clean();
}

// Add user dashboard widget
add_action('wp_dashboard_setup', 'commission_add_dashboard_widget');

function commission_add_dashboard_widget() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();

        // Check if user has actual commission records
        if (commission_is_user_in_system($current_user->user_email)) {
            wp_add_dashboard_widget(
                'commission_dashboard_widget',
                '我的分潤概況',
                'commission_dashboard_widget_content'
            );
        }
    }
}

function commission_dashboard_widget_content() {
    $current_user = wp_get_current_user();
    $user_data = commission_get_user_commission_data($current_user->user_email);
    ?>
    <div class="commission-dashboard-widget">
        <div class="commission-summary-mini">
            <div class="summary-item">
                <strong>總訂單：</strong><?php echo $user_data['summary']['total_orders']; ?>
            </div>
            <div class="summary-item">
                <strong>總分潤：</strong>$<?php echo number_format($user_data['summary']['total_commission'], 2); ?>
            </div>
            <div class="summary-item">
                <strong>角色：</strong><?php echo $user_data['user_type']; ?>
                <?php if (!empty($user_data['summary']['downline_count'])): ?>
                    <br><small style="color: #666;">下線: <?php echo $user_data['summary']['downline_count']; ?> 人</small>
                <?php endif; ?>
            </div>
        </div>
        <p style="text-align: center; margin-top: 15px;">
            <a href="<?php echo home_url('/commission-reports/'); ?>" class="button button-primary">查看詳細報表</a>
        </p>
    </div>
    <style>
    .commission-summary-mini {
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
        margin-bottom: 10px;
    }
    .summary-item {
        padding: 8px;
        background: #f8f9fa;
        border-radius: 4px;
        font-size: 14px;
    }
    </style>
    <?php
}

// Flush rewrite rules on activation
register_activation_hook(__FILE__, 'commission_flush_rewrite_rules_on_activation');
function commission_flush_rewrite_rules_on_activation() {
    // Set flag to flush rewrite rules
    add_option('commission_flush_rewrite_rules_flag', true);

    // Add the endpoint
    add_rewrite_endpoint('commission-reports', EP_ROOT | EP_PAGES);

    // Flush immediately
    flush_rewrite_rules();
}

// Also flush when plugin is deactivated
register_deactivation_hook(__FILE__, 'commission_flush_rewrite_rules_on_deactivation');
function commission_flush_rewrite_rules_on_deactivation() {
    flush_rewrite_rules();
}

/**
 * Check if user has actual commission records in the system
 *
 * This function checks if the user has any commission records as a holder, teacher, or director.
 * Only users with actual commission records will see the frontend reports menu.
 *
 * @param string $user_email User's email address
 * @return bool True if user has commission records, false otherwise
 */
function commission_is_user_in_system($user_email) {
    global $wpdb;
    $records_table = $wpdb->prefix . 'commission_records';

    // Check if user has any commission records as holder
    $holder_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $records_table WHERE holder_email = %s",
        $user_email
    ));

    if ($holder_count > 0) {
        return true;
    }

    // Check if user has any commission records as teacher (with actual commission)
    $teacher_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $records_table WHERE teacher_email = %s AND teacher_commission > 0",
        $user_email
    ));

    if ($teacher_count > 0) {
        return true;
    }

    // Check if user has any commission records as director (with actual commission)
    $director_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $records_table WHERE director_email = %s AND director_commission > 0",
        $user_email
    ));

    return $director_count > 0;
}

/**
 * Get holder's level based on downline count
 * 根據下線數量獲取推薦人等級
 *
 * @param string $holder_email Holder's email address
 * @return array Level information including badge, display name, and downline count
 */
function commission_get_holder_level($holder_email) {
    global $wpdb;
    $downlines_table = $wpdb->prefix . 'commission_downlines';

    // Get downline count for this holder
    $downline_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $downlines_table WHERE holder_email = %s",
        $holder_email
    ));

    $downline_count = intval($downline_count);

    // Determine level based on downline count
    if ($downline_count <= 100) {
        $level = 'V';
        $level_name = 'V級';
        $color = '#6366f1'; // Indigo
        $description = '初級推薦人';
    } elseif ($downline_count <= 200) {
        $level = 'P';
        $level_name = 'P級';
        $color = '#8b5cf6'; // Purple
        $description = '中級推薦人';
    } else {
        $level = 'A';
        $level_name = 'A級';
        $color = '#f59e0b'; // Amber/Gold
        $description = '高級推薦人';
    }

    return array(
        'level' => $level,
        'level_name' => $level_name,
        'display_name' => $level_name ,
        'description' => $description,
        'downline_count' => $downline_count,
        'color' => $color,
        'badge' => commission_generate_level_badge($level, $level_name, $color, $downline_count)
    );
}

/**
 * Generate HTML badge for holder level
 * 生成等級徽章HTML
 */
function commission_generate_level_badge($level, $level_name, $color, $downline_count) {
    $badge_html = sprintf(
        '<span class="holder-level-badge level-%s" style="background: linear-gradient(135deg, %s, %s); color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; display: inline-block; box-shadow: 0 2px 8px rgba(0,0,0,0.15);" title="%d 位下線">
            %s
        </span>',
        strtolower($level),
        $color,
        commission_lighten_color($color, 20),
        $downline_count,
        $level_name
    );

    return $badge_html;
}

/**
 * Lighten a hex color
 * 將十六進制顏色變亮
 */
function commission_lighten_color($hex, $percent) {
    // Remove # if present
    $hex = str_replace('#', '', $hex);

    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Lighten
    $r = min(255, $r + ($percent * 255 / 100));
    $g = min(255, $g + ($percent * 255 / 100));
    $b = min(255, $b + ($percent * 255 / 100));

    // Convert back to hex
    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
              . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
              . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}
?>
<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'commission_admin_menu');

function commission_admin_menu() {
    add_menu_page(
        'Commission System',
        '分潤系統',
        'manage_options',
        'commission-system',
        'commission_admin_page',
        'dashicons-money-alt',
        30
    );

    add_submenu_page(
        'commission-system',
        '折扣碼管理',
        '折扣碼管理',
        'manage_options',
        'commission-coupons',
        'commission_coupons_page'
    );

    add_submenu_page(
        'commission-system',
        '分潤報表',
        '分潤報表',
        'manage_options',
        'commission-reports',
        'commission_reports_page'
    );
    
    add_submenu_page(
        'commission-system',
        '推薦人報表',
        '推薦人報表',
        'manage_options',
        'commission-holder-reports',
        'commission_holder_reports_page'
    );
    
    add_submenu_page(
        'commission-system',
        '講師報表',
        '講師報表',
        'manage_options',
        'commission-teacher-reports',
        'commission_teacher_reports_page'
    );
    
    add_submenu_page(
        'commission-system',
        '業務總監報表',
        '業務總監報表',
        'manage_options',
        'commission-director-reports',
        'commission_director_reports_page'
    );
    
    add_submenu_page(
        'commission-system',
        '佣金率設定',
        '佣金率設定',
        'manage_options',
        'commission-settings',
        'commission_settings_page'
    );
    
}

function commission_admin_page() {
    ?>
    <div class="wrap">
        <h1>儀錶板</h1>

        <div class="commission-dashboard">
            <div class="commission-stats">
                <?php
                global $wpdb;
                $coupons_table = $wpdb->prefix . 'commission_coupons';
                $records_table = $wpdb->prefix . 'commission_records';

                $total_coupons = $wpdb->get_var("SELECT COUNT(*) FROM $coupons_table WHERE status = 'active'");
                $total_commissions = $wpdb->get_var("SELECT SUM(IFNULL(holder_commission, 0) + IFNULL(teacher_commission, 0) + IFNULL(director_commission, 0)) FROM $records_table");
                $pending_payments = $wpdb->get_var("SELECT COUNT(*) FROM $records_table WHERE status = 'pending'");
                ?>

                <div class="stat-box">
                    <h3>Active Coupons</h3>
                    <p class="stat-number"><?php echo $total_coupons; ?></p>
                </div>

                <div class="stat-box">
                    <h3>Total Commissions</h3>
                    <p class="stat-number">$<?php echo number_format($total_commissions, 2); ?></p>
                </div>

                <div class="stat-box">
                    <h3>Pending Payments</h3>
                    <p class="stat-number"><?php echo $pending_payments; ?></p>
                </div>
            </div>

            <div class="quick-actions">
                <h3>Quick Actions</h3>
                <a href="<?php echo admin_url('admin.php?page=commission-coupons'); ?>" class="button button-primary">Manage Coupons</a>
                <a href="<?php echo admin_url('admin.php?page=commission-reports'); ?>" class="button button-secondary">View Reports</a>
            </div>
        </div>
    </div>

    <style>
    .commission-dashboard {
        display: flex;
        gap: 20px;
        margin-top: 20px;
    }
    .commission-stats {
        display: flex;
        gap: 15px;
        flex: 2;
    }
    .stat-box {
        background: #fff;
        padding: 20px;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        text-align: center;
        flex: 1;
    }
    .stat-number {
        font-size: 24px;
        font-weight: bold;
        color: #0073aa;
        margin: 10px 0 0 0;
    }
    .quick-actions {
        background: #fff;
        padding: 20px;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        flex: 1;
    }
    .quick-actions .button {
        display: block;
        margin: 10px 0;
        text-align: center;
    }
    .status-completed {
        color: #46b450;
        font-weight: bold;
        padding: 4px 8px;
        background: #ecf7ed;
        border-radius: 3px;
        border: 1px solid #46b450;
    }
    </style>
    <?php
}

function commission_coupons_page() {
    // Handle form submissions
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_coupon') {
            $result = create_commission_coupon($_POST);
            if ($result) {
                echo '<div class="notice notice-success"><p>Coupon created successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Error creating coupon.</p></div>';
            }
        } elseif ($_POST['action'] === 'update_coupon') {
            $result = update_commission_coupon($_POST['coupon_id'], $_POST);
            if ($result !== false) {
                echo '<div class="notice notice-success"><p>Coupon updated successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Error updating coupon.</p></div>';
            }
        } elseif ($_POST['action'] === 'delete_coupon') {
            $result = delete_commission_coupon($_POST['coupon_id']);
            if ($result) {
                echo '<div class="notice notice-success"><p>Coupon deleted successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Error deleting coupon.</p></div>';
            }
        }
    }

    $coupons = get_commission_coupons();
    ?>

    <div class="wrap">
        <h1>折扣碼管理</h1>

        <!-- Add New Coupon Form -->
        <div class="coupon-form-container">
            <h2>新增折扣碼</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="add_coupon">
                <table class="form-table">
                    <tr>
                        <th scope="row">折扣碼</th>
                        <td><input type="text" name="coupon_code" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">專屬人 Email</th>
                        <td><input type="email" name="holder_email" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">講師 Email</th>
                        <td><input type="email" name="teacher_email" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">業務總監 Email</th>
                        <td><input type="email" name="director_email" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">講師比例(%)</th>
                        <td><input type="number" name="teacher_rate" value="5" min="0" max="100" step="0.01" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">業務總監比例(%)</th>
                        <td><input type="number" name="director_rate" value="5" min="0" max="100" step="0.01" class="small-text"></td>
                    </tr>
                </table>

                <h3>Discount Settings</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">折扣比例(%)</th>
                        <td>
                            <input type="number" name="discount_amount" value="10" min="0" max="100" step="0.01" class="small-text" required>
                            <p class="description">Percentage discount for this coupon (e.g., 10 for 10% off)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('新增'); ?>
            </form>
        </div>

        <!-- Existing Coupons List -->
        <div class="coupons-list">
            <h2>已存在折扣碼</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>折扣碼</th>
                        <th>專屬會員 Email</th>
                        <th>講師 Email</th>
                        <th>業務總監 Email</th>
                        <th>講師比例</th>
                        <th>業務總監比例</th>
                        <th>折扣(%)</th>
                        <th>WC Coupon ID</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $coupon): ?>
                    <tr>
                        <td><?php echo esc_html($coupon['coupon_code']); ?></td>
                        <td><?php echo esc_html($coupon['holder_email']); ?></td>
                        <td><?php echo esc_html($coupon['teacher_email']); ?></td>
                        <td><?php echo esc_html($coupon['director_email']); ?></td>
                        <td><?php echo esc_html($coupon['teacher_rate']); ?>%</td>
                        <td><?php echo esc_html($coupon['director_rate']); ?>%</td>
                        <td><?php echo esc_html($coupon['discount_percentage']); ?>%</td>
                        <td><?php echo isset($coupon['wc_coupon_id']) ? esc_html($coupon['wc_coupon_id']) : 'N/A'; ?></td>
                        <td><?php echo esc_html($coupon['status']); ?></td>
                        <td>
                            <button class="button edit-coupon" data-id="<?php echo $coupon['id']; ?>">Edit</button>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="delete_coupon">
                                <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                                <input type="submit" class="button button-link-delete" value="Delete" onclick="return confirm('Are you sure?')">
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <style>
    .coupon-form-container {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
    }
    .coupons-list {
        margin-top: 30px;
    }
    </style>
    <?php
}

function commission_reports_page() {
    $holder_email = isset($_GET['holder']) ? sanitize_email($_GET['holder']) : '';
    $reports = get_commission_reports($holder_email);
    $holders = get_all_commission_holders();
    ?>

    <div class="wrap">
        <h1>訂單報表</h1>

        <!-- Filter Form -->
        <div class="reports-filter">
            <form method="get" action="">
                <input type="hidden" name="page" value="commission-reports">
                <select name="holder">
                    <option value="">All Holders</option>
                    <?php foreach ($holders as $holder): ?>
                    <option value="<?php echo esc_attr($holder['holder_email']); ?>"
                            <?php selected($holder_email, $holder['holder_email']); ?>>
                        <?php echo esc_html($holder['holder_email']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="Filter">
                <a href="<?php echo admin_url('admin.php?page=commission-reports&export=csv'); ?>" class="button button-secondary">Export Report</a>
            </form>
        </div>
        
        <?php
        // Handle manual commission processing
        if (isset($_GET['process_missing']) && $_GET['process_missing'] === '1') {
            $processed_count = process_missing_commissions();
            echo '<div class="notice notice-success"><p>Processed ' . $processed_count . ' missing commission records.</p></div>';
        }
        
        // Handle detailed order debugging
        if (isset($_GET['debug_order_id']) && !empty($_GET['debug_order_id'])) {
            $order_id = intval($_GET['debug_order_id']);
            $order = wc_get_order($order_id);
            
            if ($order) {
                echo '<div class="notice notice-info" style="max-width: 800px;"><h3>Order #' . $order_id . ' Detailed Debug Info:</h3>';
                
                echo '<h4>1. Basic Order Info:</h4>';
                echo '<p>Status: ' . $order->get_status() . '<br>';
                echo 'Total: $' . $order->get_total() . '<br>';
                echo 'Customer: ' . $order->get_billing_email() . '</p>';
                
                echo '<h4>2. Commission Meta Data:</h4>';
                $commission_coupon = get_post_meta($order_id, '_commission_coupon_used', true);
                $commission_data = get_post_meta($order_id, '_commission_coupon_data', true);
                echo '<p>_commission_coupon_used: ' . ($commission_coupon ? $commission_coupon : '<span style="color:red;">NOT FOUND</span>') . '<br>';
                echo '_commission_coupon_data: ' . ($commission_data ? 'Found' : '<span style="color:red;">NOT FOUND</span>') . '</p>';
                
                echo '<h4>3. WooCommerce Coupons:</h4>';
                $wc_coupons = $order->get_coupon_codes();
                if (empty($wc_coupons)) {
                    echo '<p style="color:orange;">No WooCommerce coupons found</p>';
                } else {
                    echo '<p>WC Coupons: ' . implode(', ', $wc_coupons) . '</p>';
                }
                
                echo '<h4>4. Order Fees (Discounts):</h4>';
                $fees = $order->get_fees();
                if (empty($fees)) {
                    echo '<p style="color:orange;">No fees/discounts found</p>';
                } else {
                    foreach ($fees as $fee) {
                        echo '<p>Fee: ' . $fee->get_name() . ' = $' . $fee->get_amount() . '</p>';
                    }
                }
                
                echo '<h4>5. Commission Records:</h4>';
                global $wpdb;
                $records_table = $wpdb->prefix . 'commission_records';
                $records = $wpdb->get_results($wpdb->prepare("SELECT * FROM $records_table WHERE order_id = %d", $order_id));
                if (empty($records)) {
                    echo '<p style="color:orange;">No commission records found</p>';
                } else {
                    foreach ($records as $record) {
                        echo '<p>Record ID: ' . $record->id . ', Coupon: ' . $record->coupon_code . ', Amount: $' . $record->commission_amount . '</p>';
                    }
                }
                
                echo '</div>';
            } else {
                echo '<div class="notice notice-error"><p>Order #' . $order_id . ' not found.</p></div>';
            }
        }
        
        // Handle single order processing
        if (isset($_GET['test_order_id']) && !empty($_GET['test_order_id'])) {
            $order_id = intval($_GET['test_order_id']);
            $order = wc_get_order($order_id);
            
            if ($order) {
                echo '<div class="notice notice-success"><p><strong>Force Processing Order #' . $order_id . ':</strong></p>';
                
                $commission_coupon = get_post_meta($order_id, '_commission_coupon_used', true);
                
                if ($commission_coupon) {
                    // Force process commission
                    process_commission_on_order_complete($order_id);
                    echo '<p style="color: green;">Commission processing attempted for coupon: ' . $commission_coupon . '</p>';
                } else {
                    echo '<p style="color: orange;">No commission coupon found - cannot process commission.</p>';
                }
                echo '</div>';
            } else {
                echo '<div class="notice notice-error"><p>Order #' . $order_id . ' not found.</p></div>';
            }
        }
        
        // Handle database update
        if (isset($_GET['update_db']) && $_GET['update_db'] === '1') {
            global $wpdb;
            $records_table = $wpdb->prefix . 'commission_records';
            
            // Add missing columns
            $wpdb->query("ALTER TABLE $records_table ADD COLUMN IF NOT EXISTS teacher_points_sent TINYINT(1) DEFAULT 0");
            $wpdb->query("ALTER TABLE $records_table ADD COLUMN IF NOT EXISTS director_points_sent TINYINT(1) DEFAULT 0");
            
            echo '<div class="notice notice-success"><p>數據庫結構已更新！</p></div>';
        }
        
        // Test functionality removed - no longer needed
        
        // Handle fixing missing commission data
        if (isset($_GET['fix_order_id']) && !empty($_GET['fix_order_id']) && isset($_GET['fix_coupon_code']) && !empty($_GET['fix_coupon_code'])) {
            $order_id = intval($_GET['fix_order_id']);
            $coupon_code = sanitize_text_field($_GET['fix_coupon_code']);
            $order = wc_get_order($order_id);
            
            if ($order) {
                // Get coupon data from database
                global $wpdb;
                $coupons_table = $wpdb->prefix . 'commission_coupons';
                $coupon_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $coupons_table WHERE coupon_code = %s AND status = 'active'",
                    $coupon_code
                ), ARRAY_A);
                
                if ($coupon_data) {
                    // Manually add the commission coupon data to order meta
                    update_post_meta($order_id, '_commission_coupon_used', $coupon_code);
                    update_post_meta($order_id, '_commission_coupon_data', $coupon_data);
                    
                    // Now process commission
                    process_commission_on_order_complete($order_id);
                    
                    echo '<div class="notice notice-success"><p><strong>Fixed Order #' . $order_id . ':</strong><br>';
                    echo 'Added commission coupon data for: ' . $coupon_code . '<br>';
                    echo 'Processed commission for holder: ' . $coupon_data['holder_email'] . '<br>';
                    echo 'Commission rate: ' . (isset($coupon_data['commission_percentage']) ? $coupon_data['commission_percentage'] : (isset($coupon_data['discount_percentage']) ? $coupon_data['discount_percentage'] : 'Unknown')) . '%</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Coupon code "' . $coupon_code . '" not found in commission_coupons table or not active.</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>Order #' . $order_id . ' not found.</p></div>';
            }
        }
        ?>

        <!-- Reports Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>訂單 ID</th>
                    <th>折扣碼</th>
                    <th>專屬會員 Email</th>
                    <th>訂單總額</th>
                    <th>專屬會員分潤</th>
                    <th>講師分潤</th>
                    <th>業務總監分潤</th>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?php echo esc_html($report['order_id']); ?></td>
                    <td><?php echo esc_html($report['coupon_code']); ?></td>
                    <td><?php echo esc_html($report['holder_email']); ?></td>
                    <td>$<?php echo number_format($report['order_total'], 2); ?></td>
                    <td>$<?php echo number_format(isset($report['holder_commission']) ? $report['holder_commission'] : 0, 2); ?></td>
                    <td>$<?php echo number_format(isset($report['teacher_commission']) ? $report['teacher_commission'] : 0, 2); ?></td>
                    <td>$<?php echo number_format(isset($report['director_commission']) ? $report['director_commission'] : 0, 2); ?></td>
                    <td><?php echo esc_html($report['status']); ?></td>
                    <td>
                        <?php if ($report['status'] === 'pending'): ?>
                        <button class="button button-primary send-points" data-id="<?php echo $report['id']; ?>">Mark as Processed</button>
                        <?php else: ?>
                        <span class="status-completed">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <style>
    .reports-filter {
        background: #fff;
        padding: 15px;
        margin: 20px 0;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
    }
    .reports-filter form {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#process-missing-commissions').click(function() {
            if (confirm('This will process commission records for all orders with commission coupons that haven\'t been processed yet. Continue?')) {
                window.location.href = '<?php echo admin_url('admin.php?page=commission-reports&process_missing=1'); ?>';
            }
        });
    });
    </script>
    <?php
}

function commission_settings_page() {
    // Handle form submission
    if (isset($_POST['action']) && $_POST['action'] === 'update_commission_rates') {
        // Validate and sanitize input
        $rate_0_100 = floatval($_POST['commission_rate_0_100']);
        $rate_101_200 = floatval($_POST['commission_rate_101_200']);
        $rate_200_plus = floatval($_POST['commission_rate_200_plus']);
        
        // Validate ranges
        if ($rate_0_100 >= 0 && $rate_0_100 <= 100 &&
            $rate_101_200 >= 0 && $rate_101_200 <= 100 &&
            $rate_200_plus >= 0 && $rate_200_plus <= 100) {
            
            // Save to WordPress options
            update_option('commission_rate_0_100', $rate_0_100);
            update_option('commission_rate_101_200', $rate_101_200);
            update_option('commission_rate_200_plus', $rate_200_plus);
            
            echo '<div class="notice notice-success"><p>佣金率設定已成功更新！</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>佣金率必須在 0-100% 之間！</p></div>';
        }
    }
    
    // Get current settings or set defaults
    $rate_0_100 = get_option('commission_rate_0_100', 30);
    $rate_101_200 = get_option('commission_rate_101_200', 35);
    $rate_200_plus = get_option('commission_rate_200_plus', 40);
    ?>
    
    <div class="wrap">
        <h1>佣金率設定</h1>
        <p>根據推薦人的下線數量設定不同的基礎佣金率。這些設定將影響所有新的佣金計算。</p>
        
        <div class="commission-settings-container">
            <form method="post" action="">
                <input type="hidden" name="action" value="update_commission_rates">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="commission_rate_0_100">0-100人佣金率 (%)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="commission_rate_0_100" 
                                   name="commission_rate_0_100" 
                                   value="<?php echo esc_attr($rate_0_100); ?>" 
                                   min="0" 
                                   max="100" 
                                   step="0.01" 
                                   class="regular-text" 
                                   required>
                            <p class="description">當推薦人下線數量為 0-100 人時的基礎佣金率</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="commission_rate_101_200">101-200人佣金率 (%)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="commission_rate_101_200" 
                                   name="commission_rate_101_200" 
                                   value="<?php echo esc_attr($rate_101_200); ?>" 
                                   min="0" 
                                   max="100" 
                                   step="0.01" 
                                   class="regular-text" 
                                   required>
                            <p class="description">當推薦人下線數量為 101-200 人時的基礎佣金率</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="commission_rate_200_plus">201人以上佣金率 (%)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="commission_rate_200_plus" 
                                   name="commission_rate_200_plus" 
                                   value="<?php echo esc_attr($rate_200_plus); ?>" 
                                   min="0" 
                                   max="100" 
                                   step="0.01" 
                                   class="regular-text" 
                                   required>
                            <p class="description">當推薦人下線數量為 201 人以上時的基礎佣金率</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('更新佣金率設定'); ?>
            </form>
        </div>
        
        <div class="commission-settings-info">
            <h3>說明</h3>
            <ul>
                <li><strong>基礎佣金率</strong>：根據推薦人的下線數量確定的基礎分潤比例</li>
                <li><strong>最終佣金率</strong>：基礎佣金率減去講師比例和業務總監比例後的實際推薦人佣金率</li>
                <li><strong>下線計算</strong>：系統會自動計算每個推薦人的下線數量來確定適用的佣金率</li>
                <li><strong>即時生效</strong>：設定更新後，所有新的訂單將使用新的佣金率計算</li>
            </ul>
        </div>
    </div>
    
    <style>
    .commission-settings-container {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
    }
    .commission-settings-info {
        background: #f9f9f9;
        padding: 15px;
        margin: 20px 0;
        border-left: 4px solid #0073aa;
    }
    .commission-settings-info ul {
        margin: 10px 0;
    }
    .commission-settings-info li {
        margin: 5px 0;
    }
    </style>
    <?php
}


<?php
// Additional report pages for commission system

// Holder Reports Page
function commission_holder_reports_page() {
    $holder_email = isset($_GET['holder']) ? sanitize_email($_GET['holder']) : '';
    $reports = get_holder_commission_reports($holder_email);
    $holders = get_all_commission_holders();
    
    // Handle status update
    if (isset($_GET['apply_points']) && isset($_GET['record_id'])) {
        $record_id = intval($_GET['record_id']);
        $result = apply_holder_points($record_id);
        if ($result) {
            echo '<div class="notice notice-success"><p>狀態更新成功！</p></div>';
            echo '<script>window.location.href = "' . admin_url('admin.php?page=commission-holder-reports') . '";</script>';
        } else {
            echo '<div class="notice notice-error"><p>狀態更新失敗。</p></div>';
        }
    }
    ?>
    
    <div class="wrap">
        <h1>推薦人分潤報表</h1>
        
        <!-- Filter Form -->
        <div class="reports-filter">
            <form method="get" action="">
                <input type="hidden" name="page" value="commission-holder-reports">
                <select name="holder">
                    <option value="">所有推薦人</option>
                    <?php foreach ($holders as $holder): ?>
                    <option value="<?php echo esc_attr($holder['holder_email']); ?>"
                            <?php selected($holder_email, $holder['holder_email']); ?>>
                        <?php echo esc_html($holder['holder_email']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="篩選">
                <?php 
                $export_url = admin_url('admin-ajax.php?action=commission_export_holder_report');
                if (!empty($holder_email)) {
                    $export_url .= '&holder=' . urlencode($holder_email);
                }
                ?>
                <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary">匯出CSV報表</a>
            </form>
        </div>
        
        <!-- Reports Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>訂單ID</th>
                    <th>折扣碼</th>
                    <th>推薦人信箱</th>
                    <th>訂單總額</th>
                    <th>推薦人分潤</th>
                    <th>分潤比例</th>
                    <th>狀態</th>
                    <th>點數狀態</th>
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
                    <td>$<?php echo number_format($report['holder_commission'], 2); ?></td>
                    <td><?php echo esc_html($report['holder_rate']); ?>%</td>
                    <td><?php echo esc_html($report['status']); ?></td>
                    <td><?php echo (isset($report['points_sent']) && $report['points_sent']) ? '<span style="color: green;">已處理</span>' : '<span style="color: orange;">未處理</span>'; ?></td>
                    <td>
                        <?php 
                        $points_sent = isset($report['points_sent']) ? $report['points_sent'] : 0;
                        $status = isset($report['status']) ? $report['status'] : '';
                        ?>
                        <?php if (!$points_sent): ?>
                        <a href="<?php echo admin_url('admin.php?page=commission-holder-reports&apply_points=1&record_id=' . $report['id']); ?>" 
                           class="button button-primary" 
                           onclick="return confirm('確定要標記此筆分潤為已處理嗎？')">標記為已處理</a>
                        <?php else: ?>
                        <span class="status-completed">已處理</span>
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
    <?php
}

// Teacher Reports Page  
function commission_teacher_reports_page() {
    $teacher_email = isset($_GET['teacher']) ? sanitize_email($_GET['teacher']) : '';
    $reports = get_teacher_commission_reports($teacher_email);
    $teachers = get_all_commission_teachers();
    
    // Handle status update
    if (isset($_GET['apply_points']) && isset($_GET['record_id'])) {
        $record_id = intval($_GET['record_id']);
        $result = apply_teacher_points($record_id);
        if ($result) {
            echo '<div class="notice notice-success"><p>狀態更新成功！</p></div>';
            echo '<script>window.location.href = "' . admin_url('admin.php?page=commission-teacher-reports') . '";</script>';
        } else {
            echo '<div class="notice notice-error"><p>狀態更新失敗。</p></div>';
        }
    }
    ?>
    
    <div class="wrap">
        <h1>講師分潤報表</h1>
        
        <!-- Filter Form -->
        <div class="reports-filter">
            <form method="get" action="">
                <input type="hidden" name="page" value="commission-teacher-reports">
                <select name="teacher">
                    <option value="">所有講師</option>
                    <?php foreach ($teachers as $teacher): ?>
                    <option value="<?php echo esc_attr($teacher['teacher_email']); ?>"
                            <?php selected($teacher_email, $teacher['teacher_email']); ?>>
                        <?php echo esc_html($teacher['teacher_email']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="篩選">
                <?php 
                $export_url = admin_url('admin-ajax.php?action=commission_export_teacher_report');
                if (!empty($teacher_email)) {
                    $export_url .= '&teacher=' . urlencode($teacher_email);
                }
                ?>
                <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary">匯出CSV報表</a>
            </form>
        </div>
        
        <!-- Reports Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>訂單ID</th>
                    <th>折扣碼</th>
                    <th>講師信箱</th>
                    <th>訂單總額</th>
                    <th>講師分潤</th>
                    <th>分潤比例</th>
                    <th>狀態</th>
                    <th>申請狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <?php if (!empty($report['teacher_email']) && $report['teacher_commission'] > 0): ?>
                <tr>
                    <td><?php echo esc_html($report['order_id']); ?></td>
                    <td><?php echo esc_html($report['coupon_code']); ?></td>
                    <td><?php echo esc_html($report['teacher_email']); ?></td>
                    <td>$<?php echo number_format($report['order_total'], 2); ?></td>
                    <td>$<?php echo number_format($report['teacher_commission'], 2); ?></td>
                    <td><?php echo esc_html($report['teacher_rate']); ?>%</td>
                    <td><?php echo esc_html($report['status']); ?></td>
                    <td><?php echo isset($report['teacher_points_sent']) && $report['teacher_points_sent'] ? '<span style="color: green;">已處理</span>' : '<span style="color: orange;">未處理</span>'; ?></td>
                    <td>
                        <?php 
                        $teacher_points_sent = isset($report['teacher_points_sent']) ? $report['teacher_points_sent'] : 0;
                        ?>
                        <?php if (!$teacher_points_sent): ?>
                        <a href="<?php echo admin_url('admin.php?page=commission-teacher-reports&apply_points=1&record_id=' . $report['id']); ?>" 
                           class="button button-primary" 
                           onclick="return confirm('確定要標記此筆分潤為已處理嗎？')">標記為已處理</a>
                        <?php else: ?>
                        <span class="status-completed">已處理</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
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
    <?php
}

// Director Reports Page
function commission_director_reports_page() {
    $director_email = isset($_GET['director']) ? sanitize_email($_GET['director']) : '';
    $reports = get_director_commission_reports($director_email);
    $directors = get_all_commission_directors();
    
    // Handle status update
    if (isset($_GET['apply_points']) && isset($_GET['record_id'])) {
        $record_id = intval($_GET['record_id']);
        $result = apply_director_points($record_id);
        if ($result) {
            echo '<div class="notice notice-success"><p>狀態更新成功！</p></div>';
            echo '<script>window.location.href = "' . admin_url('admin.php?page=commission-director-reports') . '";</script>';
        } else {
            echo '<div class="notice notice-error"><p>狀態更新失敗。</p></div>';
        }
    }
    ?>
    
    <div class="wrap">
        <h1>業務總監分潤報表</h1>
        
        <!-- Filter Form -->
        <div class="reports-filter">
            <form method="get" action="">
                <input type="hidden" name="page" value="commission-director-reports">
                <select name="director">
                    <option value="">所有業務總監</option>
                    <?php foreach ($directors as $director): ?>
                    <option value="<?php echo esc_attr($director['director_email']); ?>"
                            <?php selected($director_email, $director['director_email']); ?>>
                        <?php echo esc_html($director['director_email']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="篩選">
                <?php 
                $export_url = admin_url('admin-ajax.php?action=commission_export_director_report');
                if (!empty($director_email)) {
                    $export_url .= '&director=' . urlencode($director_email);
                }
                ?>
                <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary">匯出CSV報表</a>
            </form>
        </div>
        
        <!-- Reports Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>訂單ID</th>
                    <th>折扣碼</th>
                    <th>業務總監信箱</th>
                    <th>訂單總額</th>
                    <th>業務總監分潤</th>
                    <th>分潤比例</th>
                    <th>狀態</th>
                    <th>申請狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <?php if (!empty($report['director_email']) && $report['director_commission'] > 0): ?>
                <tr>
                    <td><?php echo esc_html($report['order_id']); ?></td>
                    <td><?php echo esc_html($report['coupon_code']); ?></td>
                    <td><?php echo esc_html($report['director_email']); ?></td>
                    <td>$<?php echo number_format($report['order_total'], 2); ?></td>
                    <td>$<?php echo number_format($report['director_commission'], 2); ?></td>
                    <td><?php echo esc_html($report['director_rate']); ?>%</td>
                    <td><?php echo esc_html($report['status']); ?></td>
                    <td><?php echo isset($report['director_points_sent']) && $report['director_points_sent'] ? '<span style="color: green;">已處理</span>' : '<span style="color: orange;">未處理</span>'; ?></td>
                    <td>
                        <?php 
                        $director_points_sent = isset($report['director_points_sent']) ? $report['director_points_sent'] : 0;
                        ?>
                        <?php if (!$director_points_sent): ?>
                        <a href="<?php echo admin_url('admin.php?page=commission-director-reports&apply_points=1&record_id=' . $report['id']); ?>" 
                           class="button button-primary" 
                           onclick="return confirm('確定要標記此筆分潤為已處理嗎？')">標記為已處理</a>
                        <?php else: ?>
                        <span class="status-completed">已處理</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
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
    <?php
}
?>
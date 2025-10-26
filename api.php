<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register AJAX handlers
add_action('wp_ajax_commission_send_points', 'commission_send_points_handler');
add_action('wp_ajax_commission_export_report', 'commission_export_report_handler');
add_action('wp_ajax_commission_export_holder_report', 'commission_export_holder_report_handler');
add_action('wp_ajax_commission_export_teacher_report', 'commission_export_teacher_report_handler');
add_action('wp_ajax_commission_export_director_report', 'commission_export_director_report_handler');
add_action('wp_ajax_commission_get_coupon_data', 'commission_get_coupon_data_handler');
add_action('wp_ajax_commission_update_coupon', 'commission_update_coupon_handler');

// Handle automatic bonus settlement (default behavior)
function commission_auto_settle_bonus($record_id) {
    global $wpdb;
    $records_table = $wpdb->prefix . 'commission_records';
    
    // Get commission record
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $records_table WHERE id = %d",
        $record_id
    ), ARRAY_A);
    
    if (!$record) {
        return false;
    }
    
    // Create bonus entries for each participant (automatic settlement)
    $payments_table = $wpdb->prefix . 'commission_payments';
    
    // Holder bonus
    if ($record['holder_commission'] > 0) {
        $wpdb->insert($payments_table, array(
            'user_email' => $record['holder_email'],
            'user_type' => 'holder',
            'amount' => $record['holder_commission'],
            'payment_type' => 'bonus',
            'commission_record_id' => $record_id,
            'status' => 'completed'
        ));
    }
    
    // Teacher bonus
    if ($record['teacher_commission'] > 0 && !empty($record['teacher_email'])) {
        $wpdb->insert($payments_table, array(
            'user_email' => $record['teacher_email'],
            'user_type' => 'teacher',
            'amount' => $record['teacher_commission'],
            'payment_type' => 'bonus',
            'commission_record_id' => $record_id,
            'status' => 'completed'
        ));
    }
    
    // Director bonus
    if ($record['director_commission'] > 0 && !empty($record['director_email'])) {
        $wpdb->insert($payments_table, array(
            'user_email' => $record['director_email'],
            'user_type' => 'director',
            'amount' => $record['director_commission'],
            'payment_type' => 'bonus',
            'commission_record_id' => $record_id,
            'status' => 'completed'
        ));
    }
    
    // Update record status to completed (bonus settled)
    $wpdb->update(
        $records_table,
        array('status' => 'completed', 'bonus_settled' => 1),
        array('id' => $record_id)
    );
    
    return true;
}

// Handle status update (previously points sending)
function commission_send_points_handler() {
    if (!wp_verify_nonce($_POST['nonce'], 'commission_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $record_id = intval($_POST['record_id']);
    
    global $wpdb;
    $records_table = $wpdb->prefix . 'commission_records';
    
    // Get commission record
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $records_table WHERE id = %d",
        $record_id
    ), ARRAY_A);
    
    if (!$record) {
        wp_send_json_error('Record not found');
    }
    
    // Update status to completed (no actual points sending)
    $wpdb->update(
        $records_table,
        array('status' => 'completed'),
        array('id' => $record_id),
        array('%s'),
        array('%d')
    );
    
    wp_send_json_success('Status updated successfully');
}

// Handle general report export
function commission_export_report_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $holder_email = isset($_GET['holder']) ? sanitize_email($_GET['holder']) : '';
    $reports = get_commission_reports($holder_email, 1000, 0); // Get all records
    
    // Set headers for CSV download with UTF-8 BOM for proper Chinese display
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="commission_general_report_' . date('Y-m-d_H-i-s') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility with Chinese characters
    fwrite($output, "\xEF\xBB\xBF");
    
    // CSV headers
    fputcsv($output, array(
        'Order ID',
        'Coupon Code', 
        'Holder Email',
        'Teacher Email',
        'Director Email',
        'Order Total',
        'Holder Commission',
        'Teacher Commission', 
        'Director Commission',
        'Holder Rate',
        'Teacher Rate',
        'Director Rate',
        'Status',
        'Created Date'
    ));
    
    // CSV data
    foreach ($reports as $report) {
        fputcsv($output, array(
            $report['order_id'],
            $report['coupon_code'],
            $report['holder_email'],
            $report['teacher_email'] ?: 'N/A',
            $report['director_email'] ?: 'N/A',
            '$' . number_format($report['order_total'], 2),
            '$' . number_format($report['holder_commission'], 2),
            '$' . number_format($report['teacher_commission'], 2),
            '$' . number_format($report['director_commission'], 2),
            $report['holder_rate'] . '%',
            $report['teacher_rate'] . '%',
            $report['director_rate'] . '%',
            $report['status'],
            $report['created_at']
        ));
    }
    
    fclose($output);
    exit;
}

// Handle holder report export
function commission_export_holder_report_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $holder_email = isset($_GET['holder']) ? sanitize_email($_GET['holder']) : '';
    $reports = get_holder_commission_reports($holder_email);
    
    $filename = 'commission_holder_report_' . date('Y-m-d_H-i-s');
    if (!empty($holder_email)) {
        $filename .= '_' . sanitize_file_name(str_replace('@', '_at_', $holder_email));
    }
    $filename .= '.csv';
    
    // Set headers for CSV download with UTF-8 BOM for proper Chinese display
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility with Chinese characters
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
    foreach ($reports as $report) {
        // Add bonus row if bonus exists
        if (!empty($report['bonus_payment_type']) && $report['bonus_payment_type'] === 'bonus') {
            fputcsv($output, array(
                $report['order_id'],
                $report['coupon_code'],
                $report['holder_email'],
                '$' . number_format($report['order_total'], 2),
                '$' . number_format($report['holder_commission'], 2),
                $report['holder_rate'] . '%',
                'bonus',
                $report['bonus_created_at'] ?: $report['created_at']
            ));
        }
        
        // Add point row if points exist
        if (!empty($report['point_payment_type']) && $report['point_payment_type'] === 'point') {
            fputcsv($output, array(
                $report['order_id'],
                $report['coupon_code'],
                $report['holder_email'],
                '$' . number_format($report['order_total'], 2),
                '$' . number_format($report['holder_commission'], 2),
                $report['holder_rate'] . '%',
                'point',
                $report['point_created_at'] ?: $report['created_at']
            ));
        } elseif (isset($report['points_sent']) && $report['points_sent']) {
            // Handle legacy points_sent flag
            fputcsv($output, array(
                $report['order_id'],
                $report['coupon_code'],
                $report['holder_email'],
                '$' . number_format($report['order_total'], 2),
                '$' . number_format($report['holder_commission'], 2),
                $report['holder_rate'] . '%',
                'point',
                $report['created_at']
            ));
        }
        
        // If neither bonus nor points exist, show the basic record
        if (empty($report['bonus_payment_type']) && empty($report['point_payment_type']) && (!isset($report['points_sent']) || !$report['points_sent'])) {
            fputcsv($output, array(
                $report['order_id'],
                $report['coupon_code'],
                $report['holder_email'],
                '$' . number_format($report['order_total'], 2),
                '$' . number_format($report['holder_commission'], 2),
                $report['holder_rate'] . '%',
                'pending',
                $report['created_at']
            ));
        }
    }
    
    fclose($output);
    exit;
}

// Handle teacher report export
function commission_export_teacher_report_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $teacher_email = isset($_GET['teacher']) ? sanitize_email($_GET['teacher']) : '';
    $reports = get_teacher_commission_reports($teacher_email);
    
    $filename = 'commission_teacher_report_' . date('Y-m-d_H-i-s');
    if (!empty($teacher_email)) {
        $filename .= '_' . sanitize_file_name(str_replace('@', '_at_', $teacher_email));
    }
    $filename .= '.csv';
    
    // Set headers for CSV download with UTF-8 BOM for proper Chinese display
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility with Chinese characters
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
    foreach ($reports as $report) {
        // Only include records with teacher commission
        if (!empty($report['teacher_email']) && $report['teacher_commission'] > 0) {
            // Add bonus row if bonus exists
            if (!empty($report['teacher_bonus_payment_type']) && $report['teacher_bonus_payment_type'] === 'bonus') {
                fputcsv($output, array(
                    $report['order_id'],
                    $report['coupon_code'],
                    $report['teacher_email'],
                    '$' . number_format($report['order_total'], 2),
                    '$' . number_format($report['teacher_commission'], 2),
                    $report['teacher_rate'] . '%',
                    'bonus',
                    $report['teacher_bonus_created_at'] ?: $report['created_at']
                ));
            }
            
            // Add point row if points exist
            if (!empty($report['teacher_point_payment_type']) && $report['teacher_point_payment_type'] === 'point') {
                fputcsv($output, array(
                    $report['order_id'],
                    $report['coupon_code'],
                    $report['teacher_email'],
                    '$' . number_format($report['order_total'], 2),
                    '$' . number_format($report['teacher_commission'], 2),
                    $report['teacher_rate'] . '%',
                    'point',
                    $report['teacher_point_created_at'] ?: $report['created_at']
                ));
            } elseif (isset($report['teacher_points_sent']) && $report['teacher_points_sent']) {
                // Handle legacy teacher_points_sent flag
                fputcsv($output, array(
                    $report['order_id'],
                    $report['coupon_code'],
                    $report['teacher_email'],
                    '$' . number_format($report['order_total'], 2),
                    '$' . number_format($report['teacher_commission'], 2),
                    $report['teacher_rate'] . '%',
                    'point',
                    $report['created_at']
                ));
            }
            
            // If neither bonus nor points exist, show the basic record
            if (empty($report['teacher_bonus_payment_type']) && empty($report['teacher_point_payment_type']) && (!isset($report['teacher_points_sent']) || !$report['teacher_points_sent'])) {
                fputcsv($output, array(
                    $report['order_id'],
                    $report['coupon_code'],
                    $report['teacher_email'],
                    '$' . number_format($report['order_total'], 2),
                    '$' . number_format($report['teacher_commission'], 2),
                    $report['teacher_rate'] . '%',
                    'pending',
                    $report['created_at']
                ));
            }
        }
    }
    
    fclose($output);
    exit;
}

// Handle director report export
function commission_export_director_report_handler() {
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $director_email = isset($_GET['director']) ? sanitize_email($_GET['director']) : '';
    $reports = get_director_commission_reports($director_email);
    
    $filename = 'commission_director_report_' . date('Y-m-d_H-i-s');
    if (!empty($director_email)) {
        $filename .= '_' . sanitize_file_name(str_replace('@', '_at_', $director_email));
    }
    $filename .= '.csv';
    
    // Set headers for CSV download with UTF-8 BOM for proper Chinese display
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility with Chinese characters
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
    foreach ($reports as $report) {
        // Only include records with director commission
        if (!empty($report['director_email']) && $report['director_commission'] > 0) {
            // Add bonus row if bonus exists
            if (!empty($report['director_bonus_payment_type']) && $report['director_bonus_payment_type'] === 'bonus') {
                fputcsv($output, array(
                    $report['order_id'],
                    $report['coupon_code'],
                    $report['director_email'],
                    '$' . number_format($report['order_total'], 2),
                    '$' . number_format($report['director_commission'], 2),
                    $report['director_rate'] . '%',
                    'bonus',
                    $report['director_bonus_created_at'] ?: $report['created_at']
                ));
            }
            
            // Add point row if points exist
            if (!empty($report['director_point_payment_type']) && $report['director_point_payment_type'] === 'point') {
                fputcsv($output, array(
                    $report['order_id'],
                    $report['coupon_code'],
                    $report['director_email'],
                    '$' . number_format($report['order_total'], 2),
                    '$' . number_format($report['director_commission'], 2),
                    $report['director_rate'] . '%',
                    'point',
                    $report['director_point_created_at'] ?: $report['created_at']
                ));
            } elseif (isset($report['director_points_sent']) && $report['director_points_sent']) {
                // Handle legacy director_points_sent flag
                fputcsv($output, array(
                    $report['order_id'],
                    $report['coupon_code'],
                    $report['director_email'],
                    '$' . number_format($report['order_total'], 2),
                    '$' . number_format($report['director_commission'], 2),
                    $report['director_rate'] . '%',
                    'point',
                    $report['created_at']
                ));
            }
            
            // If neither bonus nor points exist, show the basic record
            if (empty($report['director_bonus_payment_type']) && empty($report['director_point_payment_type']) && (!isset($report['director_points_sent']) || !$report['director_points_sent'])) {
                fputcsv($output, array(
                    $report['order_id'],
                    $report['coupon_code'],
                    $report['director_email'],
                    '$' . number_format($report['order_total'], 2),
                    '$' . number_format($report['director_commission'], 2),
                    $report['director_rate'] . '%',
                    'pending',
                    $report['created_at']
                ));
            }
        }
    }
    
    fclose($output);
    exit;
}

// Get coupon data for editing
function commission_get_coupon_data_handler() {
    if (!wp_verify_nonce($_POST['nonce'], 'commission_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $coupon_id = intval($_POST['coupon_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_coupons';
    
    $coupon = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $coupon_id
    ), ARRAY_A);
    
    if ($coupon) {
        wp_send_json_success($coupon);
    } else {
        wp_send_json_error('Coupon not found');
    }
}

// Update coupon via AJAX
function commission_update_coupon_handler() {
    if (!wp_verify_nonce($_POST['nonce'], 'commission_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    $coupon_id = intval($_POST['coupon_id']);
    $result = update_commission_coupon($coupon_id, $_POST);
    
    if ($result !== false) {
        wp_send_json_success('Coupon updated successfully');
    } else {
        wp_send_json_error('Failed to update coupon');
    }
}

// REST API endpoints
add_action('rest_api_init', 'commission_register_rest_routes');

function commission_register_rest_routes() {
    register_rest_route('commission/v1', '/coupons', array(
        'methods' => 'GET',
        'callback' => 'commission_rest_get_coupons',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    register_rest_route('commission/v1', '/reports', array(
        'methods' => 'GET',
        'callback' => 'commission_rest_get_reports',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
}

function commission_rest_get_coupons($request) {
    $coupons = get_commission_coupons();
    return rest_ensure_response($coupons);
}

function commission_rest_get_reports($request) {
    $holder_email = $request->get_param('holder_email');
    $reports = get_commission_reports($holder_email);
    return rest_ensure_response($reports);
}
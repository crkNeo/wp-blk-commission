<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

function commission_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Commission coupons table
    $table_name = $wpdb->prefix . 'commission_coupons';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        coupon_code varchar(100) NOT NULL,
        holder_email varchar(100) NOT NULL,
        teacher_email varchar(100) DEFAULT '',
        director_email varchar(100) DEFAULT '',
        teacher_rate decimal(5,2) DEFAULT 5.00,
        director_rate decimal(5,2) DEFAULT 5.00,
        discount_percentage decimal(5,2) DEFAULT 10.00,
        wc_coupon_id mediumint(9) DEFAULT NULL,
        status varchar(20) DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY coupon_code (coupon_code),
        KEY holder_email (holder_email),
        KEY wc_coupon_id (wc_coupon_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Commission downlines table
    $table_name = $wpdb->prefix . 'commission_downlines';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        holder_email varchar(100) NOT NULL,
        downline_email varchar(100) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_downline (holder_email, downline_email),
        KEY holder_email (holder_email),
        KEY downline_email (downline_email)
    ) $charset_collate;";
    
    dbDelta($sql);
    
    // Commission records table
    $table_name = $wpdb->prefix . 'commission_records';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id mediumint(9) NOT NULL,
        coupon_code varchar(100) NOT NULL,
        holder_email varchar(100) NOT NULL,
        teacher_email varchar(100) DEFAULT '',
        director_email varchar(100) DEFAULT '',
        order_total decimal(10,2) NOT NULL,
        holder_commission decimal(10,2) NOT NULL,
        teacher_commission decimal(10,2) DEFAULT 0.00,
        director_commission decimal(10,2) DEFAULT 0.00,
        holder_rate decimal(5,2) NOT NULL,
        teacher_rate decimal(5,2) DEFAULT 0.00,
        director_rate decimal(5,2) DEFAULT 0.00,
        status varchar(20) DEFAULT 'pending',
        bonus_settled tinyint(1) DEFAULT 0,
        points_sent tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY holder_email (holder_email),
        KEY teacher_email (teacher_email),
        KEY director_email (director_email),
        KEY status (status)
    ) $charset_collate;";
    
    dbDelta($sql);
    
    // Add missing columns to existing tables (check if they exist first)
    $records_table = $wpdb->prefix . 'commission_records';
    
    // Check and add teacher_points_sent column
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $records_table LIKE 'teacher_points_sent'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $records_table ADD COLUMN teacher_points_sent TINYINT(1) DEFAULT 0");
    }
    
    // Check and add director_points_sent column
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $records_table LIKE 'director_points_sent'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $records_table ADD COLUMN director_points_sent TINYINT(1) DEFAULT 0");
    }
    
    // Commission payments table (for tracking bonus and points)
    $table_name = $wpdb->prefix . 'commission_payments';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_email varchar(100) NOT NULL,
        user_type varchar(20) NOT NULL,
        amount decimal(10,2) NOT NULL,
        payment_type varchar(20) NOT NULL,
        commission_record_id mediumint(9) NOT NULL,
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_email (user_email),
        KEY commission_record_id (commission_record_id),
        KEY payment_type (payment_type),
        KEY status (status)
    ) $charset_collate;";
    
    dbDelta($sql);
}

// Helper functions for database operations
function get_commission_coupons($limit = 20, $offset = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_coupons';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $limit, $offset
    ), ARRAY_A);
}

function create_commission_coupon($data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_coupons';
    
    $coupon_code = sanitize_text_field($data['coupon_code']);
    $discount_percentage = floatval($data['discount_amount']);
    
    // First, create WooCommerce coupon
    $wc_coupon_id = create_woocommerce_coupon($coupon_code, $discount_percentage);
    
    if (!$wc_coupon_id) {
        return false; // Failed to create WooCommerce coupon
    }
    
    // Then create commission coupon record
    $result = $wpdb->insert($table_name, array(
        'coupon_code' => $coupon_code,
        'holder_email' => sanitize_email($data['holder_email']),
        'teacher_email' => sanitize_email($data['teacher_email']),
        'director_email' => sanitize_email($data['director_email']),
        'teacher_rate' => floatval($data['teacher_rate']),
        'director_rate' => floatval($data['director_rate']),
        'discount_percentage' => $discount_percentage,
        'wc_coupon_id' => $wc_coupon_id,
        'status' => 'active'
    ));
    
    if (!$result) {
        // If commission record creation fails, delete the WooCommerce coupon
        wp_delete_post($wc_coupon_id, true);
        return false;
    }
    
    return $result;
}

// Create WooCommerce coupon
function create_woocommerce_coupon($coupon_code, $discount_percentage) {
    // Check if coupon already exists
    $existing_coupon = get_page_by_title($coupon_code, OBJECT, 'shop_coupon');
    if ($existing_coupon) {
        return false; // Coupon already exists
    }
    
    // Create WooCommerce coupon
    $coupon = array(
        'post_title' => $coupon_code,
        'post_content' => 'Commission System Coupon',
        'post_status' => 'publish',
        'post_author' => get_current_user_id(),
        'post_type' => 'shop_coupon'
    );
    
    $coupon_id = wp_insert_post($coupon);
    
    if ($coupon_id) {
        // Set coupon meta data
        update_post_meta($coupon_id, 'discount_type', 'percent');
        update_post_meta($coupon_id, 'coupon_amount', $discount_percentage);
        update_post_meta($coupon_id, 'individual_use', 'yes');
        update_post_meta($coupon_id, 'usage_limit', ''); // Unlimited usage
        update_post_meta($coupon_id, 'usage_limit_per_user', '1'); // One use per user
        update_post_meta($coupon_id, 'limit_usage_to_x_items', '');
        update_post_meta($coupon_id, 'free_shipping', 'no');
        update_post_meta($coupon_id, 'exclude_sale_items', 'no');
        update_post_meta($coupon_id, 'minimum_amount', '');
        update_post_meta($coupon_id, 'maximum_amount', '');
        update_post_meta($coupon_id, 'customer_email', '');
        
        // Mark as commission system coupon
        update_post_meta($coupon_id, '_commission_system_coupon', 'yes');
        
        return $coupon_id;
    }
    
    return false;
}

function update_commission_coupon($id, $data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_coupons';
    
    return $wpdb->update(
        $table_name,
        array(
            'holder_email' => sanitize_email($data['holder_email']),
            'teacher_email' => sanitize_email($data['teacher_email']),
            'director_email' => sanitize_email($data['director_email']),
            'teacher_rate' => floatval($data['teacher_rate']),
            'director_rate' => floatval($data['director_rate']),
            'status' => sanitize_text_field($data['status'])
        ),
        array('id' => $id)
    );
}

function delete_commission_coupon($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_coupons';
    
    // Get coupon data first
    $coupon_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $id
    ), ARRAY_A);
    
    if ($coupon_data) {
        // Delete WooCommerce coupon if exists
        if (!empty($coupon_data['wc_coupon_id'])) {
            wp_delete_post($coupon_data['wc_coupon_id'], true);
        }
        
        // Delete commission coupon record
        return $wpdb->delete($table_name, array('id' => $id));
    }
    
    return false;
}

function get_commission_reports($holder_email = '', $limit = 50, $offset = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_records';
    
    $where_clause = '';
    $params = array();
    
    if (!empty($holder_email)) {
        $where_clause = 'WHERE holder_email = %s';
        $params[] = $holder_email;
    }
    
    $params[] = $limit;
    $params[] = $offset;
    
    $sql = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
    
    return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
}

function get_commission_summary($holder_email) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_records';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as total_orders,
            SUM(order_total) as total_sales,
            SUM(holder_commission) as total_holder_commission,
            SUM(teacher_commission) as total_teacher_commission,
            SUM(director_commission) as total_director_commission
        FROM $table_name 
        WHERE holder_email = %s",
        $holder_email
    ), ARRAY_A);
}

function update_commission_payment_status($record_id, $payment_type) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_records';
    
    $field = ($payment_type === 'bonus') ? 'bonus_settled' : 'points_sent';
    
    return $wpdb->update(
        $table_name,
        array($field => 1),
        array('id' => $record_id)
    );
}

function get_all_commission_holders() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_coupons';
    
    return $wpdb->get_results(
        "SELECT DISTINCT holder_email FROM $table_name WHERE status = 'active'",
        ARRAY_A
    );
}

// Get holder commission reports with payment details
function get_holder_commission_reports($holder_email = '') {
    global $wpdb;
    $records_table = $wpdb->prefix . 'commission_records';
    $payments_table = $wpdb->prefix . 'commission_payments';
    
    $where_clause = '';
    $params = array();
    
    if (!empty($holder_email)) {
        $where_clause = 'WHERE r.holder_email = %s';
        $params[] = $holder_email;
    }
    
    $sql = "SELECT r.*, 
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
            $where_clause 
            ORDER BY r.created_at DESC";
    
    if (!empty($params)) {
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    } else {
        return $wpdb->get_results($sql, ARRAY_A);
    }
}

// Get teacher commission reports with payment details
function get_teacher_commission_reports($teacher_email = '') {
    global $wpdb;
    $records_table = $wpdb->prefix . 'commission_records';
    $payments_table = $wpdb->prefix . 'commission_payments';
    
    $where_clause = 'WHERE r.teacher_email IS NOT NULL AND r.teacher_email != ""';
    $params = array();
    
    if (!empty($teacher_email)) {
        $where_clause .= ' AND r.teacher_email = %s';
        $params[] = $teacher_email;
    }
    
    $sql = "SELECT r.*, 
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
            $where_clause 
            ORDER BY r.created_at DESC";
    
    if (!empty($params)) {
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    } else {
        return $wpdb->get_results($sql, ARRAY_A);
    }
}

// Get director commission reports with payment details
function get_director_commission_reports($director_email = '') {
    global $wpdb;
    $records_table = $wpdb->prefix . 'commission_records';
    $payments_table = $wpdb->prefix . 'commission_payments';
    
    $where_clause = 'WHERE r.director_email IS NOT NULL AND r.director_email != ""';
    $params = array();
    
    if (!empty($director_email)) {
        $where_clause .= ' AND r.director_email = %s';
        $params[] = $director_email;
    }
    
    $sql = "SELECT r.*, 
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
            $where_clause 
            ORDER BY r.created_at DESC";
    
    if (!empty($params)) {
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    } else {
        return $wpdb->get_results($sql, ARRAY_A);
    }
}

// Get all teachers
function get_all_commission_teachers() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_coupons';
    
    return $wpdb->get_results(
        "SELECT DISTINCT teacher_email FROM $table_name WHERE status = 'active' AND teacher_email IS NOT NULL AND teacher_email != ''",
        ARRAY_A
    );
}

// Get all directors
function get_all_commission_directors() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'commission_coupons';
    
    return $wpdb->get_results(
        "SELECT DISTINCT director_email FROM $table_name WHERE status = 'active' AND director_email IS NOT NULL AND director_email != ''",
        ARRAY_A
    );
}

// Mark holder as processed (no actual points sending)
function apply_holder_points($record_id) {
    global $wpdb;
    $records_table = $wpdb->prefix . 'commission_records';
    
    // Get commission record
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $records_table WHERE id = %d",
        $record_id
    ), ARRAY_A);
    
    if (!$record || $record['points_sent']) {
        return false;
    }
    
    // Simply update status to processed (no actual points sending)
    $wpdb->update(
        $records_table,
        array('points_sent' => 1),
        array('id' => $record_id),
        array('%d'),
        array('%d')
    );
    
    // Log the status change
    error_log("Holder commission marked as processed: Record ID=$record_id, Email={$record['holder_email']}, Amount={$record['holder_commission']}");

    $payments_table = $wpdb->prefix . 'commission_payments';
    $wpdb->update(
        $payments_table,
        array('payment_type' => 'point'),
        array(
            'commission_record_id' => $record_id,
            'user_email' => $record['holder_email'],
            'user_type' => 'holder'
        )
    );

    // Log the payment type change
    error_log("Holder payment type updated to 'point': Record ID=$record_id, Email={$record['holder_email']}, Amount={$record['holder_commission']}");
    return true;
}

// Mark teacher as processed (no actual points sending)
function apply_teacher_points($record_id) {
    global $wpdb;
    $records_table = $wpdb->prefix . 'commission_records';
    
    // Get commission record
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $records_table WHERE id = %d",
        $record_id
    ), ARRAY_A);
    
    if (!$record) {
        error_log("Teacher status update failed: Record not found for ID $record_id");
        return false;
    }
    
    if (isset($record['teacher_points_sent']) && $record['teacher_points_sent']) {
        error_log("Teacher status update failed: Already processed for record ID $record_id");
        return false;
    }
    
    if (empty($record['teacher_email']) || $record['teacher_commission'] <= 0) {
        error_log("Teacher status update failed: Invalid data for record ID $record_id");
        return false;
    }
    
    // Add teacher_points_sent column if it doesn't exist
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $records_table LIKE 'teacher_points_sent'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $records_table ADD COLUMN teacher_points_sent TINYINT(1) DEFAULT 0");
    }
    
    // Simply update status to processed
    $wpdb->update(
        $records_table,
        array('teacher_points_sent' => 1),
        array('id' => $record_id),
        array('%d'),
        array('%d')
    );
    
    // Log the status change
    error_log("Teacher commission marked as processed: Record ID=$record_id, Email={$record['teacher_email']}, Amount={$record['teacher_commission']}");

    $payments_table = $wpdb->prefix . 'commission_payments';
    $wpdb->update(
        $payments_table,
        array('payment_type' => 'point'),
        array(
            'commission_record_id' => $record_id,
            'user_email' => $record['teacher_email'],
            'user_type' => 'teacher'
        )
    );
    return true;
}

// Mark director as processed (no actual points sending)
function apply_director_points($record_id) {
    global $wpdb;
    $records_table = $wpdb->prefix . 'commission_records';
    
    // Get commission record
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $records_table WHERE id = %d",
        $record_id
    ), ARRAY_A);
    
    if (!$record || isset($record['director_points_sent']) && $record['director_points_sent']) {
        return false;
    }
    
    if ($record['director_commission'] <= 0 || empty($record['director_email'])) {
        return false;
    }
    
    // Add director_points_sent column if it doesn't exist
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $records_table LIKE 'director_points_sent'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $records_table ADD COLUMN director_points_sent TINYINT(1) DEFAULT 0");
    }
    
    // Simply update status to processed
    $wpdb->update(
        $records_table,
        array('director_points_sent' => 1),
        array('id' => $record_id),
        array('%d'),
        array('%d')
    );
    
    // Log the status change
    error_log("Director commission marked as processed: Record ID=$record_id, Email={$record['director_email']}, Amount={$record['director_commission']}");

    $payments_table = $wpdb->prefix . 'commission_payments';
    $wpdb->update(
        $payments_table,
        array('payment_type' => 'point'),
        array(
            'commission_record_id' => $record_id,
            'user_email' => $record['director_email'],
            'user_type' => 'director'
        )
    );

    return true;
}

// Update user status (no actual points sending)
function send_points_to_user($email, $points, $description) {
    // Get user by email
    $user = get_user_by('email', $email);
    if (!$user) {
        error_log("User not found for email: $email");
        return false;
    }
    
    // Simply log that processing was requested (no actual points sending)
    error_log("Commission processing requested: Email=$email, Points=$points, Description=$description (Status updated only, no points sent)");
    
    return true;
}

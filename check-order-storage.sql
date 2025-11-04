-- ================================================================
-- 檢查 WooCommerce 訂單存儲方式
-- ================================================================

-- 1. 檢查是否存在 wp_wc_orders 表（HPOS）
SHOW TABLES LIKE 'wp_wc_orders';

-- 2. 檢查訂單 9915 在哪裡
-- 方式 A: 在 wp_posts 中查找
SELECT 'wp_posts' as table_name, ID, post_type, post_status, post_date
FROM wp_posts
WHERE ID = 9915;

-- 方式 B: 在 wp_wc_orders 中查找（如果表存在）
-- SELECT 'wp_wc_orders' as table_name, id, type, status, date_created_gmt
-- FROM wp_wc_orders
-- WHERE id = 9915;

-- 3. 檢查訂單 9915 的 billing_email（在 postmeta 中）
SELECT meta_key, meta_value
FROM wp_postmeta
WHERE post_id = 9915
AND meta_key IN ('_billing_email', '_customer_user');

-- 4. 查找 user03 的所有訂單（在 wp_posts 中）
SELECT
    p.ID as order_id,
    p.post_type,
    p.post_status,
    p.post_date,
    pm_email.meta_value as billing_email,
    pm_coupon.meta_value as coupon_used
FROM wp_posts p
INNER JOIN wp_postmeta pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
LEFT JOIN wp_postmeta pm_coupon ON p.ID = pm_coupon.post_id AND pm_coupon.meta_key = '_commission_coupon_used'
WHERE pm_email.meta_value = 'user03@gmail.com'
AND p.post_type = 'shop_order'
ORDER BY p.post_date ASC;

-- 5. 直接檢查：commission_records 的訂單是否屬於 user03
SELECT
    cr.id as record_id,
    cr.order_id,
    cr.coupon_code,
    cr.holder_email,
    p.post_status as order_status,
    pm.meta_value as billing_email
FROM wp_commission_records cr
LEFT JOIN wp_posts p ON cr.order_id = p.ID
LEFT JOIN wp_postmeta pm ON cr.order_id = pm.post_id AND pm.meta_key = '_billing_email'
WHERE cr.id IN (9914, 9915);

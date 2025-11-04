-- ================================================================
-- 快速檢查：你的 WooCommerce 使用哪種存儲模式
-- ================================================================

-- 1. 檢查是否有 wp_wc_orders 表（HPOS）
SHOW TABLES LIKE 'wp_wc_orders';

-- 如果返回結果，說明啟用了 HPOS

-- 2. 查看訂單 9915 的 billing_email（HPOS 模式）
SELECT meta_key, meta_value
FROM wp_wc_orders_meta
WHERE order_id = 9915
AND meta_key = '_billing_email';

-- 3. 查看訂單 9915 的基本信息（HPOS 模式）
SELECT id, status, billing_email, customer_id, date_created_gmt
FROM wp_wc_orders
WHERE id = 9915;

-- 4. 查看訂單 9915 在 wp_postmeta 中有什麼
SELECT meta_key, LEFT(meta_value, 50) as meta_value_preview
FROM wp_postmeta
WHERE post_id = 9915
ORDER BY meta_key;

-- 5. 檢查 WooCommerce 設定
SELECT option_name, option_value
FROM wp_options
WHERE option_name IN (
    'woocommerce_custom_orders_table_enabled',
    'woocommerce_feature_custom_order_tables_enabled'
);

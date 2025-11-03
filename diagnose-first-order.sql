-- ================================================================
-- 確認第一筆訂單是否創建了分潤記錄
-- ================================================================

-- 1. 查看 user02 的所有訂單（包括狀態）
-- ================================================================
SELECT
    p.ID as order_id,
    p.post_status as status,
    p.post_date as created_at,
    pm_coupon.meta_value as coupon_used
FROM wp_posts p
INNER JOIN wp_postmeta pm_email ON p.ID = pm_email.post_id
LEFT JOIN wp_postmeta pm_coupon ON p.ID = pm_coupon.post_id AND pm_coupon.meta_key = '_commission_coupon_used'
WHERE pm_email.meta_key = '_billing_email'
AND pm_email.meta_value = 'user02@gmail.com'
AND p.post_type = 'shop_order'
ORDER BY p.post_date ASC;

-- 檢查：
-- 1. 第一筆訂單的 post_status 是否為 'wc-completed'
-- 2. _commission_coupon_used 是否有值

-- ================================================================
-- 2. 查看第一筆訂單的所有訂單備註
-- ================================================================
SELECT
    c.comment_content,
    c.comment_date
FROM wp_comments c
WHERE c.comment_post_ID = (
    SELECT p.ID
    FROM wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    WHERE pm.meta_key = '_billing_email'
    AND pm.meta_value = 'user02@gmail.com'
    AND p.post_type = 'shop_order'
    ORDER BY p.post_date ASC
    LIMIT 1
)
AND c.comment_type = 'order_note'
ORDER BY c.comment_date ASC;

-- 檢查：
-- 1. 是否有 "Commission processed for Order ID" 的備註
-- 2. 是否有 "SCENARIO 1" 的備註
-- 3. 是否有錯誤訊息

-- ================================================================
-- 3. 查看第一筆訂單的商品分類
-- ================================================================
SELECT
    oi.order_id,
    p.post_title as product_name,
    GROUP_CONCAT(t.slug) as categories
FROM wp_woocommerce_order_items oi
INNER JOIN wp_woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
INNER JOIN wp_posts p ON oim.meta_value = p.ID
LEFT JOIN wp_term_relationships tr ON p.ID = tr.object_id
LEFT JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
LEFT JOIN wp_terms t ON tt.term_id = t.term_id
WHERE oim.meta_key = '_product_id'
AND oi.order_id = (
    SELECT p.ID
    FROM wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    WHERE pm.meta_key = '_billing_email'
    AND pm.meta_value = 'user02@gmail.com'
    AND p.post_type = 'shop_order'
    ORDER BY p.post_date ASC
    LIMIT 1
)
GROUP BY oi.order_id, p.post_title;

-- 檢查：
-- 是否包含 'member-events' 或 'featured-events' 分類
-- 如果是，分潤記錄不會創建

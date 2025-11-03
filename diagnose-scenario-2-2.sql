-- ================================================================
-- 診斷情境 2-2 問題：為什麼系統認為沒有購買記錄
-- ================================================================

-- 1. 檢查 user02 的所有訂單
-- ================================================================
SELECT
    p.ID as order_id,
    p.post_status as order_status,
    p.post_date,
    pm.meta_value as billing_email
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE pm.meta_key = '_billing_email'
AND pm.meta_value = 'user02@gmail.com'
AND p.post_type = 'shop_order'
ORDER BY p.post_date DESC;

-- 應該看到至少兩筆訂單（第一筆用 qqq123，第二筆用 qwe123）

-- ================================================================
-- 2. 檢查 user02 的所有分潤記錄
-- ================================================================
SELECT
    cr.id,
    cr.order_id,
    cr.coupon_code,
    cr.holder_email,
    cr.order_total,
    cr.holder_commission,
    cr.created_at
FROM wp_commission_records cr
WHERE cr.order_id IN (
    SELECT p.ID
    FROM wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    WHERE pm.meta_key = '_billing_email'
    AND pm.meta_value = 'user02@gmail.com'
    AND p.post_type = 'shop_order'
)
ORDER BY cr.created_at DESC;

-- 應該看到第一筆訂單的分潤記錄（holder_email = neoc860927@gmail.com）

-- ================================================================
-- 3. 測試 commission_user_has_purchase_history 的 SQL 查詢
-- ================================================================
-- 這是代碼中實際使用的查詢
SELECT COUNT(*) as purchase_count
FROM wp_commission_records cr
INNER JOIN wp_posts p ON cr.order_id = p.ID
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE pm.meta_key = '_billing_email'
AND pm.meta_value = 'user02@gmail.com';

-- 這個查詢應該返回 > 0
-- 如果返回 0，表示 JOIN 有問題

-- ================================================================
-- 4. 簡化的查詢（不依賴 JOIN）
-- ================================================================
-- 直接通過 commission_records 表的 order_id 查找
SELECT COUNT(DISTINCT cr.order_id) as purchase_count
FROM wp_commission_records cr
WHERE EXISTS (
    SELECT 1
    FROM wp_postmeta pm
    WHERE pm.post_id = cr.order_id
    AND pm.meta_key = '_billing_email'
    AND pm.meta_value = 'user02@gmail.com'
);

-- ================================================================
-- 5. 檢查第二筆訂單執行時的時間點
-- ================================================================
-- 查看第二筆訂單的訂單備註
SELECT
    comment_content,
    comment_date
FROM wp_comments
WHERE comment_post_ID = (
    SELECT p.ID
    FROM wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    WHERE pm.meta_key = '_billing_email'
    AND pm.meta_value = 'user02@gmail.com'
    AND p.post_type = 'shop_order'
    ORDER BY p.post_date DESC
    LIMIT 1 OFFSET 0  -- 最新的訂單（第二筆）
)
AND comment_type = 'order_note'
ORDER BY comment_date DESC;

-- 應該看到 "SCENARIO 2-1" 或 "SCENARIO 2-2" 的備註

-- ================================================================
-- 6. 檢查第一筆和第二筆訂單的處理順序
-- ================================================================
-- 查看兩筆訂單的完成時間
SELECT
    p.ID as order_id,
    pm_coupon.meta_value as coupon_used,
    p.post_date as order_created,
    (SELECT MAX(comment_date) FROM wp_comments
     WHERE comment_post_ID = p.ID
     AND comment_type = 'order_note'
     AND comment_content LIKE '%SCENARIO%') as scenario_time,
    (SELECT created_at FROM wp_commission_records
     WHERE order_id = p.ID LIMIT 1) as commission_created
FROM wp_posts p
LEFT JOIN wp_postmeta pm_coupon ON p.ID = pm_coupon.post_id AND pm_coupon.meta_key = '_commission_coupon_used'
INNER JOIN wp_postmeta pm_email ON p.ID = pm_email.post_id
WHERE pm_email.meta_key = '_billing_email'
AND pm_email.meta_value = 'user02@gmail.com'
AND p.post_type = 'shop_order'
ORDER BY p.post_date DESC;

-- ================================================================
-- 診斷結果分析
-- ================================================================

-- 如果查詢 3 返回 0，但查詢 2 有記錄，表示：
-- → JOIN 條件有問題，需要修改 commission_user_has_purchase_history()

-- 如果查詢 3 返回 > 0，但仍然執行了轉移，表示：
-- → 代碼邏輯有其他問題，需要檢查條件判斷

-- 如果查詢 2 沒有記錄，表示：
-- → 第一筆訂單的分潤記錄沒有創建成功
-- → 需要檢查第一筆訂單為什麼沒有生成分潤記錄

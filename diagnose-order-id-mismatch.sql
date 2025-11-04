-- ================================================================
-- 診斷：為什麼 commission_records 有記錄但查詢返回空
-- ================================================================

-- 1. 找出 user03 的所有訂單 ID
SELECT
    p.ID as order_id,
    p.post_status,
    p.post_date,
    pm.meta_value as billing_email
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE pm.meta_key = '_billing_email'
AND pm.meta_value = 'user03@gmail.com'
AND p.post_type = 'shop_order'
ORDER BY p.post_date ASC;

-- 記下這些訂單 ID！

-- ================================================================
-- 2. 查看 commission_records 中最近的兩筆記錄
SELECT
    cr.id as record_id,
    cr.order_id,
    cr.coupon_code,
    cr.holder_email,
    cr.created_at,
    -- 檢查這個 order_id 是否存在於 wp_posts
    (SELECT p.post_status FROM wp_posts p WHERE p.ID = cr.order_id) as order_status,
    -- 檢查這個訂單的 billing_email
    (SELECT pm.meta_value FROM wp_postmeta pm
     WHERE pm.post_id = cr.order_id
     AND pm.meta_key = '_billing_email') as order_billing_email
FROM wp_commission_records cr
ORDER BY cr.created_at DESC
LIMIT 10;

-- 關鍵檢查：
-- 如果 order_billing_email 不是 'user03@gmail.com'
-- 說明分潤記錄的 order_id 指向了錯誤的訂單！

-- ================================================================
-- 3. 直接查看 id=9914 和 9915 的分潤記錄詳情
SELECT
    cr.id,
    cr.order_id,
    cr.coupon_code,
    cr.holder_email,
    cr.order_total,
    cr.created_at,
    -- 訂單狀態
    p.post_status as order_status,
    p.post_date as order_date,
    -- 訂單的 billing_email
    pm.meta_value as order_billing_email
FROM wp_commission_records cr
LEFT JOIN wp_posts p ON cr.order_id = p.ID
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_billing_email'
WHERE cr.id IN (9914, 9915)
ORDER BY cr.id;

-- 關鍵檢查：
-- order_billing_email 是什麼？
-- 如果不是 'user03@gmail.com'，說明記錄關聯錯了！

-- ================================================================
-- 4. 查看 user03 的兩筆訂單的完整信息
-- （假設 user03 有兩筆訂單，請替換為步驟1查到的實際訂單ID）

-- 第一筆訂單的信息
SELECT
    p.ID as order_id,
    p.post_status,
    pm_email.meta_value as billing_email,
    pm_coupon.meta_value as coupon_used,
    pm_source.meta_value as commission_source,
    -- 是否有分潤記錄
    (SELECT cr.id FROM wp_commission_records cr WHERE cr.order_id = p.ID) as commission_record_id
FROM wp_posts p
INNER JOIN wp_postmeta pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
LEFT JOIN wp_postmeta pm_coupon ON p.ID = pm_coupon.post_id AND pm_coupon.meta_key = '_commission_coupon_used'
LEFT JOIN wp_postmeta pm_source ON p.ID = pm_source.post_id AND pm_source.meta_key = '_commission_source'
WHERE pm_email.meta_value = 'user03@gmail.com'
AND p.post_type = 'shop_order'
ORDER BY p.post_date ASC;

-- ================================================================
-- 診斷結論
-- ================================================================

/*
根據查詢結果判斷：

情況 A：commission_records 的 order_id 指向錯誤的訂單
    - 步驟3顯示 order_billing_email 不是 'user03@gmail.com'
    - 說明創建分潤記錄時使用了錯誤的 order_id
    → 需要檢查為什麼 order_id 會錯誤

情況 B：user03 的訂單根本沒有創建分潤記錄
    - 步驟1顯示 user03 有訂單（比如 ID=1234, 1235）
    - 步驟3顯示 commission_records 的 order_id 是其他值（比如 9914, 9915）
    - 9914 和 9915 不在 user03 的訂單列表中
    → 這些分潤記錄屬於其他用戶

情況 C：訂單 ID 和分潤記錄不同步
    - user03 的訂單 ID 假設是 1234, 1235
    - 但 commission_records 存的是 9914, 9915
    → 可能是測試環境數據混亂，或者有多個測試用戶

請執行這4個查詢並提供結果！
*/

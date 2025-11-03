-- ================================================================
-- 測試購買歷史檢查邏輯
-- ================================================================

-- 第一步：找到 user02 的兩筆訂單ID
SET @user_email = 'user02@gmail.com';

-- 第一筆訂單（最早的）
SET @first_order_id = (
    SELECT p.ID FROM wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    WHERE pm.meta_key = '_billing_email'
    AND pm.meta_value = @user_email
    AND p.post_type = 'shop_order'
    ORDER BY p.post_date ASC
    LIMIT 1
);

-- 第二筆訂單（最新的）
SET @second_order_id = (
    SELECT p.ID FROM wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    WHERE pm.meta_key = '_billing_email'
    AND pm.meta_value = @user_email
    AND p.post_type = 'shop_order'
    ORDER BY p.post_date DESC
    LIMIT 1
);

SELECT
    @first_order_id as first_order_id,
    @second_order_id as second_order_id;

-- ================================================================
-- 測試1：檢查第一筆訂單是否有分潤記錄
-- ================================================================
SELECT
    'Test 1: First order commission record' as test_name,
    COUNT(*) as record_count
FROM wp_commission_records
WHERE order_id = @first_order_id;

-- 預期：1（如果第一筆訂單創建了分潤記錄）
-- 如果是 0，說明第一筆訂單沒有創建分潤記錄！

-- ================================================================
-- 測試2：檢查第二筆訂單是否有分潤記錄
-- ================================================================
SELECT
    'Test 2: Second order commission record' as test_name,
    COUNT(*) as record_count
FROM wp_commission_records
WHERE order_id = @second_order_id;

-- ================================================================
-- 測試3：模擬購買歷史檢查（不排除任何訂單）
-- ================================================================
SELECT
    'Test 3: Purchase history (no exclusion)' as test_name,
    COUNT(DISTINCT cr.id) as record_count
FROM wp_commission_records cr
WHERE EXISTS (
    SELECT 1
    FROM wp_postmeta pm
    WHERE pm.post_id = cr.order_id
    AND pm.meta_key = '_billing_email'
    AND pm.meta_value = @user_email
);

-- ================================================================
-- 測試4：模擬購買歷史檢查（排除第二筆訂單）
-- ================================================================
SELECT
    'Test 4: Purchase history (exclude 2nd order)' as test_name,
    COUNT(DISTINCT cr.id) as record_count,
    @second_order_id as excluded_order
FROM wp_commission_records cr
WHERE EXISTS (
    SELECT 1
    FROM wp_postmeta pm
    WHERE pm.post_id = cr.order_id
    AND pm.meta_key = '_billing_email'
    AND pm.meta_value = @user_email
)
AND cr.order_id != @second_order_id;

-- 預期：1（第一筆訂單的分潤記錄）
-- 如果是 0，說明有問題！

-- ================================================================
-- 測試5：檢查第一筆和第二筆訂單的狀態和完成時間
-- ================================================================
SELECT
    p.ID as order_id,
    p.post_status,
    p.post_date as created_at,
    p.post_modified as completed_at,
    pm_coupon.meta_value as coupon_used,
    cr.id as commission_record_id,
    cr.created_at as commission_created_at
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_billing_email'
LEFT JOIN wp_postmeta pm_coupon ON p.ID = pm_coupon.post_id AND pm_coupon.meta_key = '_commission_coupon_used'
LEFT JOIN wp_commission_records cr ON p.ID = cr.order_id
WHERE pm.meta_value = @user_email
AND p.post_type = 'shop_order'
ORDER BY p.post_date ASC;

-- 關鍵檢查：
-- 1. 兩筆訂單的 post_status 都是 'wc-completed'
-- 2. 第一筆訂單有 commission_record_id
-- 3. 第一筆訂單的 commission_created_at 在第二筆訂單的 completed_at 之前

-- ================================================================
-- 測試6：檢查兩筆訂單處理的時間順序
-- ================================================================
SELECT
    'First order commission created' as event,
    cr.created_at as event_time
FROM wp_commission_records cr
WHERE cr.order_id = @first_order_id

UNION ALL

SELECT
    'Second order completed' as event,
    p.post_modified as event_time
FROM wp_posts p
WHERE p.ID = @second_order_id

ORDER BY event_time ASC;

-- 如果順序是：
-- 1. Second order completed
-- 2. First order commission created
-- 說明第二筆訂單處理時，第一筆訂單的分潤記錄還沒創建！

-- ================================================================
-- 診斷結論
-- ================================================================
/*
根據測試結果：

情況 A：測試1返回0（第一筆訂單沒有分潤記錄）
    → 需要檢查第一筆訂單的訂單備註，看為什麼沒有創建
    → 可能原因：
       1. 訂單還沒完成
       2. 有錯誤發生
       3. 代碼邏輯有問題

情況 B：測試1返回1，但測試4返回0
    → SQL 查詢有問題
    → 需要修復 commission_user_has_purchase_history() 函數

情況 C：測試1返回1，測試4也返回1，但還是執行了轉移
    → 情境判斷邏輯有問題
    → 需要檢查 $has_purchase_history 變量

情況 D：測試6顯示時間順序錯誤
    → 第二筆訂單先完成，第一筆訂單後創建分潤記錄
    → 這是不可能的，除非手動改了訂單狀態
*/

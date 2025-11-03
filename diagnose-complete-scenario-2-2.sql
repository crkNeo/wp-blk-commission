-- ================================================================
-- 完整診斷 user02 的情境 2-2 問題
-- ================================================================

-- ================================================================
-- 第一部分：檢查 user02 的基本資訊
-- ================================================================

-- 1. 查看 user02 的所有訂單
SELECT
    p.ID as order_id,
    p.post_status,
    p.post_date,
    pm_coupon.meta_value as coupon_used,
    pm_source.meta_value as commission_source
FROM wp_posts p
INNER JOIN wp_postmeta pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
LEFT JOIN wp_postmeta pm_coupon ON p.ID = pm_coupon.post_id AND pm_coupon.meta_key = '_commission_coupon_used'
LEFT JOIN wp_postmeta pm_source ON p.ID = pm_source.post_id AND pm_source.meta_key = '_commission_source'
WHERE pm_email.meta_value = 'user02@gmail.com'
AND p.post_type = 'shop_order'
ORDER BY p.post_date ASC;

-- 預期結果：
-- 第一筆：coupon_used = qwe123, post_status = wc-completed
-- 第二筆：coupon_used = qqq123, post_status = wc-completed

-- ================================================================
-- 2. 查看 user02 的所有分潤記錄
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
    SELECT p.ID FROM wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    WHERE pm.meta_key = '_billing_email'
    AND pm.meta_value = 'user02@gmail.com'
    AND p.post_type = 'shop_order'
)
ORDER BY cr.created_at;

-- 關鍵檢查：
-- 第一筆訂單是否有分潤記錄？
-- 如果沒有，為什麼？

-- ================================================================
-- 3. 查看第一筆訂單的商品和分類
SELECT
    p.ID as order_id,
    prod.post_title as product_name,
    GROUP_CONCAT(DISTINCT t.slug SEPARATOR ', ') as categories
FROM wp_posts p
INNER JOIN wp_postmeta pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_billing_email'
INNER JOIN wp_woocommerce_order_items oi ON p.ID = oi.order_id AND oi.order_item_type = 'line_item'
INNER JOIN wp_woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'
INNER JOIN wp_posts prod ON oim.meta_value = prod.ID
LEFT JOIN wp_term_relationships tr ON prod.ID = tr.object_id
LEFT JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'
LEFT JOIN wp_terms t ON tt.term_id = t.term_id
WHERE pm_email.meta_value = 'user02@gmail.com'
AND p.post_type = 'shop_order'
GROUP BY p.ID, prod.post_title
ORDER BY p.post_date ASC;

-- 關鍵檢查：
-- 第一筆訂單的商品是否包含 'member-events' 或 'featured-events'？
-- 如果是，分潤記錄不會被創建！

-- ================================================================
-- 4. 查看第一筆訂單的訂單備註
SELECT
    comment_content,
    comment_date
FROM wp_comments
WHERE comment_post_ID = (
    SELECT p.ID FROM wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    WHERE pm.meta_key = '_billing_email'
    AND pm.meta_value = 'user02@gmail.com'
    AND p.post_type = 'shop_order'
    ORDER BY p.post_date ASC
    LIMIT 1
)
AND comment_type = 'order_note'
ORDER BY comment_date ASC;

-- 關鍵檢查：
-- 是否有 "SCENARIO 1" 備註？
-- 是否有 "Commission processed" 備註？
-- 是否有 "excluded product categories" 備註？
-- 是否有錯誤訊息？

-- ================================================================
-- 5. 查看第二筆訂單的訂單備註
SELECT
    comment_content,
    comment_date
FROM wp_comments
WHERE comment_post_ID = (
    SELECT p.ID FROM wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    WHERE pm.meta_key = '_billing_email'
    AND pm.meta_value = 'user02@gmail.com'
    AND p.post_type = 'shop_order'
    ORDER BY p.post_date DESC
    LIMIT 1
)
AND comment_type = 'order_note'
ORDER BY comment_date ASC;

-- 關鍵檢查：
-- 是否有 "SCENARIO 2-1" 還是 "SCENARIO 2-2"？
-- SCENARIO 2-1 = 轉移（錯誤）
-- SCENARIO 2-2 = 保持（正確）

-- ================================================================
-- 6. 查看 user02 的 user_meta
SELECT
    um.meta_key,
    um.meta_value
FROM wp_users u
INNER JOIN wp_usermeta um ON u.ID = um.user_id
WHERE u.user_email = 'user02@gmail.com'
AND um.meta_key LIKE '%commission%'
ORDER BY um.meta_key;

-- 關鍵檢查：
-- _commission_referrer_email 應該是誰？
-- 如果是 qwe123@gmail.com（第一筆訂單的推薦人）→ 正確（情境2-2）
-- 如果是 neoc860927@gmail.com（第二筆訂單的推薦人）→ 錯誤（被轉移了）

-- ================================================================
-- 7. 查看 user02 的下線記錄
SELECT
    id,
    holder_email,
    downline_email,
    created_at
FROM wp_commission_downlines
WHERE downline_email = 'user02@gmail.com'
ORDER BY created_at;

-- 關鍵檢查：
-- 應該只有一條記錄
-- holder_email 應該是 qwe123@gmail.com（第一筆訂單的推薦人）
-- 如果有兩條記錄，或 holder_email 是 neoc860927@gmail.com，表示被轉移了

-- ================================================================
-- 第二部分：診斷結果分析
-- ================================================================

/*
情況 A：第一筆訂單沒有創建分潤記錄
    原因可能：
    1. 商品在排除的分類中（member-events 或 featured-events）
       → 解決：使用不在排除分類中的商品重新測試
    2. 訂單狀態不是 wc-completed
       → 解決：確保訂單完成
    3. 分潤創建失敗（檢查訂單備註中的錯誤訊息）
       → 解決：根據錯誤訊息修復

情況 B：第一筆訂單有分潤記錄，但第二筆還是轉移了
    檢查第二筆訂單的備註：
    - 如果是 "SCENARIO 2-1"：購買歷史檢查返回 NO（錯誤）
      → 需要查看 debug.log 確認檢查結果
    - 如果是 "SCENARIO 2-2"：購買歷史檢查返回 YES（正確）
      → 但轉移邏輯還是執行了（代碼有 Bug）

情況 C：第一筆訂單有分潤記錄，第二筆顯示 SCENARIO 2-2，但下線還是改變了
    → commission_transfer_referral() 在情境2-2中被錯誤調用
    → 需要檢查代碼邏輯
*/

-- ================================================================
-- 第三部分：手動測試購買歷史檢查
-- ================================================================

-- 8. 模擬購買歷史檢查（不排除當前訂單）
SELECT COUNT(DISTINCT cr.id) as record_count
FROM wp_commission_records cr
WHERE EXISTS (
    SELECT 1
    FROM wp_postmeta pm
    WHERE pm.post_id = cr.order_id
    AND pm.meta_key = '_billing_email'
    AND pm.meta_value = 'user02@gmail.com'
);

-- 應該返回：第一筆訂單有分潤記錄 = 1，否則 = 0

-- 9. 模擬購買歷史檢查（排除第二筆訂單）
-- 先找到第二筆訂單的ID
SET @second_order_id = (
    SELECT p.ID FROM wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    WHERE pm.meta_key = '_billing_email'
    AND pm.meta_value = 'user02@gmail.com'
    AND p.post_type = 'shop_order'
    ORDER BY p.post_date DESC
    LIMIT 1
);

-- 檢查排除第二筆訂單後的購買記錄
SELECT
    @second_order_id as excluded_order_id,
    COUNT(DISTINCT cr.id) as record_count
FROM wp_commission_records cr
WHERE EXISTS (
    SELECT 1
    FROM wp_postmeta pm
    WHERE pm.post_id = cr.order_id
    AND pm.meta_key = '_billing_email'
    AND pm.meta_value = 'user02@gmail.com'
)
AND cr.order_id != @second_order_id;

-- 應該返回：1（第一筆訂單的分潤記錄）
-- 如果返回 0，表示第一筆訂單沒有創建分潤記錄

-- ================================================================
-- 結論
-- ================================================================
/*
請將以上所有查詢的結果提供給我，我會根據結果判斷問題所在。

特別關注：
1. 查詢 2：user02 是否有分潤記錄？
2. 查詢 3：第一筆訂單的商品是否在排除分類中？
3. 查詢 4：第一筆訂單的備註（是否有錯誤？）
4. 查詢 5：第二筆訂單的備註（SCENARIO 2-1 還是 2-2？）
5. 查詢 6：user_meta 的 _commission_referrer_email 是誰？
6. 查詢 7：downlines 表中 holder_email 是誰？
7. 查詢 9：排除第二筆訂單後，購買記錄數量是多少？
*/

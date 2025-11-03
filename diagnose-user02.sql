-- ================================================================
-- 診斷 user02 的情況
-- ================================================================

-- 1. 檢查 user02 的下線關係
-- ================================================================
SELECT
    id,
    holder_email,
    downline_email,
    created_at
FROM wp_commission_downlines
WHERE downline_email = 'user02@gmail.com'
ORDER BY created_at;

-- 預期：應該只有一條記錄
-- 如果是 qwe123@gmail.com → 問題：沒有轉移
-- 如果是 qqq123 的持有者 (neoc860927@gmail.com) → 正確：已轉移

-- ================================================================
-- 2. 檢查 user02 的 user_meta
-- ================================================================
SELECT
    u.user_email,
    um.meta_key,
    um.meta_value
FROM wp_users u
INNER JOIN wp_usermeta um ON u.ID = um.user_id
WHERE u.user_email = 'user02@gmail.com'
AND um.meta_key LIKE '%commission%'
ORDER BY um.meta_key;

-- 應該看到：
-- _commission_referral_code: 應該是 qqq123（如果轉移了）或 qwe123（如果沒轉移）
-- _commission_referrer_email: 對應的推薦人email

-- ================================================================
-- 3. 檢查 user02 的訂單
-- ================================================================
SELECT
    p.ID as order_id,
    p.post_status,
    p.post_date,
    pm_billing.meta_value as billing_email
FROM wp_posts p
INNER JOIN wp_postmeta pm_billing ON p.ID = pm_billing.post_id
WHERE pm_billing.meta_key = '_billing_email'
AND pm_billing.meta_value = 'user02@gmail.com'
AND p.post_type = 'shop_order'
ORDER BY p.post_date DESC;

-- 記下最新訂單的 ID，用於下面的查詢

-- ================================================================
-- 4. 檢查訂單的 commission meta（請將 [ORDER_ID] 替換為上面查到的訂單ID）
-- ================================================================
SELECT
    meta_key,
    meta_value
FROM wp_postmeta
WHERE post_id = [ORDER_ID]  -- ← 替換為實際訂單ID
AND meta_key LIKE '%commission%'
ORDER BY meta_key;

-- 關鍵欄位：
-- _commission_coupon_used: 使用的推薦碼
-- _commission_source: 推薦碼來源 (cookie / manual_coupon / existing_relationship)
-- _commission_temp_holder_email: 如果有值，表示是情境2-2
-- _commission_temp_coupon_code: 臨時推薦碼

-- ================================================================
-- 5. 檢查訂單備註（請將 [ORDER_ID] 替換為實際訂單ID）
-- ================================================================
SELECT
    comment_content,
    comment_date
FROM wp_comments
WHERE comment_post_ID = [ORDER_ID]  -- ← 替換為實際訂單ID
AND comment_type = 'order_note'
ORDER BY comment_date DESC;

-- 應該看到 SCENARIO 1 / 2-1 / 2-2 的備註

-- ================================================================
-- 6. 檢查分潤記錄
-- ================================================================
SELECT
    cr.id,
    cr.order_id,
    cr.coupon_code,
    cr.holder_email,
    cr.teacher_email,
    cr.director_email,
    cr.order_total,
    cr.holder_commission,
    cr.created_at
FROM wp_commission_records cr
INNER JOIN wp_posts p ON cr.order_id = p.ID
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE pm.meta_key = '_billing_email'
AND pm.meta_value = 'user02@gmail.com'
ORDER BY cr.created_at DESC;

-- 檢查 holder_email 是誰（qwe123@gmail.com 還是 neoc860927@gmail.com）

-- ================================================================
-- 診斷結果分析
-- ================================================================

-- 情況 A：轉移成功（預期的情境2-1）
-- - downlines: user02 → qqq123的持有者
-- - user_meta: _commission_referrer_email = qqq123的持有者email
-- - order_meta: _commission_source = cookie 或 manual_coupon
-- - order_note: "SCENARIO 2-1: User transferred..."
-- - commission_records: holder_email = qqq123的持有者email

-- 情況 B：沒有轉移但給了新推薦人分潤（不應該發生！）
-- - downlines: user02 → qwe123@gmail.com
-- - user_meta: _commission_referrer_email = qwe123@gmail.com
-- - commission_records: holder_email = qqq123的持有者email
-- ⚠️ 這種情況表示有 Bug

-- 情況 C：使用了既有推薦關係（如果沒有正確使用qqq123）
-- - downlines: user02 → qwe123@gmail.com
-- - user_meta: _commission_referrer_email = qwe123@gmail.com
-- - order_meta: _commission_source = existing_relationship
-- - commission_records: holder_email = qwe123@gmail.com

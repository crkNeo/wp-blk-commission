-- ================================================================
-- 清理重複下線關係的 SQL 腳本
-- ================================================================
-- 用途：修復 Bug 導致的一個用戶有多個上線的問題
-- 執行前請先備份資料庫！
-- ================================================================

-- 步驟 1: 檢查有多個上線的用戶（診斷用）
-- ================================================================
SELECT
    downline_email,
    COUNT(*) as upline_count,
    GROUP_CONCAT(holder_email ORDER BY created_at) as uplines,
    GROUP_CONCAT(created_at ORDER BY created_at) as dates
FROM wp_commission_downlines
GROUP BY downline_email
HAVING COUNT(*) > 1;

-- 步驟 2: 檢查用戶的 user_meta 中記錄的推薦人
-- ================================================================
-- 對於 user01@gmail.com 的例子：
SELECT
    u.user_email,
    um.meta_value as referrer_email
FROM wp_users u
INNER JOIN wp_usermeta um ON u.ID = um.user_id
WHERE um.meta_key = '_commission_referrer_email'
AND u.user_email = 'user01@gmail.com';

-- 步驟 3: 對比 downlines 表和 user_meta，找出不一致的記錄
-- ================================================================
SELECT
    d.id as downline_id,
    d.downline_email,
    d.holder_email as downline_table_holder,
    um.meta_value as usermeta_holder,
    d.created_at,
    CASE
        WHEN d.holder_email = um.meta_value THEN '✓ 一致（保留）'
        ELSE '✗ 不一致（應刪除）'
    END as status
FROM wp_commission_downlines d
INNER JOIN wp_users u ON d.downline_email = u.user_email
INNER JOIN wp_usermeta um ON u.ID = um.user_id
WHERE um.meta_key = '_commission_referrer_email'
AND d.downline_email IN (
    SELECT downline_email
    FROM wp_commission_downlines
    GROUP BY downline_email
    HAVING COUNT(*) > 1
)
ORDER BY d.downline_email, d.created_at;

-- ================================================================
-- 清理方案 A：根據 user_meta 清理（推薦）
-- ================================================================
-- 這個方案會保留與 user_meta 中 _commission_referrer_email 匹配的記錄
-- 刪除所有不匹配的記錄

-- 4A. 刪除與 user_meta 不一致的下線記錄
-- ================================================================
-- ⚠️ 請先在測試環境執行！
-- ⚠️ 執行前先用 SELECT 檢查會刪除哪些記錄：

-- 預覽要刪除的記錄：
SELECT
    d.id,
    d.downline_email,
    d.holder_email,
    d.created_at,
    '將被刪除' as action
FROM wp_commission_downlines d
INNER JOIN wp_users u ON d.downline_email = u.user_email
INNER JOIN wp_usermeta um ON u.ID = um.user_id
WHERE um.meta_key = '_commission_referrer_email'
AND d.holder_email != um.meta_value;

-- 確認無誤後，執行刪除：
/*
DELETE d FROM wp_commission_downlines d
INNER JOIN wp_users u ON d.downline_email = u.user_email
INNER JOIN wp_usermeta um ON u.ID = um.user_id
WHERE um.meta_key = '_commission_referrer_email'
AND d.holder_email != um.meta_value;
*/

-- ================================================================
-- 清理方案 B：根據時間順序清理（備用方案）
-- ================================================================
-- 這個方案會保留最早創建的推薦關係，刪除後續的

-- 4B. 刪除重複的下線記錄（保留最早的）
-- ================================================================
-- ⚠️ 只在方案A不適用時使用

-- 預覽要刪除的記錄：
SELECT
    d.id,
    d.downline_email,
    d.holder_email,
    d.created_at,
    '將被刪除（保留最早的）' as action
FROM wp_commission_downlines d
INNER JOIN (
    SELECT
        downline_email,
        MIN(id) as keep_id
    FROM wp_commission_downlines
    GROUP BY downline_email
) earliest ON d.downline_email = earliest.downline_email
WHERE d.id != earliest.keep_id;

-- 確認無誤後，執行刪除：
/*
DELETE d FROM wp_commission_downlines d
INNER JOIN (
    SELECT
        downline_email,
        MIN(id) as keep_id
    FROM wp_commission_downlines
    GROUP BY downline_email
) earliest ON d.downline_email = earliest.downline_email
WHERE d.id != earliest.keep_id;
*/

-- ================================================================
-- 清理方案 C：手動清理特定用戶（最安全）
-- ================================================================
-- 針對 user01@gmail.com 的手動清理範例

-- 5C. 檢查 user01 的情況
-- ================================================================
SELECT * FROM wp_commission_downlines
WHERE downline_email = 'user01@gmail.com'
ORDER BY created_at;

-- 假設結果是：
-- id=1, holder=qwe123@gmail.com, created_at=2025-11-01 10:00:00
-- id=2, holder=neoc860927@gmail.com, created_at=2025-11-03 11:00:00

-- 6C. 檢查 user01 的 user_meta
-- ================================================================
SELECT um.meta_value as referrer_email
FROM wp_users u
INNER JOIN wp_usermeta um ON u.ID = um.user_id
WHERE u.user_email = 'user01@gmail.com'
AND um.meta_key = '_commission_referrer_email';

-- 假設結果是：qwe123@gmail.com（第一個推薦人，有購買記錄）

-- 7C. 刪除錯誤的記錄（neoc860927@gmail.com）
-- ================================================================
-- 確認後執行：
/*
DELETE FROM wp_commission_downlines
WHERE downline_email = 'user01@gmail.com'
AND holder_email = 'neoc860927@gmail.com';
*/

-- ================================================================
-- 驗證清理結果
-- ================================================================

-- 8. 確認沒有重複的下線記錄
-- ================================================================
SELECT
    downline_email,
    COUNT(*) as upline_count
FROM wp_commission_downlines
GROUP BY downline_email
HAVING COUNT(*) > 1;
-- 應該返回空結果

-- 9. 確認所有下線記錄與 user_meta 一致
-- ================================================================
SELECT
    d.downline_email,
    d.holder_email as downline_table,
    um.meta_value as usermeta,
    CASE
        WHEN d.holder_email = um.meta_value THEN '✓ 一致'
        ELSE '✗ 不一致'
    END as status
FROM wp_commission_downlines d
INNER JOIN wp_users u ON d.downline_email = u.user_email
INNER JOIN wp_usermeta um ON u.ID = um.user_id
WHERE um.meta_key = '_commission_referrer_email';

-- ================================================================
-- 針對 user01@gmail.com 的完整清理腳本
-- ================================================================
-- 根據你的描述，user01 應該：
-- 1. 保留 qwe123@gmail.com 的下線關係（第一個推薦人，有購買記錄）
-- 2. 刪除 neoc860927@gmail.com 的下線關係（第二次下單不應該改變下線）

-- 執行清理（請根據實際情況調整）：
/*
-- 先檢查
SELECT * FROM wp_commission_downlines
WHERE downline_email = 'user01@gmail.com';

-- 確認 user_meta
SELECT u.ID, um.meta_key, um.meta_value
FROM wp_users u
INNER JOIN wp_usermeta um ON u.ID = um.user_id
WHERE u.user_email = 'user01@gmail.com'
AND um.meta_key LIKE '%commission%';

-- 如果確認 _commission_referrer_email 是 qwe123@gmail.com，
-- 則刪除 neoc860927@gmail.com 的記錄：
DELETE FROM wp_commission_downlines
WHERE downline_email = 'user01@gmail.com'
AND holder_email != (
    SELECT um.meta_value
    FROM wp_users u
    INNER JOIN wp_usermeta um ON u.ID = um.user_id
    WHERE u.user_email = 'user01@gmail.com'
    AND um.meta_key = '_commission_referrer_email'
    LIMIT 1
);
*/

-- ================================================================
-- 預防性措施：添加唯一索引（可選）
-- ================================================================
-- 為了防止未來再次出現重複，可以添加唯一索引
-- ⚠️ 請在清理完重複記錄後再執行

-- 檢查是否已有唯一索引
SHOW INDEXES FROM wp_commission_downlines
WHERE Column_name = 'downline_email';

-- 如果沒有，添加唯一索引：
/*
CREATE UNIQUE INDEX idx_unique_downline
ON wp_commission_downlines(downline_email);
*/

-- 注意：這會確保每個 downline_email 只能有一條記錄
-- 如果未來嘗試插入重複記錄，會觸發錯誤

-- ================================================================
-- 完成！
-- ================================================================
-- 執行完成後，建議：
-- 1. 檢查報表功能是否正常
-- 2. 測試新訂單的分潤是否正確
-- 3. 驗證情境2-2的臨時分潤功能

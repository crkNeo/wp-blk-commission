# 動態推薦關係系統 - 實作說明

## 📋 實作概述

本次更新實作了動態推薦關係系統，包含商品分類排除、推薦碼優先順序控制、以及智慧型推薦關係轉換機制。

---

## 🎯 核心功能

### 1. 商品分類排除

**功能：** 特定商品分類不參與分潤計算

**排除的分類：**
- `member-events` - 會員活動
- `featured-events` - 精選活動

**實作函數：**
```php
function commission_order_has_excluded_categories($order_id)
```

**工作原理：**
- 在訂單完成時第一時間檢查
- 遍歷訂單中的所有商品
- 檢查商品是否屬於排除的分類
- 如果包含排除分類，立即停止分潤處理

**訂單備註：**
```
Order contains excluded product categories (member-events or featured-events) - no commission will be processed.
```

---

### 2. 推薦碼優先順序

**優先順序（從高到低）：**

#### 優先級 1：Cookie 推薦連結 ⭐ 最高優先級
- 來源：訪客點擊推薦鏈接（如 `https://yoursite.com/?ref=TEST1234`）
- Cookie名稱：`commission_ref_code`
- 有效期：30天
- 標記為：`commission_source = 'cookie'`

#### 優先級 2：手動輸入的折扣碼
- 來源：結帳時手動輸入或套用的 WooCommerce 折扣碼
- 檢查方式：`$order->get_coupon_codes()`
- 標記為：`commission_source = 'manual_coupon'`

#### 優先級 3：既有推薦關係
- 來源：用戶先前已綁定的推薦關係
- 儲存位置：`_commission_referral_code` user meta
- 標記為：`commission_source = 'existing_relationship'`

**實作邏輯：**
```php
// 1. 先檢查 Cookie
$cookie_referral_code = $_COOKIE[COMMISSION_REFERRAL_COOKIE];

// 2. 沒有 Cookie，檢查手動折扣碼
if (!$found) {
    $coupons_used = $order->get_coupon_codes();
}

// 3. 都沒有，使用既有推薦關係
if (!$found && $user_id) {
    $user_referral_code = get_user_meta($user_id, '_commission_referral_code', true);
}
```

---

### 3. 動態推薦關係轉換

根據用戶的購買歷史和推薦狀態，系統會自動決定如何處理推薦關係。

#### 情境 1：無推薦人 → 正常綁定

**條件：**
- 用戶沒有既有的推薦關係（`_commission_referrer_email` 為空）
- 使用了有效的推薦碼（Cookie 或手動折扣碼）

**處理方式：**
1. 檢查防止自我推薦（客戶email ≠ 推薦人email）
2. 在 `commission_downlines` 表中建立推薦關係
3. 更新用戶 meta：
   - `_commission_referral_code`：推薦碼
   - `_commission_referrer_email`：推薦人email
   - `_commission_referral_date`：綁定日期

**訂單備註：**
```
SCENARIO 1: New referral relationship created. Holder: holder@example.com, Code: TEST1234
```

**適用案例：**
- 新用戶首次購買
- 從未被推薦過的舊用戶

---

#### 情境 2-1：有推薦人但無購買記錄 → 轉移到新推薦人

**條件：**
- 用戶已有推薦關係（`_commission_referrer_email` 不為空）
- 使用了不同推薦人的推薦碼
- 用戶沒有購買歷史（`commission_user_has_purchase_history()` = false）

**判斷購買歷史的標準：**
- 檢查 `commission_records` 表
- 只要有分潤記錄的訂單 = 有購買歷史
- 沒有任何分潤記錄 = 無購買歷史

**處理方式：**
1. 從舊推薦人的下線列表中移除（`commission_downlines`）
2. 加入新推薦人的下線列表
3. 更新用戶 meta 為新推薦人資訊
4. 本次訂單的分潤給新推薦人

**實作函數：**
```php
function commission_transfer_referral($user_id, $new_referral_code)
```

**訂單備註：**
```
SCENARIO 2-1: User transferred from old@example.com to new@example.com (no purchase history). Commission goes to new holder.
```

**適用案例：**
- 用戶點擊了推薦鏈接註冊，但還沒購買
- 之後點擊另一個推薦人的鏈接完成首次購買
- 系統會轉移到新推薦人（因為舊推薦人還沒獲得任何利益）

---

#### 情境 2-2：有推薦人且有購買記錄 → 保持原推薦人，本訂單分潤給新推薦人

**條件：**
- 用戶已有推薦關係（`_commission_referrer_email` 不為空）
- 使用了不同推薦人的推薦碼
- 用戶有購買歷史（`commission_user_has_purchase_history()` = true）

**處理方式：**
1. **不改變**用戶的推薦關係（user meta 保持不變）
2. **不修改** `commission_downlines` 表（仍屬於原推薦人的下線）
3. **僅針對本訂單**，分潤給新推薦人
4. 使用訂單 meta 儲存臨時推薦人：
   - `_commission_temp_holder_email`：臨時推薦人email
   - `_commission_temp_coupon_code`：臨時推薦碼

**分潤計算邏輯：**
```php
// 在計算分潤時，優先檢查是否有臨時推薦人
$temp_holder_email = get_post_meta($order_id, '_commission_temp_holder_email', true);
$temp_coupon_code = get_post_meta($order_id, '_commission_temp_coupon_code', true);

if (!empty($temp_holder_email)) {
    // 使用臨時推薦人的折扣碼資料
    $coupon_data = get_commission_coupon_data($temp_coupon_code);
}
```

**訂單備註：**
```
SCENARIO 2-2: User stays with original holder original@example.com (has purchase history), but this order's commission goes to new@example.com using code NEW123.
```

**適用案例：**
- 忠實客戶（已有多次購買記錄）
- 點擊朋友的推薦鏈接購買特定商品
- 系統不會剝奪原推薦人的長期利益
- 但這筆訂單的分潤會給提供鏈接的朋友

---

## 🔧 新增的函數

### 1. commission_order_has_excluded_categories()

**位置：** `main.php` 第 74-99 行

**功能：** 檢查訂單是否包含排除的商品分類

**參數：**
- `$order_id` (int) - 訂單ID

**返回值：**
- `true` - 包含排除的分類
- `false` - 不包含排除的分類

**使用方式：**
```php
if (commission_order_has_excluded_categories($order_id)) {
    // 停止處理分潤
    return;
}
```

---

### 2. commission_user_has_purchase_history()

**位置：** `main.php` 第 104-124 行

**功能：** 檢查用戶是否有購買歷史（是否有分潤記錄）

**參數：**
- `$user_id` (int) - 用戶ID

**返回值：**
- `true` - 有購買歷史（有分潤記錄）
- `false` - 無購買歷史（無分潤記錄）

**SQL查詢：**
```sql
SELECT COUNT(*) FROM commission_records cr
INNER JOIN wp_posts p ON cr.order_id = p.ID
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE pm.meta_key = '_billing_email'
AND pm.meta_value = %s
```

**使用方式：**
```php
if (commission_user_has_purchase_history($user_id)) {
    // 情境 2-2：保持原推薦人
} else {
    // 情境 2-1：轉移到新推薦人
}
```

---

### 3. commission_transfer_referral()

**位置：** `main.php` 第 129-180 行

**功能：** 將用戶從舊推薦人轉移到新推薦人

**參數：**
- `$user_id` (int) - 用戶ID
- `$new_referral_code` (string) - 新的推薦碼

**返回值：**
- `true` - 轉移成功
- `false` - 轉移失敗

**執行步驟：**
1. 驗證新推薦碼有效性
2. 從 `commission_downlines` 刪除舊的推薦關係
3. 在 `commission_downlines` 新增新的推薦關係
4. 更新用戶 meta

**錯誤日誌：**
```
Commission: Removed user 123 (customer@example.com) from old holder old@example.com
Commission: Transferred user 123 (customer@example.com) from old@example.com to new@example.com
```

---

## 📊 訂單 Meta 說明

### 既有的 Meta Keys

| Meta Key | 說明 | 範例值 |
|----------|------|--------|
| `_commission_coupon_used` | 使用的推薦碼 | `TEST1234` |
| `_commission_coupon_data` | 完整的折扣碼資料（序列化） | `array(...)` |

### 新增的 Meta Keys

| Meta Key | 說明 | 範例值 | 使用情境 |
|----------|------|--------|----------|
| `_commission_source` | 推薦碼來源 | `cookie`, `manual_coupon`, `existing_relationship` | 所有情境 |
| `_commission_temp_holder_email` | 臨時推薦人email | `temp@example.com` | 情境 2-2 |
| `_commission_temp_coupon_code` | 臨時推薦碼 | `TEMP123` | 情境 2-2 |

---

## 🧪 測試指南

### 測試準備

**測試環境：**
1. 啟用 WordPress Debug 模式（查看 error_log）
2. 準備至少 3 個測試用戶帳號
3. 創建至少 2 個活躍的推薦碼
4. 準備不同分類的測試商品

**測試用推薦碼：**
- `HOLDER_A` - 推薦人A的折扣碼
- `HOLDER_B` - 推薦人B的折扣碼

**測試用商品：**
- 一般商品（如：`product`）
- 排除分類商品（`member-events`, `featured-events`）

---

### 測試案例 1：商品分類排除

**目標：** 驗證排除的商品分類不參與分潤

**步驟：**
1. 創建一個商品，分類設為 `member-events`
2. 使用推薦鏈接 `?ref=HOLDER_A` 訪問網站
3. 購買該商品並完成訂單
4. 檢查訂單狀態改為「已完成」

**預期結果：**
- ✅ 訂單備註顯示：`Order contains excluded product categories...`
- ✅ `commission_records` 表中沒有新記錄
- ✅ error_log 顯示：`Commission: Order X excluded due to product category`

**測試變化：**
- 測試 `featured-events` 分類（應該也被排除）
- 測試混合訂單（包含一般商品 + 排除分類商品）→ 整張訂單應該被排除

---

### 測試案例 2：推薦碼優先順序

**目標：** 驗證 Cookie > 手動折扣碼 > 既有關係

#### 測試 2A：Cookie 優先於手動折扣碼

**步驟：**
1. 清除瀏覽器 Cookie（無痕模式）
2. 訪問推薦鏈接 `?ref=HOLDER_A`
3. 註冊新帳號
4. 加商品到購物車
5. 在結帳頁面**手動輸入**折扣碼 `HOLDER_B`
6. 完成結帳

**預期結果：**
- ✅ 分潤給 `HOLDER_A`（Cookie 優先）
- ✅ 訂單 meta `_commission_source` = `cookie`
- ✅ error_log 顯示：`Using cookie referral code: HOLDER_A`

#### 測試 2B：手動折扣碼優先於既有關係

**步驟：**
1. 使用已有推薦關係的測試帳號登入（如：已綁定 `HOLDER_A`）
2. 加商品到購物車
3. **不使用推薦鏈接**，直接在結帳頁面手動輸入折扣碼 `HOLDER_B`
4. 完成結帳

**預期結果：**
- ✅ 檢查用戶是否有購買歷史
  - 如果無購買歷史：轉移到 `HOLDER_B`（情境 2-1）
  - 如果有購買歷史：保持 `HOLDER_A`，但本訂單分潤給 `HOLDER_B`（情境 2-2）

---

### 測試案例 3：情境 1 - 新用戶正常綁定

**目標：** 驗證新用戶首次購買的推薦關係建立

**步驟：**
1. 清除瀏覽器 Cookie（無痕模式）
2. 訪問推薦鏈接 `?ref=HOLDER_A`
3. 註冊新帳號（如：`newuser@test.com`）
4. 購買商品並完成訂單

**預期結果：**
- ✅ `commission_downlines` 表新增記錄：
  - `holder_email` = `HOLDER_A` 的email
  - `downline_email` = `newuser@test.com`
- ✅ 用戶 meta：
  - `_commission_referral_code` = `HOLDER_A`
  - `_commission_referrer_email` = HOLDER_A的email
- ✅ 訂單備註：`SCENARIO 1: New referral relationship created...`
- ✅ `commission_records` 表新增分潤記錄

**SQL驗證：**
```sql
-- 檢查推薦關係
SELECT * FROM wp_commission_downlines
WHERE downline_email = 'newuser@test.com';

-- 檢查分潤記錄
SELECT * FROM wp_commission_records
WHERE order_id = [訂單ID];

-- 檢查用戶meta
SELECT * FROM wp_usermeta
WHERE user_id = [用戶ID]
AND meta_key LIKE '%commission%';
```

---

### 測試案例 4：情境 2-1 - 無購買記錄轉移

**目標：** 驗證無購買記錄的用戶可以轉移推薦人

**前置條件：**
- 用戶已註冊並綁定 `HOLDER_A`
- 用戶**從未完成過訂單**（無分潤記錄）

**步驟：**
1. 使用該測試帳號登入
2. 訪問新的推薦鏈接 `?ref=HOLDER_B`
3. 購買商品並完成訂單

**預期結果：**
- ✅ `commission_downlines` 表更新：
  - 舊記錄被刪除（`HOLDER_A` → user）
  - 新記錄被建立（`HOLDER_B` → user）
- ✅ 用戶 meta 更新為 `HOLDER_B` 的資訊
- ✅ 訂單備註：`SCENARIO 2-1: User transferred from [HOLDER_A email] to [HOLDER_B email]...`
- ✅ 分潤給 `HOLDER_B`

**SQL驗證：**
```sql
-- 檢查推薦關係（應該只有 HOLDER_B）
SELECT * FROM wp_commission_downlines
WHERE downline_email = 'testuser@test.com';

-- 應該只有一筆記錄，holder_email = HOLDER_B的email
```

**錯誤日誌驗證：**
```
Commission: Removed user X (testuser@test.com) from old holder [HOLDER_A email]
Commission: Transferred user X (testuser@test.com) from [HOLDER_A email] to [HOLDER_B email]
Commission: SCENARIO 2-1 - User X transferred...
```

---

### 測試案例 5：情境 2-2 - 有購買記錄保持原推薦人

**目標：** 驗證有購買記錄的用戶不會被轉移，但本訂單分潤給新推薦人

**前置條件：**
- 用戶已註冊並綁定 `HOLDER_A`
- 用戶**已完成至少一筆訂單**（有分潤記錄）

**步驟：**
1. 確認該用戶已有分潤記錄
2. 使用該測試帳號登入
3. 訪問新的推薦鏈接 `?ref=HOLDER_B`
4. 購買商品並完成訂單

**預期結果：**
- ✅ `commission_downlines` **不變**（仍屬於 `HOLDER_A` 的下線）
- ✅ 用戶 meta **不變**（仍綁定 `HOLDER_A`）
- ✅ 訂單 meta 新增：
  - `_commission_temp_holder_email` = HOLDER_B的email
  - `_commission_temp_coupon_code` = `HOLDER_B`
- ✅ 訂單備註：`SCENARIO 2-2: User stays with original holder...`
- ✅ **本訂單的分潤給 `HOLDER_B`**
- ✅ `commission_records` 中該訂單的 `holder_email` = HOLDER_B的email

**SQL驗證：**
```sql
-- 1. 檢查推薦關係（應該仍是 HOLDER_A）
SELECT * FROM wp_commission_downlines
WHERE downline_email = 'loyaluser@test.com';
-- 應該只有一筆，holder_email = HOLDER_A的email

-- 2. 檢查用戶meta（應該仍是 HOLDER_A）
SELECT meta_key, meta_value FROM wp_usermeta
WHERE user_id = [用戶ID]
AND meta_key IN ('_commission_referral_code', '_commission_referrer_email');

-- 3. 檢查訂單meta（應該有臨時推薦人）
SELECT meta_key, meta_value FROM wp_postmeta
WHERE post_id = [訂單ID]
AND meta_key IN ('_commission_temp_holder_email', '_commission_temp_coupon_code');

-- 4. 檢查分潤記錄（本訂單應該分給 HOLDER_B）
SELECT holder_email, coupon_code FROM wp_commission_records
WHERE order_id = [訂單ID];
-- holder_email 應該是 HOLDER_B 的email
```

**重要驗證點：**
- 用戶的下一筆訂單（如果沒有新的推薦鏈接）應該分給 `HOLDER_A`
- 只有這一筆特定訂單分給 `HOLDER_B`

---

### 測試案例 6：防止自我推薦

**目標：** 驗證用戶不能使用自己的推薦碼

**步驟：**
1. 使用 `HOLDER_A` 帳號登入
2. 訪問自己的推薦鏈接（使用自己的推薦碼）
3. 購買商品並完成訂單

**預期結果：**
- ✅ 不建立推薦關係（情境1中有檢查）
- ✅ 仍會處理分潤（因為使用了有效的折扣碼）
- ✅ 但不會在 `commission_downlines` 新增自我推薦記錄

---

### 測試案例 7：混合情境測試

**目標：** 測試複雜的使用場景

#### 情境 7A：Cookie過期後的行為

**步驟：**
1. 訪問推薦鏈接 `?ref=HOLDER_A`
2. 等待30天（或手動刪除Cookie）
3. 不使用推薦鏈接，直接購買
4. 檢查使用哪個推薦碼

**預期結果：**
- 如果用戶已註冊：使用既有推薦關係（優先級3）
- 如果用戶未註冊：沒有推薦關係

#### 情境 7B：多次轉移

**步驟：**
1. 新用戶通過 `HOLDER_A` 註冊
2. 不購買，訪問 `HOLDER_B` 的鏈接
3. 購買（應轉移到 `HOLDER_B`）
4. 不購買，訪問 `HOLDER_C` 的鏈接
5. 購買（應轉移到 `HOLDER_C`）

**預期結果：**
- 第3步：轉移到 `HOLDER_B`（情境2-1）
- 第5步：因為已有購買記錄，應該觸發情境2-2
- 用戶永久屬於 `HOLDER_B`
- 第5步的訂單分潤給 `HOLDER_C`

---

## 📝 錯誤日誌範例

開啟 WordPress 調試模式後，可以在 `wp-content/debug.log` 查看詳細日誌。

### 情境 1 日誌

```
Commission: Using cookie referral code: HOLDER_A
Commission: SCENARIO 1 - New user bound to holder holdera@example.com via code HOLDER_A
Commission processed for Order ID: 123, Coupon: HOLDER_A, Total: 1000
```

### 情境 2-1 日誌

```
Commission: Using cookie referral code: HOLDER_B
Commission: Removed user 456 (user@example.com) from old holder holdera@example.com
Commission: Transferred user 456 (user@example.com) from holdera@example.com to holderb@example.com
Commission: SCENARIO 2-1 - User 456 transferred from holdera@example.com to holderb@example.com
Commission processed for Order ID: 124, Coupon: HOLDER_B, Total: 1000
```

### 情境 2-2 日誌

```
Commission: Using cookie referral code: HOLDER_B
Commission: SCENARIO 2-2 - User 456 stays with holdera@example.com, but order 125 commission goes to holderb@example.com
Commission: Using temporary holder holderb@example.com for order 125 (Scenario 2-2)
Commission processed for Order ID: 125, Coupon: HOLDER_B, Total: 1000
```

### 商品分類排除日誌

```
Commission: Order 126 excluded due to product category
```

---

## 🔍 常見問題排除

### 問題 1：Cookie 沒有正確讀取

**症狀：** 明明訪問了推薦鏈接，但系統沒有使用 Cookie 中的推薦碼

**檢查步驟：**
1. 確認 Cookie 是否成功設定（瀏覽器開發者工具 → Application → Cookies）
2. 檢查 Cookie 名稱是否為 `commission_ref_code`
3. 確認 Cookie 有效期（應為30天）

**可能原因：**
- Cookie 被瀏覽器阻擋（隱私模式）
- 折扣碼無效或已停用
- `referral-links.php` 未正確載入

**解決方案：**
```php
// 在 main.php 的 process_commission_on_order_complete 中加入調試
error_log("Cookie check: " . print_r($_COOKIE, true));
```

---

### 問題 2：情境判斷錯誤

**症狀：** 有購買記錄的用戶被轉移了，或無購買記錄的用戶沒有轉移

**檢查步驟：**
1. 查詢用戶的分潤記錄：
```sql
SELECT cr.* FROM wp_commission_records cr
INNER JOIN wp_posts p ON cr.order_id = p.ID
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE pm.meta_key = '_billing_email'
AND pm.meta_value = 'user@example.com';
```

2. 檢查 error_log 中的購買歷史判斷結果

**可能原因：**
- 訂單未完成（status ≠ 'completed'）
- 分潤記錄被刪除
- 郵箱地址不匹配

---

### 問題 3：情境 2-2 分潤給錯誤的推薦人

**症狀：** 應該分給新推薦人，但分給了原推薦人

**檢查步驟：**
1. 檢查訂單 meta：
```sql
SELECT meta_key, meta_value FROM wp_postmeta
WHERE post_id = [訂單ID]
AND meta_key LIKE '%commission%';
```

2. 確認 `_commission_temp_holder_email` 和 `_commission_temp_coupon_code` 是否正確設定

**可能原因：**
- 訂單 meta 沒有正確保存
- 分潤計算時沒有檢查臨時推薦人

---

### 問題 4：排除分類不生效

**症狀：** `member-events` 或 `featured-events` 商品仍然參與分潤

**檢查步驟：**
1. 確認商品分類的 slug 是否完全匹配（大小寫、連字符）
2. 檢查商品是否正確分配到該分類
3. 查看 error_log 是否有排除訊息

**SQL驗證：**
```sql
-- 檢查商品的分類slug
SELECT t.slug, t.name
FROM wp_terms t
INNER JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
INNER JOIN wp_term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
WHERE tr.object_id = [商品ID]
AND tt.taxonomy = 'product_cat';
```

---

## 🎓 最佳實踐建議

### 1. 監控和報表

**定期檢查：**
- 每週檢查推薦關係轉移日誌
- 監控排除分類的訂單數量
- 分析各情境的觸發頻率

**SQL報表查詢：**
```sql
-- 統計各情境的訂單數量
SELECT
    pm.meta_value as source,
    COUNT(*) as order_count
FROM wp_postmeta pm
WHERE pm.meta_key = '_commission_source'
GROUP BY pm.meta_value;

-- 統計臨時推薦人的訂單數量（情境2-2）
SELECT COUNT(*)
FROM wp_postmeta
WHERE meta_key = '_commission_temp_holder_email';

-- 統計被排除的訂單
SELECT COUNT(*)
FROM wp_comments
WHERE comment_content LIKE '%excluded product categories%';
```

---

### 2. 效能優化

**建議：**
- 定期清理過期的 Cookie
- 為 `commission_records` 表的 email 欄位建立索引
- 避免在高峰時段執行大量推薦關係轉移

**索引優化：**
```sql
-- 加速購買歷史查詢
CREATE INDEX idx_holder_email ON wp_commission_records(holder_email);
CREATE INDEX idx_billing_email ON wp_postmeta(meta_value(191))
WHERE meta_key = '_billing_email';
```

---

### 3. 用戶溝通

**建議在前台顯示：**
- 用戶當前的推薦關係狀態
- 推薦人姓名或代碼
- 如果使用不同推薦碼，告知會發生什麼

**範例訊息：**
```
您目前是 張老師 的推薦會員。
本次訂單使用了 李老師 的推薦碼，這筆訂單的優惠將由李老師提供，
但您的會員關係仍維持在張老師名下。
```

---

## 🔄 未來擴展建議

### 1. 後台設定選項

建議在 WordPress 後台新增設定頁面，讓管理員可以：
- 調整推薦碼優先順序
- 修改排除的商品分類列表
- 選擇情境2-2的處理方式（轉移 vs 保持）
- 設定購買歷史的判斷標準（訂單數 vs 分潤記錄數）

### 2. 推薦關係歷史記錄

建議新增表格記錄推薦關係的變更歷史：
```sql
CREATE TABLE wp_commission_referral_history (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    old_holder_email varchar(255),
    new_holder_email varchar(255),
    reason varchar(50),
    order_id bigint(20),
    created_at datetime,
    PRIMARY KEY (id)
);
```

### 3. 推薦人通知

當用戶被轉移或臨時分潤時，發送通知給相關推薦人：
- 舊推薦人：「XXX 已轉移到其他推薦人」
- 新推薦人：「XXX 已加入您的推薦網絡」

---

## 📞 技術支援

### 相關檔案

| 檔案 | 說明 | 修改行數 |
|------|------|----------|
| `main.php` | 核心邏輯 | 74-367 |
| `referral-links.php` | Cookie 管理 | 未修改 |
| `table.php` | 資料庫結構 | 未修改 |

### Git Commit 資訊

- **Commit Hash:** `1b9f56f`
- **Branch:** `claude/review-profit-sharing-project-011CUVyewoevReGeEyPugyVw`
- **Date:** 2025-11-03

---

## 📄 版本資訊

**版本：** 2.0.0
**更新日期：** 2025-11-03
**作者：** Commission System Team
**相容性：** WordPress 5.8+, WooCommerce 6.0+

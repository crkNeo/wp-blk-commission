# 🔗 推薦鏈接系統使用指南

## 📋 功能概述

推薦鏈接系統允許推薦人通過專屬URL分享折扣碼，訪客點擊鏈接後：
1. ✅ 折扣碼自動存入Cookie（30天有效）
2. ✅ 註冊時自動建立推薦關係
3. ✅ 結帳時自動套用折扣
4. ✅ 即使稍後才購買也能追蹤推薦關係

---

## 🎯 支援的URL格式

系統支援多種推薦鏈接格式：

### 1. 路徑格式（最簡潔）
```
https://beamclub.com/TEST1234
```
- 訪客訪問此鏈接時，`TEST1234` 會自動存入Cookie
- 適合：社交媒體分享、短信分享

### 2. 查詢參數格式（通用）
```
https://beamclub.com/?ref=TEST1234
https://beamclub.com/?referral=TEST1234
```
- 可以搭配任何頁面使用
- 適合：Email行銷、特定商品推薦

### 3. 指定頁面格式
```
https://beamclub.com/shop/?ref=TEST1234
https://beamclub.com/product/example/?ref=TEST1234
```
- 將訪客導向特定頁面並套用折扣
- 適合：推廣特定商品或分類

---

## 🚀 快速開始

### 步驟1：確認折扣碼已創建

1. 進入WordPress後台
2. 前往「分潤系統」→「折扣碼管理」
3. 確認您的折扣碼狀態為「active」
4. 記下折扣碼（例如：TEST1234）

### 步驟2：生成推薦鏈接

**方法A：手動生成（最簡單）**
```
格式：https://您的網站.com/折扣碼
範例：https://beamclub.com/TEST1234
```

**方法B：使用PHP函數**
```php
<?php
// 生成到首頁的推薦鏈接
$link = commission_generate_referral_link('TEST1234');

// 生成到特定頁面的推薦鏈接
$link = commission_generate_referral_link('TEST1234', 123); // 123是頁面ID
?>
```

### 步驟3：分享推薦鏈接

將生成的鏈接分享給您的客戶：
- 📱 社交媒體（Facebook、Instagram、Line等）
- 📧 Email行銷
- 💬 即時通訊（WhatsApp、Telegram等）
- 📄 印刷品、傳單

---

## 🎨 創建專屬推薦頁面

### 方法1：使用短代碼（推薦）

1. 創建新頁面（例如：「推薦專區」）
2. 在頁面內容中加入短代碼：
```
[commission_referral_info]
```
3. 發布頁面
4. 推薦鏈接格式：
```
https://beamclub.com/推薦專區/?ref=TEST1234
```

**短代碼會自動顯示：**
- 推薦人姓名
- 專屬折扣金額
- 折扣碼
- 精美的歡迎訊息
- 行動呼籲按鈕（開始購物、註冊）

### 方法2：自訂頁面設計

創建自訂頁面範本並使用以下函數：

```php
<?php
// 獲取當前的推薦碼
$ref_code = commission_get_current_referral_code();

if ($ref_code) {
    // 獲取折扣碼資料
    $coupon_data = get_commission_coupon_data($ref_code);

    echo "歡迎！您的專屬折扣：" . $coupon_data['discount_percentage'] . "%";
}
?>
```

---

## 🔄 完整使用流程

### 流程圖

```
訪客點擊推薦鏈接
    ↓
折扣碼存入Cookie（30天）
    ↓
訪客瀏覽網站（Cookie保持有效）
    ↓
訪客註冊帳號
    ↓
自動建立推薦關係（存入downline表）
    ↓
訪客結帳購物
    ↓
自動套用折扣碼
    ↓
訂單完成後處理分潤
```

### 詳細說明

**1. 訪客點擊推薦鏈接**
- URL: `https://beamclub.com/TEST1234`
- 系統檢測到折扣碼 `TEST1234`
- 驗證折扣碼有效性

**2. 存入Cookie**
- Cookie名稱：`commission_ref_code`
- 值：`TEST1234`
- 有效期：30天
- 作用域：整個網站

**3. 訪客瀏覽或離開**
- Cookie持續有效30天
- 即使訪客離開網站，30天內回訪仍有效

**4. 訪客註冊**
- 系統自動檢測Cookie
- 建立推薦關係：
  - holder_email: 推薦人email
  - downline_email: 新用戶email
- 儲存到用戶meta:
  - `_commission_referral_code`: TEST1234
  - `_commission_referrer_email`: holder@example.com
  - `_commission_referral_date`: 2024-12-19

**5. 訪客結帳**
- 進入結帳頁面時自動檢測Cookie
- 如果尚未套用折扣碼，自動套用
- 顯示成功訊息：「推薦折扣碼 "TEST1234" 已自動套用！」

**6. 訂單完成**
- 計算三層分潤
- 記錄到 commission_records
- 在訂單備註中顯示推薦關係

---

## 💡 使用場景範例

### 場景1：社群媒體推廣

**推薦人：**張老師
**折扣碼：**TEACHER001
**推薦鏈接：**https://beamclub.com/TEACHER001

**分享文案：**
```
🎉 專屬優惠來了！
透過我的推薦鏈接註冊，立即享有10% OFF！

🔗 點擊連結：https://beamclub.com/TEACHER001

✨ 優惠自動套用，註冊即享折扣！
⏰ 優惠30天內有效
```

### 場景2：Email行銷

**推薦人：**李主管
**折扣碼：**DIRECTOR888
**推薦鏈接：**https://beamclub.com/?ref=DIRECTOR888

**Email範本：**
```html
<h2>親愛的朋友，</h2>
<p>我想與您分享一個特別優惠！</p>
<p>使用我的專屬推薦鏈接，您將獲得：</p>
<ul>
  <li>✅ 全站商品 15% 折扣</li>
  <li>✅ 自動套用，無需輸入折扣碼</li>
  <li>✅ 30天內隨時購物都享優惠</li>
</ul>
<a href="https://beamclub.com/?ref=DIRECTOR888">
  立即開始購物
</a>
```

### 場景3：特定商品推廣

**推薦人：**王講師
**折扣碼：**COURSE2024
**商品頁面ID：**456
**推薦鏈接：**https://beamclub.com/product/premium-course/?ref=COURSE2024

**WhatsApp訊息：**
```
📚 推薦您這個超棒的課程！

使用我的專屬鏈接購買：
👉 https://beamclub.com/product/premium-course/?ref=COURSE2024

🎁 享有20% OFF優惠
⚡ 點擊連結自動套用折扣
```

---

## 🛠️ 進階功能

### 1. 生成短網址（可選）

使用第三方短網址服務（如 Bitly）來美化推薦鏈接：

```
原始：https://beamclub.com/TEACHER001
短網址：https://bit.ly/teacher-special
```

### 2. 追蹤推薦統計

在後台查看推薦成效：

```php
<?php
// 獲取推薦人的統計資料
$stats = commission_get_user_referral_stats('holder@example.com');

echo "總推薦人數：" . $stats['total_referrals'];
echo "本月新增：" . $stats['month_referrals'];
?>
```

### 3. 自訂Cookie有效期

如果需要修改Cookie有效期（預設30天），編輯 `referral-links.php`：

```php
// 修改這行（單位：秒）
define('COMMISSION_REFERRAL_EXPIRY', 30 * DAY_IN_SECONDS); // 30天

// 範例：改為60天
define('COMMISSION_REFERRAL_EXPIRY', 60 * DAY_IN_SECONDS); // 60天
```

### 4. 防止自我推薦

系統已內建防止自我推薦機制：
- 如果用戶email與推薦人email相同
- 自動拒絕建立推薦關係
- 記錄在error log中

---

## 🔍 測試推薦鏈接

### 測試步驟

**1. 準備測試環境**
- 使用無痕模式（或清除Cookie）
- 準備測試用的email地址

**2. 測試推薦鏈接**
```
訪問：https://您的網站.com/TEST1234
```

**3. 檢查Cookie**
- 開啟瀏覽器開發者工具（F12）
- Application → Cookies
- 確認有 `commission_ref_code` = `TEST1234`

**4. 測試註冊**
- 使用測試email註冊新帳號
- 註冊完成後，檢查WordPress後台

**5. 檢查推薦關係**
- 前往「分潤系統」→「推薦人報表」
- 查詢該推薦人的下線
- 應該看到新註冊的用戶

**6. 測試結帳**
- 加商品到購物車
- 進入結帳頁面
- 確認折扣碼自動套用
- 應該看到成功訊息

---

## 🐛 故障排除

### 問題1：Cookie沒有設定

**症狀：**訪問推薦鏈接後，Cookie中沒有折扣碼

**檢查：**
1. 確認折扣碼在資料庫中存在且狀態為 `active`
2. 檢查折扣碼格式是否正確（只能包含字母、數字、連字符、底線）
3. 查看WordPress錯誤日誌：`wp-content/debug.log`

**解決方案：**
```php
// 在 wp-config.php 中啟用調試模式
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### 問題2：折扣碼沒有自動套用

**症狀：**進入結帳頁面時，折扣碼沒有自動套用

**檢查：**
1. 確認Cookie中有折扣碼
2. 確認WooCommerce折價券已創建
3. 檢查是否已手動套用其他折扣碼

**解決方案：**
- 清除購物車中的其他折扣碼
- 重新整理結帳頁面

### 問題3：推薦關係沒有建立

**症狀：**用戶註冊後，downlines表中沒有記錄

**檢查：**
1. 確認Cookie有效期內
2. 確認用戶email與推薦人email不同
3. 檢查是否已經建立過推薦關係

**解決方案：**
- 查看用戶meta：`_commission_referral_code`
- 檢查WordPress錯誤日誌

### 問題4：路徑格式不生效

**症狀：**`https://beamclub.com/TEST1234` 無法識別折扣碼

**原因：**可能與WordPress的Permalink設定衝突

**解決方案：**
- 使用查詢參數格式：`?ref=TEST1234`
- 或調整WordPress的Rewrite Rules

---

## 📊 監控和分析

### 查看推薦成效

**後台報表：**
1. 進入「分潤系統」→「推薦人報表」
2. 選擇推薦人email
3. 查看下線數量和分潤記錄

**數據庫查詢：**
```sql
-- 查看特定推薦人的所有下線
SELECT * FROM wp_commission_downlines
WHERE holder_email = 'holder@example.com'
ORDER BY created_at DESC;

-- 查看本月新增的推薦關係
SELECT * FROM wp_commission_downlines
WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
AND YEAR(created_at) = YEAR(CURRENT_DATE());
```

### 推薦轉換率分析

```php
<?php
// 獲取折扣碼的使用次數（從訂單中統計）
global $wpdb;
$records_table = $wpdb->prefix . 'commission_records';

$usage_stats = $wpdb->get_row($wpdb->prepare("
    SELECT
        COUNT(*) as total_orders,
        SUM(order_total) as total_sales,
        COUNT(DISTINCT holder_email) as unique_holders
    FROM $records_table
    WHERE coupon_code = %s
", 'TEST1234'));

echo "使用次數：" . $usage_stats->total_orders;
echo "總銷售額：$" . number_format($usage_stats->total_sales, 2);
?>
```

---

## 🎓 最佳實踐

### 1. 推薦鏈接命名

**建議：**
- 使用有意義的折扣碼
- 易於記憶和分享
- 避免過長或複雜的代碼

```
✅ 好的範例：TEACHER2024、SAVE20、PREMIUM
❌ 不好的範例：X7KL9P2M、discount_code_123456
```

### 2. 分享策略

**多管道分享：**
- 社交媒體：定期發布推薦鏈接
- Email簽名：在郵件簽名中加入推薦鏈接
- 個人網站：在部落格文章中嵌入推薦鏈接
- 線下活動：在名片、傳單中印刷推薦鏈接

**內容優化：**
- 清楚說明優惠內容
- 強調專屬性和限時性
- 提供明確的行動呼籲（CTA）

### 3. 追蹤優化

**定期檢視：**
- 每週查看推薦成效
- 分析哪些管道轉換率最高
- 調整分享策略

**A/B測試：**
- 測試不同的分享文案
- 比較短網址 vs 完整鏈接
- 試驗不同的著陸頁

---

## 🔐 隱私和安全

### Cookie聲明

建議在網站的隱私政策中加入：

```
本網站使用Cookie來追蹤推薦鏈接和優惠活動。
當您通過推薦鏈接訪問本網站時，我們會存儲一個Cookie
以便在您註冊或購物時自動套用優惠折扣。

Cookie名稱：commission_ref_code
有效期：30天
用途：追蹤推薦來源並自動套用折扣
```

### GDPR合規

如果您的網站需要符合GDPR：
- 在Cookie同意橫幅中說明推薦Cookie
- 提供選擇性同意選項
- 允許用戶隨時刪除Cookie

---

## 📞 技術支援

### 相關文件
- [主程式說明](README.md)
- [API文檔](api.php)
- [數據庫結構](table.php)

### 調試資訊

啟用調試模式後，系統會記錄以下訊息：

```
Commission Referral: Code 'TEST1234' saved to cookie
Commission Referral: User 123 registered via referral code 'TEST1234'
Commission Referral: User 123 bound to holder holder@example.com via code 'TEST1234'
Commission Referral: Auto-applied coupon 'TEST1234' at checkout
```

---

**版本：**1.0.0
**最後更新：**2024-12-19
**作者：**Commission System Team

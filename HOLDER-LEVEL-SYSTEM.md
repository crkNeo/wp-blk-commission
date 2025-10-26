# 🏆 推薦人等級系統說明

## 📋 功能概述

推薦人等級系統根據下線數量自動顯示推薦人的等級，激勵推薦人發展更多下線。

---

## 🎯 等級劃分

| 等級 | 下線人數 | 顯示名稱 | 顏色 | 說明 |
|------|---------|---------|------|------|
| **V級** | 0-100人 | V級 推薦人 | 靛藍色 | 初級推薦人 |
| **P級** | 101-200人 | P級 推薦人 | 紫色 | 中級推薦人 |
| **A級** | 201人以上 | A級 推薦人 | 金色 | 高級推薦人 |

---

## 📊 等級與分潤比例的關係

等級是根據下線數量計算的，同時也影響分潤比例：

### **V級推薦人（0-100人）**
- **基礎分潤率：** 30%（預設）
- **扣除：** Teacher rate + Director rate
- **最終分潤率：** 30% - Teacher rate - Director rate

**範例：**
```
下線數量：50人
基礎分潤率：30%
Teacher rate：5%
Director rate：5%
最終分潤率：20%
```

### **P級推薦人（101-200人）**
- **基礎分潤率：** 35%（預設）
- **扣除：** Teacher rate + Director rate
- **最終分潤率：** 35% - Teacher rate - Director rate

**範例：**
```
下線數量：150人
基礎分潤率：35%
Teacher rate：5%
Director rate：5%
最終分潤率：25%
```

### **A級推薦人（201人以上）**
- **基礎分潤率：** 40%（預設）
- **扣除：** Teacher rate + Director rate
- **最終分潤率：** 40% - Teacher rate - Director rate

**範例：**
```
下線數量：250人
基礎分潤率：40%
Teacher rate：5%
Director rate：5%
最終分潤率：30%
```

---

## 🖼️ 顯示位置

等級信息會顯示在以下位置：

### **1. WooCommerce My Account - 我的分潤頁面**

在「我的角色」卡片中顯示：
```
我的角色
━━━━━━━━━
V級 推薦人
下線人數: 50
```

### **2. 前台分潤報表頁面**

在「我的角色」卡片中顯示：
```
我的角色
━━━━━━━━━
P級 推薦人
下線人數: 150
```

### **3. WordPress Dashboard Widget**

在「我的分潤概況」小工具中顯示：
```
總訂單：25
總分潤：$5,000.00
角色：A級 推薦人
下線: 250 人
```

---

## 🎨 視覺設計

### **等級徽章樣式**

每個等級都有專屬的顏色和漸變效果：

- **V級**：靛藍色漸變 (#6366f1 → 較亮的靛藍)
- **P級**：紫色漸變 (#8b5cf6 → 較亮的紫色)
- **A級**：金色漸變 (#f59e0b → 較亮的金色)

徽章特性：
- ✅ 圓角設計（border-radius: 20px）
- ✅ 漸變背景
- ✅ 陰影效果
- ✅ Hover顯示下線數量

---

## 🔄 等級計算邏輯

### **自動計算**

系統會自動查詢 `commission_downlines` 表，統計該推薦人的下線總數：

```php
function commission_get_holder_level($holder_email) {
    global $wpdb;
    $downlines_table = $wpdb->prefix . 'commission_downlines';

    // 查詢下線數量
    $downline_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $downlines_table WHERE holder_email = %s",
        $holder_email
    ));

    // 根據數量判定等級
    if ($downline_count <= 100) {
        return 'V級';
    } elseif ($downline_count <= 200) {
        return 'P級';
    } else {
        return 'A級';
    }
}
```

### **即時更新**

- ✅ 每次查看報表時動態計算
- ✅ 新增下線後立即反映
- ✅ 無需手動更新

---

## 📈 等級晉升示例

### **案例1：新手推薦人**

```
初始狀態：
- 下線數量：0人
- 等級：V級 推薦人
- 基礎分潤率：30%

第一次推薦成功：
- 下線數量：1人
- 等級：V級 推薦人（仍然）
- 基礎分潤率：30%

達到100人：
- 下線數量：100人
- 等級：V級 推薦人
- 基礎分潤率：30%
```

### **案例2：晉升到P級**

```
突破100人：
- 下線數量：101人
- 等級：P級 推薦人 ⭐ 升級！
- 基礎分潤率：35%（+5%）

持續發展：
- 下線數量：150人
- 等級：P級 推薦人
- 基礎分潤率：35%

達到200人：
- 下線數量：200人
- 等級：P級 推薦人
- 基礎分潤率：35%
```

### **案例3：達到頂級A級**

```
突破200人：
- 下線數量：201人
- 等級：A級 推薦人 ⭐⭐ 最高級！
- 基礎分潤率：40%（再+5%）

頂級推薦人：
- 下線數量：500人
- 等級：A級 推薦人
- 基礎分潤率：40%
```

---

## 🛠️ 技術實現

### **新增函數**

#### **1. commission_get_holder_level($holder_email)**

獲取推薦人的等級信息：

```php
$level_info = commission_get_holder_level('holder@example.com');

// 返回值：
array(
    'level' => 'V',              // 等級代碼
    'level_name' => 'V級',        // 等級名稱
    'display_name' => 'V級 推薦人', // 顯示名稱
    'description' => '初級推薦人',  // 描述
    'downline_count' => 50,       // 下線數量
    'color' => '#6366f1',         // 顏色代碼
    'badge' => '<span>...</span>' // HTML徽章
)
```

#### **2. commission_generate_level_badge($level, $level_name, $color, $downline_count)**

生成等級徽章HTML：

```php
$badge = commission_generate_level_badge('V', 'V級', '#6366f1', 50);

// 輸出：
// <span class="holder-level-badge level-v"
//       style="background: linear-gradient(...)"
//       title="50 位下線">
//     V級
// </span>
```

#### **3. commission_lighten_color($hex, $percent)**

將顏色變亮（用於漸變效果）：

```php
$lighter = commission_lighten_color('#6366f1', 20);
// 返回：較亮的靛藍色
```

### **修改的代碼位置**

**frontend-reports.php:**

1. **第766-776行：** 計算並設定holder等級
2. **第200-208行：** 前台報表頁面顯示
3. **第285-293行：** WooCommerce My Account 顯示
4. **第983-988行：** Dashboard Widget 顯示
5. **第1043-1125行：** 新增等級計算函數

---

## 🎓 使用場景

### **場景1：激勵推薦人**

推薦人看到自己的等級和下線數量，會更有動力發展下線：

```
當前狀態：V級 推薦人（下線：95人）
距離升級：還需要 6 人就能升到 P級！
分潤提升：升級後分潤率從 30% 提升到 35%
```

### **場景2：展示成就**

高等級推薦人可以展示自己的成就：

```
我的等級：A級 推薦人
下線數量：350人
分潤比例：40%

這是系統中的最高等級！
```

### **場景3：分潤說明**

在分潤報表中清楚顯示等級和分潤關係：

```
我的角色：P級 推薦人
下線人數：150
基礎分潤率：35%
Teacher rate：5%
Director rate：5%
實際分潤率：25%
```

---

## ⚙️ 設定選項

### **修改等級劃分標準**

如果需要調整等級標準，編輯 `frontend-reports.php` 的 `commission_get_holder_level` 函數：

```php
// 修改這部分（第1056-1071行）

if ($downline_count <= 100) {        // ← 修改這個數字
    $level = 'V';
    $level_name = 'V級';
    // ...
} elseif ($downline_count <= 200) {  // ← 修改這個數字
    $level = 'P';
    $level_name = 'P級';
    // ...
} else {
    $level = 'A';
    $level_name = 'A級';
    // ...
}
```

### **修改等級名稱**

```php
// 例如改為：Bronze, Silver, Gold
if ($downline_count <= 100) {
    $level = 'Bronze';
    $level_name = '銅牌';
    $color = '#cd7f32';
    // ...
}
```

### **添加更多等級**

```php
if ($downline_count <= 50) {
    // 新手級
} elseif ($downline_count <= 100) {
    // V級
} elseif ($downline_count <= 200) {
    // P級
} elseif ($downline_count <= 500) {
    // A級
} else {
    // 鑽石級
}
```

---

## 🔍 測試建議

### **測試步驟**

**1. 測試V級（0-100人）**
```sql
-- 查看測試holder的下線數量
SELECT COUNT(*) FROM wp_commission_downlines
WHERE holder_email = 'test@example.com';

-- 應該顯示 <= 100
```

**2. 測試P級（101-200人）**
```sql
-- 添加測試下線到101人
INSERT INTO wp_commission_downlines (holder_email, downline_email, created_at)
VALUES ('test@example.com', 'downline101@example.com', NOW());
```

**3. 測試A級（201+人）**
```sql
-- 添加測試下線到201人
-- ... 繼續添加
```

**4. 檢查顯示**
- 登入該用戶帳號
- 前往「我的分潤」頁面
- 確認等級正確顯示
- 確認下線數量正確

---

## 📞 常見問題

### **Q1: 等級何時更新？**
A: 每次查看報表時動態計算，立即反映最新狀態。

### **Q2: 可以手動設定等級嗎？**
A: 不可以，等級完全由下線數量自動決定。

### **Q3: Teacher和Director也有等級嗎？**
A: 目前只有Holder（推薦人）有等級系統。Teacher和Director顯示固定角色名稱。

### **Q4: 等級會降級嗎？**
A: 不會。下線只會增加不會減少（除非手動從資料庫刪除）。

### **Q5: 等級和分潤比例的關係？**
A: 等級反映下線數量，下線數量決定基礎分潤率。參見本文檔的「等級與分潤比例的關係」章節。

---

## 🎉 總結

推薦人等級系統為分潤系統添加了遊戲化元素：

- ✅ **清晰的晉升路徑**：V級 → P級 → A級
- ✅ **即時的視覺反饋**：精美的等級徽章
- ✅ **明確的激勵機制**：更多下線 = 更高等級 = 更高分潤
- ✅ **自動化管理**：無需人工干預，系統自動計算

這將有效激勵推薦人發展更大的推薦網絡！

---

**版本：** 1.0.0
**最後更新：** 2024-12-19
**相關文件：** frontend-reports.php

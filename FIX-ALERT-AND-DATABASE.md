# Fix: Remove Alert & Database Schema Update

## Vấn đề

1. **Alert không cần thiết**: Khi tạo session thành công, hiển thị alert "Phiên học đã được tạo! Đang chuyển đến phòng học..." gây delay không cần thiết
2. **Session không hiển thị trong Student Sessions**: Sau khi tạo session, không thấy trong tab "Đã xác nhận"

## Nguyên nhân

1. Alert gây delay trước khi redirect
2. Database thiếu các columns:
   - `discord_channel` - Lưu link phòng Discord
   - `created_at` - Timestamp tạo session
   - `feedback` - Feedback từ giáo viên

## Giải pháp

### 1. Bỏ Alert, Redirect ngay lập tức

**File: `blocks/teachers-block-frontend.js`**

```javascript
// BEFORE:
if (response.success) {
    alert('Phiên học đã được tạo! Đang chuyển đến phòng học...');
    window.location.href = response.room_link;
}

// AFTER:
if (response.success) {
    // Redirect directly without alert
    window.location.href = response.room_link;
}
```

**Lợi ích:**
- Không có delay
- Chuyển học viên đến Discord ngay lập tức
- Trải nghiệm mượt mà hơn

### 2. Cập nhật Database Schema

**File: `includes/class-activator.php`**

Thêm function `update_database_tables()` để tự động thêm các columns thiếu:

```php
public static function update_database_tables() {
    global $wpdb;
    $table_sessions = $wpdb->prefix . 'dnd_speaking_sessions';
    
    $columns = $wpdb->get_col("DESCRIBE $table_sessions");
    
    // Add discord_channel column
    if (!in_array('discord_channel', $columns)) {
        $wpdb->query("ALTER TABLE $table_sessions ADD COLUMN discord_channel varchar(255) DEFAULT NULL");
    }
    
    // Add created_at column
    if (!in_array('created_at', $columns)) {
        $wpdb->query("ALTER TABLE $table_sessions ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP");
    }
    
    // Add feedback column
    if (!in_array('feedback', $columns)) {
        $wpdb->query("ALTER TABLE $table_sessions ADD COLUMN feedback text DEFAULT NULL");
    }
}
```

**Auto-update khi plugin load:**

```php
add_action('plugins_loaded', function() {
    if (class_exists('DND_Speaking_Activator')) {
        DND_Speaking_Activator::update_database_tables();
    }
    // ... rest of code
});
```

### 3. Thêm Logging để Debug

**File: `includes/class-rest-api.php`**

#### A. Trong `student_start_now()`:

```php
$insert_result = $wpdb->insert(
    $sessions_table,
    [
        'student_id' => $user_id,
        'teacher_id' => $teacher_id,
        'session_date' => current_time('Y-m-d'),
        'session_time' => current_time('H:i:s'),
        'start_time' => current_time('mysql'),
        'status' => 'confirmed',
        'discord_channel' => $room_link,
        'created_at' => current_time('mysql')
    ],
    ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
);

if ($insert_result === false) {
    error_log('Failed to insert session. Error: ' . $wpdb->last_error);
    error_log('SQL Query: ' . $wpdb->last_query);
    return new WP_Error('db_insert_failed', 'Không thể tạo session trong database', ['status' => 500]);
}

$session_id = $wpdb->insert_id;
error_log('Session created successfully. ID: ' . $session_id . ', Student: ' . $user_id . ', Teacher: ' . $teacher_id);
```

#### B. Trong `ajax_get_student_sessions()`:

```php
$sessions = $wpdb->get_results($wpdb->prepare(
    "SELECT s.*, t.display_name as teacher_name
     FROM $sessions_table s
     LEFT JOIN {$wpdb->users} t ON s.teacher_id = t.ID
     WHERE $where_clause
     ORDER BY s.start_time DESC
     LIMIT %d OFFSET %d",
    array_merge($query_params, [$per_page, $offset])
));

error_log('=== STUDENT SESSIONS DEBUG ===');
error_log('SQL Query: ' . $wpdb->last_query);
error_log('Sessions found: ' . count($sessions));
error_log('Filter: ' . $filter . ', User ID: ' . $user_id);
if (!empty($sessions)) {
    error_log('First session: ' . print_r($sessions[0], true));
}
```

## Database Migration Steps

### Cách 1: Tự động (Recommended)

Database sẽ tự động update khi:
1. Plugin được activate lại
2. Plugin load và gọi `update_database_tables()`

### Cách 2: Manual SQL

Nếu cần chạy manual, execute các câu SQL sau:

```sql
ALTER TABLE wp_dnd_speaking_sessions ADD COLUMN discord_channel varchar(255) DEFAULT NULL;
ALTER TABLE wp_dnd_speaking_sessions ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE wp_dnd_speaking_sessions ADD COLUMN feedback text DEFAULT NULL;
```

## Testing

### Test 1: Database Schema
```bash
# Check if columns exist
DESCRIBE wp_dnd_speaking_sessions;

# Expected columns:
# - discord_channel (varchar 255)
# - created_at (datetime)
# - feedback (text)
```

### Test 2: Create Session và Check Logs

1. **Start Now:**
   - Login học viên
   - Click "Start Now"
   - Confirm

2. **Check WordPress error log:**
   ```
   Session created successfully. ID: 123, Student: 456, Teacher: 789, Room Link: https://discord.com/...
   ```

3. **Kiểm tra database:**
   ```sql
   SELECT * FROM wp_dnd_speaking_sessions WHERE id = 123;
   ```

   Expected:
   - `status` = 'confirmed'
   - `discord_channel` = 'https://discord.com/channels/...'
   - `created_at` = timestamp hiện tại

### Test 3: View in Student Sessions

1. Quay lại trang (back button)
2. Vào Student Sessions block
3. Click tab "Đã xác nhận"

**Expected:**
- Thấy session vừa tạo
- Hiển thị link phòng
- Button "Tham gia ngay"

4. **Check WordPress error log:**
   ```
   === STUDENT SESSIONS DEBUG ===
   SQL Query: SELECT s.*, t.display_name as teacher_name FROM wp_dnd_speaking_sessions s LEFT JOIN wp_users t ON s.teacher_id = t.ID WHERE s.student_id = 456 AND s.status IN ('confirmed', 'in_progress') ORDER BY s.start_time DESC LIMIT 10 OFFSET 0
   Sessions found: 1
   Filter: confirmed, User ID: 456
   First session: stdClass Object ( [id] => 123 [student_id] => 456 [teacher_id] => 789 [status] => confirmed [discord_channel] => https://discord.com/... ... )
   ```

## Troubleshooting

### Vấn đề: Session không hiển thị

**Check 1: Database columns exist?**
```sql
DESCRIBE wp_dnd_speaking_sessions;
```

**Check 2: Session được tạo?**
```sql
SELECT * FROM wp_dnd_speaking_sessions 
WHERE student_id = YOUR_USER_ID 
ORDER BY created_at DESC 
LIMIT 5;
```

**Check 3: Check WordPress error log:**
- Đường dẫn: `wp-content/debug.log`
- Enable logging trong `wp-config.php`:
  ```php
  define('WP_DEBUG', true);
  define('WP_DEBUG_LOG', true);
  define('WP_DEBUG_DISPLAY', false);
  ```

**Check 4: Filter query:**
```
Filter: confirmed
Expected WHERE clause: s.student_id = X AND s.status IN ('confirmed', 'in_progress')
```

## Summary

✅ **Removed alert** - Redirect ngay lập tức  
✅ **Added database columns** - discord_channel, created_at, feedback  
✅ **Added logging** - Debug session creation và query  
✅ **Auto database migration** - Tự động update schema khi plugin load  

Học viên giờ có thể:
1. Click "Start Now" → Redirect đến Discord ngay
2. Quay lại → Vào Student Sessions
3. Tab "Đã xác nhận" → Thấy session với link join
4. Click "Tham gia ngay" → Join lại bất cứ lúc nào

# Update: In Progress Status & Teacher Busy Management

## Tổng quan thay đổi

### 1. **Status từ 'confirmed' → 'in_progress'**
Session được tạo ngay với status 'in_progress' thay vì 'confirmed'

### 2. **Bỏ hiển thị "Link phòng"**
Chỉ hiển thị button "Tham gia ngay", không hiển thị text link phòng

### 3. **Teacher Status Management**
- Khi học viên Start Now thành công → Teacher status = 'busy'
- Teacher busy → Không hiển thị nút "Start Now"
- Validate teacher có thực sự available không trước khi tạo session

## Chi tiết thay đổi

### 1. Student Start Now - Create In Progress Session

**File: `includes/class-rest-api.php` - Function `student_start_now()`**

#### A. Validate Teacher Status (NEW)
```php
// Check if teacher is actually available (not offline or busy)
$teacher_available = get_user_meta($teacher_id, 'dnd_available', true);
if ($teacher_available !== '1') {
    return new WP_REST_Response([
        'success' => false,
        'teacher_not_available' => true,
        'message' => 'Xin lỗi, giáo viên hiện đang bận hoặc offline. Vui lòng thử lại sau.'
    ], 200);
}
```

**Teacher Status Values:**
- `'1'` = Available (online)
- `'busy'` = Busy (đang có học viên)
- `''` or `'0'` = Offline

#### B. Create Session with 'in_progress' Status
```php
$wpdb->insert(
    $sessions_table,
    [
        'student_id' => $user_id,
        'teacher_id' => $teacher_id,
        'session_date' => current_time('Y-m-d'),
        'session_time' => current_time('H:i:s'),
        'start_time' => current_time('mysql'),
        'status' => 'in_progress',  // Changed from 'confirmed'
        'discord_channel' => $room_link,
        'created_at' => current_time('mysql')
    ],
    ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
);
```

#### C. Set Teacher to Busy (NEW)
```php
// Set teacher status to busy
update_user_meta($teacher_id, 'dnd_available', 'busy');
error_log('Teacher ' . $teacher_id . ' status set to busy');
```

### 2. Teachers List - Hide Start Now for Busy Teachers

**File: `includes/class-rest-api.php` - Function `get_teachers()`**

```php
foreach ($users as $user) {
    $available_status = get_user_meta($user->ID, 'dnd_available', true);
    // Only show as available if status is '1' (not 'busy' or empty)
    $available = $available_status === '1';
    $teachers[] = [
        'id' => $user->ID,
        'name' => $user->display_name,
        'available' => $available,  // false if busy or offline
    ];
}
```

**Result:**
- Teacher với `dnd_available = '1'` → `available: true` → Show "Start Now"
- Teacher với `dnd_available = 'busy'` → `available: false` → NO "Start Now" button
- Teacher với `dnd_available = ''` → `available: false` → NO "Start Now" button

### 3. Frontend - Handle Teacher Not Available

**File: `blocks/teachers-block-frontend.js`**

```javascript
success: function(response) {
    $loadingModal.remove();
    
    if (response.success) {
        window.location.href = response.room_link;
    } else if (response.teacher_not_available) {
        // Teacher is offline or busy
        alert(response.message);
    } else if (response.need_discord_connection) {
        // ... Discord connection logic
    }
}
```

### 4. Bỏ hiển thị "Link phòng"

**Files:**
- `includes/class-rest-api.php` - Function `render_student_session_card()`
- `blocks/student-sessions-block.php` - Function `render_session_card()`

```php
// BEFORE:
$room_link_html = '';
if ($session->status === 'confirmed' && !empty($session->discord_channel)) {
    $room_link_html = '<div class="dnd-session-room-link">
        <strong>Link phòng:</strong> <a href="...">...</a>
    </div>';
}

// AFTER:
// Don't show room link text, only button will have the link
$room_link_html = '';
```

### 5. Render In Progress Sessions

**File: `includes/class-rest-api.php`**

```php
case 'in_progress':
    $status_text = 'Đang diễn ra';
    $status_class = 'in_progress';
    
    // Show join button with room link if available
    if (!empty($session->discord_channel)) {
        $actions = '<a href="' . esc_url($session->discord_channel) . '" class="dnd-btn dnd-btn-join">Tham gia ngay</a>';
    } else {
        $actions = '';
    }
    $actions .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '">Hủy</button>';
    break;
```

## Flow hoàn chỉnh

### Scenario 1: Học viên Start Now thành công

1. **Teacher status**: `dnd_available = '1'` (online)
2. Học viên click "Start Now"
3. **API validates**: Teacher có available không?
   - ✅ Yes → Continue
   - ❌ No → Return error "Giáo viên đang bận hoặc offline"
4. **Create session**: status = 'in_progress'
5. **Update teacher**: `dnd_available = 'busy'`
6. **Redirect**: Học viên đến Discord room
7. **Teacher list updates**: Start Now button biến mất (teacher busy)

### Scenario 2: Học viên Start Now khi teacher đã busy

**Tình huống:** Teacher toggle offline, nhưng học viên không F5 lại trang

1. Frontend vẫn hiển thị nút "Start Now" (stale data)
2. Học viên click "Start Now"
3. **API validates**: `dnd_available !== '1'` (busy hoặc offline)
4. **Return error**:
   ```json
   {
     "success": false,
     "teacher_not_available": true,
     "message": "Xin lỗi, giáo viên hiện đang bận hoặc offline. Vui lòng thử lại sau."
   }
   ```
5. Frontend hiển thị alert với message
6. Không tạo session

### Scenario 3: Học viên vào Student Sessions

1. Vào tab "Đã xác nhận"
2. Thấy sessions với status = 'in_progress' hoặc 'confirmed'
3. **Hiển thị:**
   - Giáo viên: [Name]
   - Trạng thái: Đang diễn ra
   - Thời gian: [Date/Time]
   - **Button**: "Tham gia ngay" (link trực tiếp)
   - **NO**: "Link phòng: https://..." text

### Scenario 4: Teacher kết thúc session

**Note:** Cần implement thêm endpoint để:
1. Set `end_time` cho session
2. Set teacher status từ 'busy' về '1'
3. Update status session từ 'in_progress' sang 'completed'

## Teacher Status State Machine

```
┌─────────┐
│ Offline │ (dnd_available = '' or '0')
└────┬────┘
     │
     │ Toggle Online
     ▼
┌─────────┐
│ Online  │ (dnd_available = '1')
└────┬────┘
     │
     │ Student Start Now
     ▼
┌─────────┐
│  Busy   │ (dnd_available = 'busy')
└────┬────┘
     │
     │ Session End (TODO)
     ▼
┌─────────┐
│ Online  │ (dnd_available = '1')
└─────────┘
```

## Database Impact

### Sessions Table
- Mỗi session tạo với status = 'in_progress'
- Hiển thị trong tab "Đã xác nhận" (query includes 'in_progress')

### User Meta
- `dnd_available` values:
  - `'1'` = Online, ready for students
  - `'busy'` = Currently teaching
  - `''` or `'0'` = Offline

## Testing

### Test 1: Start Now Success
1. Teacher toggle online (`dnd_available = '1'`)
2. Học viên click "Start Now"
3. **Expected:**
   - Session tạo với status = 'in_progress'
   - Teacher status = 'busy'
   - Redirect đến Discord
   - Start Now button biến mất trên teacher card

### Test 2: Start Now - Teacher Already Busy
1. Teacher có `dnd_available = 'busy'`
2. Học viên khác click "Start Now" (stale frontend)
3. **Expected:**
   - Alert: "Xin lỗi, giáo viên hiện đang bận..."
   - Không tạo session
   - Teacher status không đổi

### Test 3: View In Progress Session
1. Vào Student Sessions
2. Tab "Đã xác nhận"
3. **Expected:**
   - Thấy session với "Trạng thái: Đang diễn ra"
   - Button "Tham gia ngay" có link
   - KHÔNG có text "Link phòng: ..."

### Test 4: Frontend Display
1. Reload teacher list
2. **Expected:**
   - Teacher online (`dnd_available = '1'`) → Hiện "Start Now"
   - Teacher busy (`dnd_available = 'busy'`) → KHÔNG hiện "Start Now"
   - Teacher offline → KHÔNG hiện "Start Now"

## TODO: Teacher End Session

Cần implement endpoint để giáo viên kết thúc session:

```php
public function teacher_end_session($request) {
    $session_id = intval($request->get_param('session_id'));
    $teacher_id = get_current_user_id();
    
    // Validate teacher owns this session
    // Set end_time
    // Update status to 'completed'
    // Set teacher status back to '1' (online)
    
    update_user_meta($teacher_id, 'dnd_available', '1');
}
```

## Summary

✅ **Session status = 'in_progress'** - Phản ánh đúng trạng thái  
✅ **Teacher busy management** - Tránh double booking  
✅ **Validate teacher status** - Check realtime trước khi tạo session  
✅ **Clean UI** - Chỉ hiển thị button, bỏ link text  
✅ **Error handling** - Xử lý trường hợp teacher offline/busy  

⚠️ **Next step:** Implement teacher end session để set status về online

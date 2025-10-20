# Changelog - Complete Session Flow with Webhook Integration

## Ngày: 2025-10-20

## Tổng quan luồng hoạt động

### 1. Student Start Now
- Học viên click "Start Now" trên teacher card
- Backend tạo session với `status = 'in_progress'`
- Set teacher status = `'busy'`
- Gửi webhook để tạo room trên Discord
- Lưu room link vào session

### 2. Teacher Status Display When Busy
- Teacher Status Block hiển thị **Offline** (toggle về trái)
- Nhưng vẫn có **room link** (active)
- Toggle bị **disabled** (không thể thay đổi)
- Hiển thị thông báo: "Bạn đang trong buổi học"

### 3. Cancel In-Progress Session
**Từ Teacher:**
- Teacher nhấn "Hủy buổi học" trên session đang `in_progress`
- Gửi webhook với action `teacher_cancel_session`
- Xóa room metadata
- Set teacher status về `'1'` (available/online)
- Reload page → Teacher Status hiển thị Online, no room

**Từ Student:**
- Student nhấn "Hủy" trên session đang `in_progress`
- Gửi webhook với action `student_cancel_session`
- Xóa room metadata của teacher
- Set teacher status về `'1'` (available/online)

### 4. Complete In-Progress Session
- Teacher nhấn "Kết thúc" trên session đang `in_progress`
- Gửi webhook với action `teacher_complete_session`
- Xóa room metadata
- Set teacher status về `'1'` (available/online)
- Update session status = `'completed'`
- Reload page → Teacher Status hiển thị Online, no room

### 5. Reset Room Link
Sau khi hủy hoặc hoàn thành session:
- Xóa metadata:
  - `discord_voice_channel_id`
  - `discord_voice_channel_invite`
- Set teacher status về `'0'` (offline)
- Teacher Status Block hiển thị:
  - Trạng thái: **Offline**
  - Room: "**Link room**" (không active)

---

## Chi tiết thay đổi

### File: `blocks/teacher-header-block.php`

**Function: `render_block()`**

**Thay đổi:**
```php
// Lấy status từ user meta
$status = get_user_meta($user_id, 'dnd_available', true);

// Phân biệt 3 trạng thái:
// '1' = available (online)
// 'busy' = đang trong buổi học (hiển thị offline nhưng có room)
// '0' hoặc empty = offline (không có room)

$is_available = ($status == '1');
$is_busy = ($status === 'busy');

// Disable toggle khi busy
'<input type="checkbox" id="teacher-status-toggle" ' 
    . ($is_available ? 'checked' : '') 
    . ($is_busy ? ' disabled' : '') . '>';

// Hiển thị thông báo khi busy
if ($is_busy) {
    '<div class="status-info">Bạn đang trong buổi học</div>';
}
```

---

### File: `blocks/teacher-header-block-frontend.js`

**Thay đổi:**
```javascript
// Check if toggle is disabled (teacher is busy)
if ($toggle.prop('disabled')) {
    // Prevent any interaction when teacher is busy
    $toggle.on('click', function(e) {
        e.preventDefault();
        alert('Bạn đang trong buổi học. Vui lòng hoàn thành hoặc hủy buổi học hiện tại trước khi thay đổi trạng thái.');
        return false;
    });
    return; // Don't attach other event handlers
}
```

---

### File: `includes/class-rest-api.php`

#### Function: `ajax_update_session_status()`

**Thay đổi:**
```php
$room_cleared = false;
$webhook_url = get_option('dnd_discord_webhook');

// Extract room_id from discord_channel URL
$room_id = '';
if (!empty($session->discord_channel) && preg_match('/channels\/\d+\/(\d+)/', $session->discord_channel, $matches)) {
    $room_id = $matches[1];
}

// Case 1: Cancel in_progress session
if ($new_status === 'cancelled' && $session->status === 'in_progress' && !empty($session->discord_channel)) {
    // Send webhook
    wp_remote_post($webhook_url, [...]);
    // Clean metadata
    delete_user_meta($user_id, 'discord_voice_channel_id');
    delete_user_meta($user_id, 'discord_voice_channel_invite');
    // Reset status to offline
    update_user_meta($user_id, 'dnd_available', '0');
    $room_cleared = true;
}

// Case 2: Complete in_progress session
if ($new_status === 'completed' && $session->status === 'in_progress' && !empty($session->discord_channel)) {
    // Send webhook with action 'teacher_complete_session'
    wp_remote_post($webhook_url, [...]);
    // Clean metadata
    delete_user_meta($user_id, 'discord_voice_channel_id');
    delete_user_meta($user_id, 'discord_voice_channel_invite');
    // Reset status to offline
    update_user_meta($user_id, 'dnd_available', '0');
    $room_cleared = true;
}

// Return room_cleared flag
wp_send_json_success([
    'message' => 'Session status updated successfully',
    'room_cleared' => $room_cleared
]);
```

**Webhook Payloads:**

1. **Teacher Cancel:**
```json
{
    "action": "teacher_cancel_session",
    "teacher_wp_id": 123,
    "session_id": 456,
    "room_id": "987654321",
    "server_id": "123456789"
}
```

2. **Teacher Complete:**
```json
{
    "action": "teacher_complete_session",
    "teacher_wp_id": 123,
    "session_id": 456,
    "room_id": "987654321",
    "server_id": "123456789"
}
```

---

#### Function: `cancel_session()`

**Thay đổi:**
```php
// Only send webhook if session is in_progress
if ($session->status === 'in_progress' && $has_room_link) {
    // Extract room_id from URL
    $room_id = '';
    if (preg_match('/channels\/\d+\/(\d+)/', $session->discord_channel, $matches)) {
        $room_id = $matches[1];
    }
    
    // Send webhook
    wp_remote_post($webhook_url, [
        'body' => json_encode([
            'action' => 'student_cancel_session',
            'teacher_wp_id' => $teacher_id,
            'student_wp_id' => $user_id,
            'session_id' => $session_id,
            'room_id' => $room_id,
            'server_id' => get_option('dnd_discord_server_id')
        ])
    ]);
    
    // Clean up teacher's room metadata
    delete_user_meta($teacher_id, 'discord_voice_channel_id');
    delete_user_meta($teacher_id, 'discord_voice_channel_invite');
    
    // Reset teacher status to offline
    update_user_meta($teacher_id, 'dnd_available', '0');
}
```

**Webhook Payload:**
```json
{
    "action": "student_cancel_session",
    "teacher_wp_id": 123,
    "student_wp_id": 456,
    "session_id": 789,
    "room_id": "987654321",
    "server_id": "123456789"
}
```

---

### File: `blocks/session-history-block-frontend.js`

**Function: `updateSessionStatus()`**

**Thay đổi:**
```javascript
if (response.data && response.data.room_cleared) {
    if (newStatus === 'cancelled') {
        alert('Buổi học đã được hủy. Phòng học đã được xóa và trạng thái của bạn đã được cập nhật về Offline.');
    } else if (newStatus === 'completed') {
        alert('Buổi học đã hoàn thành. Phòng học đã được xóa và trạng thái của bạn đã được cập nhật về Offline.');
    }
    location.reload(); // Reload to refresh Teacher Status Block
}
```

---

## Tóm tắt các webhook actions

| Action | Trigger | Payload |
|--------|---------|---------|
| `student_start_now` | Học viên start now | student_discord_id, teacher_room_id |
| `student_cancel_session` | Học viên hủy session in_progress | teacher_wp_id, student_wp_id, room_id |
| `teacher_cancel_session` | Giáo viên hủy session in_progress | teacher_wp_id, session_id, room_id |
| `teacher_complete_session` | Giáo viên hoàn thành session | teacher_wp_id, session_id, room_id |

---

## Testing Checklist

### 1. Student Start Now
- [ ] Session được tạo với status = 'in_progress'
- [ ] Teacher status = 'busy'
- [ ] Teacher Status Block hiển thị Offline + có room link
- [ ] Toggle bị disabled
- [ ] Hiển thị "Bạn đang trong buổi học"

### 2. Teacher Cancel In-Progress
- [ ] Webhook gửi với action 'teacher_cancel_session'
- [ ] Room metadata bị xóa
- [ ] Teacher status = '0' (offline)
- [ ] Page reload
- [ ] Teacher Status Block hiển thị Offline + no room
- [ ] Session status = 'cancelled'

### 3. Student Cancel In-Progress
- [ ] Webhook gửi với action 'student_cancel_session'
- [ ] Teacher room metadata bị xóa
- [ ] Teacher status = '0' (offline)
- [ ] Session status = 'cancelled'

### 4. Teacher Complete In-Progress
- [ ] Webhook gửi với action 'teacher_complete_session'
- [ ] Room metadata bị xóa
- [ ] Teacher status = '0' (offline)
- [ ] Page reload
- [ ] Teacher Status Block hiển thị Offline + no room
- [ ] Session status = 'completed'

### 5. Teacher Toggle Disabled When Busy
- [ ] Khi status = 'busy', toggle bị disabled
- [ ] Click vào toggle hiển thị alert
- [ ] Không thể thay đổi trạng thái khi đang busy

---

## Debug Logs

```
STUDENT CANCEL IN_PROGRESS SESSION - Sending webhook to delete room: [room_id]
STUDENT CANCEL IN_PROGRESS SESSION - Cleaned up room metadata and set status to offline for teacher: [teacher_id]

TEACHER CANCEL IN_PROGRESS SESSION - Sending webhook to delete room: [room_id]
TEACHER CANCEL IN_PROGRESS SESSION - Cleaned up room metadata and set status to offline for teacher: [teacher_id]

TEACHER COMPLETE IN_PROGRESS SESSION - Sending webhook to delete room: [room_id]
TEACHER COMPLETE IN_PROGRESS SESSION - Cleaned up room metadata and set status to offline for teacher: [teacher_id]
```

---

## Dependencies

- Webhook URL: `dnd_discord_webhook` option
- Discord Server ID: `dnd_discord_server_id` option
- Discord server endpoint phải xử lý các actions:
  - `student_cancel_session`
  - `teacher_cancel_session`
  - `teacher_complete_session`

---

## Notes

- Tất cả webhook requests đều non-blocking (`'blocking' => false`)
- Page reload đảm bảo UI consistency
- Room ID được extract từ URL pattern: `/channels/\d+/(\d+)/`
- Teacher status có 3 giá trị: `'1'` (online), `'busy'` (trong buổi học), `'0'/empty (offline)
- Sau khi cancel hoặc complete session, teacher status sẽ về `'0'` (offline), không phải về online

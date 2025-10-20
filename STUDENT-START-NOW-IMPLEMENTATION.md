# Student Start Now - Implementation Summary

## Tổng quan
Tính năng "Student Start Now" cho phép học viên bắt đầu phiên học ngay lập tức với giáo viên đang online.

## Các thay đổi đã thực hiện

### 1. Xóa Block Student Session History
- **Files đã xóa:**
  - `blocks/student-session-history-block.php`
  - `blocks/student-session-history-block.js`
  - `blocks/student-session-history-block.css`
  - `blocks/student-session-history-block-frontend.js`

- **Files đã sửa:**
  - `dnd-speaking.php`: Xóa require_once cho student-session-history-block
  - `includes/class-rest-api.php`: Xóa ajax_get_student_session_history handler

### 2. Webhook Integration - Unified Discord Webhook

**File: `includes/class-admin.php`**

Cấu hình webhook duy nhất trong Discord Settings > Webhook Integration:
- **Setting name:** `dnd_discord_webhook`
- **Label:** "Discord Webhook URL"
- **Description:** "Webhook URL for all Discord integrations. The system will send different 'action' values: 'online' (teacher goes online), 'offline' (teacher goes offline), 'student_start_now' (student starts a session)."

**Note:** Webhook này được sử dụng chung cho tất cả các action:
- `action: 'online'` - Khi giáo viên bật trạng thái available
- `action: 'offline'` - Khi giáo viên tắt trạng thái available
- `action: 'student_start_now'` - Khi học viên bắt đầu phiên học với giáo viên

### 3. REST API Endpoint - Student Start Now

**File: `includes/class-rest-api.php`**

**Endpoint mới:**
- **URL:** `/wp-json/dnd-speaking/v1/student/start-now`
- **Method:** POST
- **Parameters:** `teacher_id` (required)
- **Permission:** User phải đăng nhập

**Logic flow:**

1. **Kiểm tra Discord Connection:**
   - Kiểm tra `discord_connected` user meta
   - Nếu chưa kết nối: trả về `need_discord_connection: true` với Discord auth URL

2. **Kiểm tra Active Session:**
   - Query database tìm session với status = 'confirmed' và end_time IS NULL
   - Nếu có session đang active: trả về `has_active_session: true` với room link hiện tại
   - User có thể chọn tiếp tục với session hiện tại hoặc hủy

3. **Lấy Teacher Room ID:**
   - Lấy `discord_voice_channel_id` từ teacher user meta
   - Đây là room ID mà giáo viên đã tạo khi bật trạng thái online

4. **Gửi Webhook Request:**
   - URL: `dnd_discord_webhook` (từ settings - unified webhook cho tất cả actions)
   - Body:
     ```json
     {
       "action": "student_start_now",
       "student_discord_id": "123456789",
       "student_discord_name": "StudentName",
       "student_wp_id": 123,
       "teacher_wp_id": 456,
       "teacher_room_id": "987654321",
       "server_id": "111222333"
     }
     ```

5. **Nhận Response từ Webhook:**
   - Expected response:
     ```json
     {
       "success": true,
       "room_id": "channel_id_here",
       "room_link": "https://discord.com/channels/SERVER_ID/CHANNEL_ID"
     }
     ```
   - Nếu không có `room_link`, tự động tạo từ `room_id` và `server_id`

6. **Tạo Confirmed Session:**
   - Insert vào database với status = 'confirmed'
   - Lưu `discord_channel` (room link)
   - Set `start_time` = current time
   - Set `session_date` và `session_time` = current date/time

7. **Trả về Response:**
   ```json
   {
     "success": true,
     "session_id": 123,
     "room_link": "https://discord.com/channels/...",
     "message": "Phiên học đã được tạo thành công!"
   }
   ```

### 4. Frontend Implementation

**File: `blocks/teachers-block-frontend.js`**

**Function mới:** `startNowSession(teacherId, teacherName)`

**Flow:**
1. Hiển thị confirm dialog: "Bạn có muốn bắt đầu phiên học với [Teacher Name] ngay bây giờ không?"
2. Hiển thị loading modal: "Đang kết nối..."
3. Gọi API endpoint `/student/start-now`
4. Xử lý response:
   - **Success:** Mở room link trong tab mới, reload trang sau 1 giây
   - **Need Discord:** Hiển thị confirm để redirect đến Discord auth
   - **Has Active Session:** Hiển thị confirm với option mở room hiện tại
   - **Error:** Hiển thị error message

**Event Handler:**
```javascript
$('.dnd-btn-start').on('click', function() {
    const teacherId = $(this).data('teacher-id');
    const teacherName = $(this).closest('.dnd-teacher-card').find('.dnd-teacher-name').text();
    startNowSession(teacherId, teacherName);
});
```

### 5. Student Sessions Block Enhancement

**File: `blocks/student-sessions-block.php`**

**Cải tiến hiển thị cho Confirmed Sessions:**

1. **Hiển thị Room Link:**
   - Thêm section hiển thị link phòng Discord cho sessions với status = 'confirmed'
   - Format: `<a href="room_link" target="_blank">room_link</a>`

2. **Join Button:**
   - Thay đổi button "Tham gia ngay" thành link trực tiếp đến room
   - Class: `dnd-btn dnd-btn-join`
   - Opens in new tab

3. **Status Display:**
   - "Đã xác nhận" - normal confirmed status
   - "Sẵn sàng tham gia" - trong vòng 15 phút trước scheduled time

**HTML Structure:**
```html
<div class="dnd-session-card">
    <div class="dnd-session-teacher">Giáo viên: [Name]</div>
    <div class="dnd-session-status confirmed">Trạng thái: Đã xác nhận</div>
    <div class="dnd-session-time">Thời gian: [Date/Time]</div>
    <div class="dnd-session-room-link">
        <strong>Link phòng:</strong> <a href="..." target="_blank">...</a>
    </div>
    <div class="dnd-session-actions">
        <a href="..." target="_blank" class="dnd-btn dnd-btn-join">Tham gia phòng</a>
        <button class="dnd-btn dnd-btn-cancel">Hủy</button>
    </div>
</div>
```

## Webhook Server Requirements

Server webhook cần implement một endpoint duy nhất nhận request từ WordPress với các action khác nhau:

### Action: "student_start_now"
1. **Nhận data:**
   - `action`: "student_start_now"
   - `student_discord_id`: Discord ID của học viên
   - `teacher_room_id`: Discord channel ID của giáo viên
   - `server_id`: Discord server ID

2. **Xử lý:**
   - Thêm học viên vào voice channel của giáo viên
   - Set permissions cho học viên có thể view và join channel
   - Tạo invite link hoặc direct channel link

3. **Trả về:**
   ```json
   {
     "success": true,
     "room_id": "channel_id",
     "room_link": "https://discord.com/channels/SERVER_ID/CHANNEL_ID"
   }
   ```

### Action: "online"
1. **Nhận data:**
   - `action`: "online"
   - `discord_user_id`: Discord ID của giáo viên
   - `discord_global_name`: Tên Discord của giáo viên
   - `server_id`: Discord server ID

2. **Xử lý:**
   - Tạo voice channel cho giáo viên

3. **Trả về:**
   ```json
   {
     "channelId": "channel_id"
   }
   ```

### Action: "offline"
1. **Nhận data:**
   - `action`: "offline"
   - `discord_user_id`: Discord ID của giáo viên
   - `channelId`: ID của voice channel cần xóa
   - `server_id`: Discord server ID

2. **Xử lý:**
   - Xóa voice channel của giáo viên

## Testing Checklist

- [ ] Admin có thể cấu hình Discord Webhook URL (unified webhook)
- [ ] Học viên chưa kết nối Discord: Hiển thị link kết nối
- [ ] Học viên đã kết nối Discord: Có thể nhấn Start Now
- [ ] Kiểm tra duplicate session: Nếu đang trong phòng, hiển thị confirm
- [ ] Webhook nhận đúng action "student_start_now" với đầy đủ data
- [ ] Webhook nhận đúng action "online" khi giáo viên bật available
- [ ] Webhook nhận đúng action "offline" khi giáo viên tắt available
- [ ] Nhận room_id từ webhook và tạo session
- [ ] Hiển thị room link trong Student Sessions
- [ ] Click "Tham gia phòng" mở Discord trong tab mới
- [ ] Session được lưu với status = 'confirmed'
- [ ] Reload trang sau khi tạo session thành công

## Database Schema

Session record được tạo với các fields:
- `student_id`: WordPress user ID
- `teacher_id`: WordPress user ID
- `session_date`: Current date (Y-m-d)
- `session_time`: Current time (H:i:s)
- `start_time`: Current timestamp (mysql format)
- `status`: 'confirmed'
- `discord_channel`: Room link URL
- `created_at`: Current timestamp

## Notes

- Session được tạo ngay lập tức khi webhook trả về success
- Không cần approval từ giáo viên (khác với Book Now)
- Link phòng được lưu trực tiếp vào database
- Học viên có thể join bất cứ lúc nào qua link trong Student Sessions
- System không tự động end session, cần giáo viên hoặc học viên kết thúc manually

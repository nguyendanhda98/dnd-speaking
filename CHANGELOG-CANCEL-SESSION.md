# Changelog - Cancel In-Progress Session Feature

## Ngày: 2025-10-20

## Tính năng: Hủy buổi học đang diễn ra (in_progress) và cập nhật Teacher Status

### Mô tả
Khi giáo viên nhấn nút "Hủy buổi học" cho một buổi học đang diễn ra (status = in_progress), hệ thống sẽ:
1. Gửi webhook đến Discord server để xóa phòng học
2. Xóa metadata phòng học của giáo viên
3. Cập nhật trạng thái giáo viên từ "busy" về "available" (online)
4. Cập nhật Teacher Status Block để hiển thị không còn phòng học

### Các file đã sửa đổi

#### 1. `includes/class-rest-api.php`
**Function: `ajax_update_session_status()`**

**Thay đổi:**
- Thêm logic kiểm tra khi cancel session có status = 'in_progress' và có discord_channel
- Trích xuất room_id từ discord_channel URL (format: https://discord.com/channels/SERVER_ID/CHANNEL_ID)
- Gửi webhook với action = 'teacher_cancel_session' và room_id
- Xóa metadata:
  - `discord_voice_channel_id`
  - `discord_voice_channel_invite`
- Cập nhật `dnd_available` từ 'busy' về '1' (online)
- Thêm error logging để debug
- Response trả về thêm field `room_cleared` để frontend biết cần reload page

**Webhook payload:**
```json
{
    "action": "teacher_cancel_session",
    "teacher_wp_id": <teacher_user_id>,
    "session_id": <session_id>,
    "room_id": <extracted_channel_id>,
    "server_id": <discord_server_id>
}
```

#### 2. `blocks/session-history-block-frontend.js`
**Function: `updateSessionStatus()`**

**Thay đổi:**
- Kiểm tra response.data.room_cleared
- Nếu true (đã xóa phòng), hiển thị thông báo và reload toàn bộ page
- Reload page để refresh Teacher Status Block với trạng thái mới

### Luồng hoạt động

1. **Giáo viên click "Hủy buổi học" trên session đang in_progress**
   - Frontend gọi AJAX đến `ajax_update_session_status`
   - Payload: `{session_id, new_status: 'cancelled'}`

2. **Backend xử lý (class-rest-api.php)**
   - Kiểm tra session thuộc về teacher
   - Phát hiện session có status = 'in_progress' và có discord_channel
   - Trích xuất room_id từ URL
   - Gửi webhook non-blocking đến Discord server
   - Xóa metadata phòng học của teacher
   - Update teacher status về '1' (available/online)
   - Update session status = 'cancelled'
   - Trả về success với room_cleared = true

3. **Frontend nhận response**
   - Phát hiện room_cleared = true
   - Hiển thị thông báo cho teacher
   - Reload toàn bộ page

4. **Page reload**
   - Teacher Status Block refresh
   - Hiển thị trạng thái: Online
   - Link phòng: "Link room" (không active)
   - Session History Block refresh
   - Session đã chuyển sang status 'cancelled'

### Testing Checklist

- [ ] Tạo session in_progress có discord_channel
- [ ] Teacher cancel session
- [ ] Kiểm tra webhook được gửi với đúng room_id
- [ ] Kiểm tra metadata phòng đã bị xóa
- [ ] Kiểm tra teacher status = '1' (available)
- [ ] Kiểm tra page reload
- [ ] Kiểm tra Teacher Status Block hiển thị đúng (Online, no room)
- [ ] Kiểm tra session history hiển thị status 'cancelled'

### Debug Logs

Các log được thêm vào để debug:
```
TEACHER CANCEL IN_PROGRESS SESSION - Sending webhook to delete room: [room_id]
TEACHER CANCEL IN_PROGRESS SESSION - Cleaned up room metadata and set status to available for teacher: [teacher_id]
```

### Dependencies

- Webhook URL phải được cấu hình trong `dnd_discord_webhook` option
- Discord Server ID phải được cấu hình trong `dnd_discord_server_id` option
- Discord server phải có endpoint xử lý action `teacher_cancel_session`

### Notes

- Webhook request là non-blocking (`'blocking' => false`) để không làm chậm response
- Page reload đảm bảo tất cả blocks đều được refresh với dữ liệu mới
- Regex pattern để extract room_id: `/channels\/\d+\/(\d+)/`

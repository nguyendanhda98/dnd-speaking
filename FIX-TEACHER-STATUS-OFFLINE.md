# Fix: Teacher Status After Cancel/Complete Session

## Ngày: 2025-10-20

## Vấn đề
Khi giáo viên hủy hoặc hoàn thành buổi học đang diễn ra, Teacher Status Block hiển thị trạng thái **Online** (toggle bật). Người dùng yêu cầu sau khi hủy/hoàn thành, trạng thái phải là **Offline** và không có phòng.

## Giải pháp

### Thay đổi trong `includes/class-rest-api.php`

#### 1. Function: `ajax_update_session_status()` - Teacher Cancel

**Trước:**
```php
// Reset teacher status from busy to available (online)
update_user_meta($user_id, 'dnd_available', '1');
error_log('... set status to available for teacher: ' . $user_id);
```

**Sau:**
```php
// Reset teacher status to offline (not available)
update_user_meta($user_id, 'dnd_available', '0');
error_log('... set status to offline for teacher: ' . $user_id);
```

#### 2. Function: `ajax_update_session_status()` - Teacher Complete

**Trước:**
```php
// Reset teacher status from busy to available (online)
update_user_meta($user_id, 'dnd_available', '1');
error_log('... set status to available for teacher: ' . $user_id);
```

**Sau:**
```php
// Reset teacher status to offline (not available)
update_user_meta($user_id, 'dnd_available', '0');
error_log('... set status to offline for teacher: ' . $user_id);
```

#### 3. Function: `cancel_session()` - Student Cancel

**Trước:**
```php
// Reset teacher status from busy to available (online)
update_user_meta($teacher_id, 'dnd_available', '1');
error_log('... set status to available for teacher: ' . $teacher_id);
```

**Sau:**
```php
// Reset teacher status to offline (not available)
update_user_meta($teacher_id, 'dnd_available', '0');
error_log('... set status to offline for teacher: ' . $teacher_id);
```

### Thay đổi trong `blocks/session-history-block-frontend.js`

**Trước:**
```javascript
alert('Buổi học đã được hủy. Phòng học đã được xóa và trạng thái của bạn đã được cập nhật về Online.');
alert('Buổi học đã hoàn thành. Phòng học đã được xóa và trạng thái của bạn đã được cập nhật về Online.');
```

**Sau:**
```javascript
alert('Buổi học đã được hủy. Phòng học đã được xóa và trạng thái của bạn đã được cập nhật về Offline.');
alert('Buổi học đã hoàn thành. Phòng học đã được xóa và trạng thái của bạn đã được cập nhật về Offline.');
```

## Kết quả

### Trước khi fix:
1. Teacher ở trạng thái **Busy** (đang trong buổi học)
2. Teacher hủy/hoàn thành buổi học
3. ❌ Teacher Status hiển thị: **Online** (toggle bật) + no room

### Sau khi fix:
1. Teacher ở trạng thái **Busy** (đang trong buổi học)
2. Teacher hủy/hoàn thành buổi học
3. ✅ Teacher Status hiển thị: **Offline** (toggle tắt) + no room

## Luồng hoàn chỉnh

```
[Student Start Now]
    ↓
Teacher status = 'busy'
Teacher Status Block: Offline + có room link + toggle disabled
    ↓
[Teacher/Student Cancel hoặc Teacher Complete]
    ↓
- Gửi webhook xóa Discord room
- Xóa room metadata
- Set teacher status = '0' (offline)
- Reload page
    ↓
Teacher Status Block: Offline + no room + toggle enabled
```

## Files đã sửa

1. ✅ `includes/class-rest-api.php`
   - `ajax_update_session_status()` - 2 cases (cancel & complete)
   - `cancel_session()` - student cancel case

2. ✅ `blocks/session-history-block-frontend.js`
   - Alert messages updated

3. ✅ `CHANGELOG-SESSION-FLOW.md`
   - Documentation updated

## Testing Checklist

- [ ] Teacher cancel in_progress session → Status = Offline, no room
- [ ] Teacher complete in_progress session → Status = Offline, no room  
- [ ] Student cancel in_progress session → Teacher status = Offline, no room
- [ ] Alert messages show "Offline" instead of "Online"
- [ ] Teacher Status Block renders correctly after page reload

## Debug Logs Updated

```
TEACHER CANCEL IN_PROGRESS SESSION - Cleaned up room metadata and set status to offline for teacher: [teacher_id]

TEACHER COMPLETE IN_PROGRESS SESSION - Cleaned up room metadata and set status to offline for teacher: [teacher_id]

STUDENT CANCEL IN_PROGRESS SESSION - Cleaned up room metadata and set status to offline for teacher: [teacher_id]
```

## Notes

- Teacher status values:
  - `'1'` = Online (available)
  - `'busy'` = Đang trong buổi học
  - `'0'` hoặc empty = Offline

- Sau khi cancel hoặc complete, teacher về trạng thái offline (`'0'`), không phải online (`'1'`)
- Teacher phải tự bật lại trạng thái Online để nhận học viên mới

# Update: Redirect to Discord Room & Show Confirmed Sessions

## Thay đổi đã thực hiện

### 1. Thay đổi từ window.open sang window.location.href

**File: `blocks/teachers-block-frontend.js`**

#### Trước đây:
```javascript
if (response.success) {
    alert('Phiên học đã được tạo! Đang mở phòng học...');
    window.open(response.room_link, '_blank');  // Mở tab mới
    setTimeout(() => {
        location.reload();
    }, 1000);
}
```

#### Bây giờ:
```javascript
if (response.success) {
    alert('Phiên học đã được tạo! Đang chuyển đến phòng học...');
    window.location.href = response.room_link;  // Redirect trực tiếp
}
```

**Lý do:**
- Không mở tab mới nữa
- Redirect học viên trực tiếp đến phòng Discord
- Trải nghiệm liền mạch hơn

#### Áp dụng cho cả trường hợp có active session:
```javascript
if (response.has_active_session) {
    if (confirm(response.message)) {
        window.location.href = response.room_link;  // Redirect thay vì window.open
    }
}
```

### 2. Hiển thị Confirmed Sessions với Link tham gia

**File: `includes/class-rest-api.php` - Function `render_student_session_card()`**

#### Case 'confirmed':
```php
case 'confirmed':
    $status_text = 'Đã xác nhận';
    $status_class = 'confirmed';
    
    // Show join button with room link if available
    if (!empty($session->discord_channel)) {
        $actions = '<a href="' . esc_url($session->discord_channel) . '" class="dnd-btn dnd-btn-join">Tham gia ngay</a>';
    } else {
        $actions = '';
    }
    $actions .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '">Hủy</button>';
    break;
```

#### Thêm hiển thị Link phòng:
```php
$room_link_html = '';
if ($session->status === 'confirmed' && !empty($session->discord_channel)) {
    $room_link_html = '<div class="dnd-session-room-link">
        <strong>Link phòng:</strong> <a href="' . esc_url($session->discord_channel) . '" target="_blank">' . esc_html($session->discord_channel) . '</a>
    </div>';
}

return '
    <div class="dnd-session-card">
        ...
        ' . $room_link_html . '
        ...
    </div>
';
```

**File: `blocks/student-sessions-block.php` - Function `render_session_card()`**

Tương tự, đã cập nhật để hiển thị link phòng cho confirmed sessions.

### 3. Filter "Đã xác nhận" trong Student Sessions

**Query filter:**
```php
case 'confirmed':
    $where_clause .= " AND s.status IN ('confirmed', 'in_progress')";
    break;
```

Sessions với status = 'confirmed' sẽ hiển thị trong tab "Đã xác nhận".

## Flow hoàn chỉnh

### A. Học viên nhấn "Start Now"

1. **Confirm dialog**: "Bạn có muốn bắt đầu phiên học với [Teacher Name] ngay bây giờ không?"
2. **Loading**: "Đang kết nối..."
3. **API Call**: POST `/student/start-now`
4. **Success Response**:
   ```json
   {
     "success": true,
     "session_id": 123,
     "room_link": "https://discord.com/channels/SERVER_ID/CHANNEL_ID"
   }
   ```
5. **Redirect**: `window.location.href = response.room_link`
6. Học viên được chuyển trực tiếp đến Discord

### B. Xem session trong Student Sessions Block

1. Vào trang có block Student Sessions
2. Click vào tab "Đã xác nhận"
3. Thấy session vừa tạo với:
   - **Giáo viên**: [Teacher Name]
   - **Trạng thái**: Đã xác nhận
   - **Thời gian**: [Date/Time]
   - **Link phòng**: https://discord.com/channels/...
   - **Button**: "Tham gia ngay" (link trực tiếp)

4. Click "Tham gia ngay" → Redirect đến Discord room

## Database

Session được lưu với:
- `status`: 'confirmed'
- `discord_channel`: Full Discord room URL
- `session_date`: Current date
- `session_time`: Current time
- `start_time`: Current timestamp

## UI/UX Improvements

### Trước:
- Mở tab mới → Có thể bị popup blocker
- Phải tự reload trang để thấy session
- Session có thể không hiện ngay trong danh sách

### Bây giờ:
- ✅ Redirect trực tiếp → Không bị popup blocker
- ✅ Liền mạch, không cần reload
- ✅ Session hiện trong tab "Đã xác nhận" với link join
- ✅ Có thể quay lại trang sau và join lại bất cứ lúc nào

## Testing

### Test Case 1: Start Now thành công
1. Login học viên đã kết nối Discord
2. Click "Start Now" trên giáo viên online
3. Confirm dialog
4. **Expected**: Redirect đến Discord room

### Test Case 2: Xem Confirmed Session
1. Sau khi Start Now
2. Quay lại trang (back button hoặc navigate)
3. Vào Student Sessions block
4. Click tab "Đã xác nhận"
5. **Expected**: 
   - Thấy session vừa tạo
   - Có link phòng hiển thị
   - Có button "Tham gia ngay"

### Test Case 3: Join lại từ Student Sessions
1. Ở tab "Đã xác nhận"
2. Click "Tham gia ngay" trên session
3. **Expected**: Redirect đến Discord room

## Notes

- Link Discord channel format: `https://discord.com/channels/SERVER_ID/CHANNEL_ID`
- Sessions với `discord_channel` NULL sẽ không hiện button "Tham gia ngay"
- Có thể cancel session bất cứ lúc nào từ Student Sessions block

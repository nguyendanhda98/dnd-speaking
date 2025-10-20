# Fix Discord Auth URL - Test Case

## Vấn đề
Khi học viên chưa kết nối Discord và nhấn "Start Now", hệ thống redirect đến URL: `http://localhost:8642/luyen-noi-voi-giao-vien/null`

## Nguyên nhân
1. Function `get_discord_auth_url()` trả về `['url' => $auth_url]` thay vì `['auth_url' => $auth_url]`
2. Code frontend đang lấy `response.discord_auth_url` nhưng backend trả về key `url`

## Giải pháp đã áp dụng

### 1. Cập nhật `get_discord_auth_url()` trong `includes/class-rest-api.php`

**Thay đổi:**
- Ưu tiên sử dụng `dnd_discord_generated_url` từ settings (URL đã được cấu hình trong Admin)
- Trả về key `auth_url` thay vì `url`
- Fallback sang việc tạo URL động nếu Generated URL không có

```php
public function get_discord_auth_url($request) {
    // Use the generated URL from settings if available
    $generated_url = get_option('dnd_discord_generated_url');
    if ($generated_url) {
        return ['auth_url' => $generated_url];
    }
    
    // Fallback to generating URL...
    return ['auth_url' => $auth_url];
}
```

### 2. Cập nhật `student_start_now()` để xử lý response đúng cách

```php
if (!$discord_connected) {
    $auth_url_response = $this->get_discord_auth_url($request);
    $auth_url = '';
    
    if (!is_wp_error($auth_url_response) && isset($auth_url_response['auth_url'])) {
        $auth_url = $auth_url_response['auth_url'];
    }
    
    return new WP_REST_Response([
        'success' => false,
        'need_discord_connection' => true,
        'message' => 'Bạn cần liên kết tài khoản Discord để bắt đầu phiên học.',
        'discord_auth_url' => $auth_url
    ], 200);
}
```

## Test Cases

### Test 1: Discord Generated URL đã được cấu hình
**Setup:**
- Admin -> Discord Settings -> Application Details -> Generated URL: `https://discord.com/oauth2/authorize?client_id=...`

**Steps:**
1. Login với tài khoản học viên chưa kết nối Discord
2. Nhấn "Start Now" trên một giáo viên đang online
3. Nhấn "OK" trong confirm dialog

**Expected Result:**
- Redirect đến Discord OAuth URL đã cấu hình
- URL có dạng: `https://discord.com/oauth2/authorize?client_id=...&redirect_uri=...&scope=...`

### Test 2: Discord Generated URL chưa được cấu hình
**Setup:**
- Admin -> Discord Settings -> Application Details -> Generated URL: (để trống)
- Client ID và Redirect URI đã được cấu hình

**Steps:**
1. Login với tài khoản học viên chưa kết nối Discord
2. Nhấn "Start Now" trên một giáo viên đang online
3. Nhấn "OK" trong confirm dialog

**Expected Result:**
- Hệ thống tự động tạo Discord OAuth URL từ Client ID và Redirect URI
- Redirect đến URL được tạo động

### Test 3: Học viên đã kết nối Discord
**Setup:**
- Học viên đã kết nối Discord thành công

**Steps:**
1. Login với tài khoản học viên đã kết nối Discord
2. Nhấn "Start Now" trên một giáo viên đang online

**Expected Result:**
- Không hiển thị dialog yêu cầu kết nối Discord
- Tiếp tục flow tạo session và gọi webhook

## API Response Format

### Response khi cần kết nối Discord:
```json
{
  "success": false,
  "need_discord_connection": true,
  "message": "Bạn cần liên kết tài khoản Discord để bắt đầu phiên học.",
  "discord_auth_url": "https://discord.com/oauth2/authorize?client_id=..."
}
```

### Frontend xử lý:
```javascript
if (response.need_discord_connection) {
    if (confirm(response.message + '\n\nBạn có muốn kết nối Discord ngay bây giờ không?')) {
        window.location.href = response.discord_auth_url;
    }
}
```

## Checklist

- [x] Fix `get_discord_auth_url()` trả về key `auth_url`
- [x] Sử dụng `dnd_discord_generated_url` từ settings
- [x] Xử lý WP_Error trong `student_start_now()`
- [x] Đảm bảo `discord_auth_url` không bao giờ là `null`
- [x] Test với Generated URL có và không có

## Notes

- Generated URL trong Discord Developer Portal có dạng:
  ```
  https://discord.com/oauth2/authorize?client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_REDIRECT_URI&response_type=code&scope=identify+email+guilds+guilds.join+guilds.members.read+gdm.join
  ```
  
- Nếu Generated URL không có trong settings, hệ thống sẽ tự động tạo với:
  - `client_id` từ `dnd_discord_client_id`
  - `redirect_uri` từ `dnd_discord_redirect_page_full` hoặc `dnd_discord_redirect_page`
  - `scope` đã được định nghĩa sẵn trong code

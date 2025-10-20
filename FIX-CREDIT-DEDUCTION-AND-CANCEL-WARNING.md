# Fix: Credit Deduction & Cancel Warning

## Ngày: 20/10/2025

## Các lỗi đã sửa

### 1. Lỗi không trừ buổi học khi giáo viên chấp nhận (Accept)

**Vấn đề:**
- Học viên book lịch → Giáo viên Accept → **Không trừ buổi học**

**Nguyên nhân:**
- Function `deduct_user_credits()` có thể fail im lặng nếu:
  - User không tồn tại trong bảng credits
  - Database update error không được log
  
**Giải pháp:**

**File: `includes/class-helpers.php`**

Cải thiện function `deduct_user_credits()`:

```php
public static function deduct_user_credits($user_id, $amount = 1) {
    global $wpdb;
    $table = $wpdb->prefix . 'dnd_speaking_credits';
    $current_credits = self::get_user_credits($user_id);
    
    if ($current_credits < $amount) {
        error_log("CREDIT DEDUCTION FAILED - User {$user_id} has {$current_credits} credits, needs {$amount}");
        return false;
    }
    
    $new_credits = $current_credits - $amount;
    
    // Check if user exists in credits table
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d", $user_id));
    
    if ($exists > 0) {
        $result = $wpdb->update($table, ['credits' => $new_credits], ['user_id' => $user_id], ['%d'], ['%d']);
    } else {
        // User doesn't exist, insert with 0 credits (shouldn't happen but handle it)
        $result = $wpdb->insert($table, ['user_id' => $user_id, 'credits' => 0], ['%d', '%d']);
        error_log("CREDIT DEDUCTION - User {$user_id} not found in credits table, created with 0 credits");
        return false;
    }
    
    if ($result === false) {
        error_log("CREDIT DEDUCTION FAILED - Database error for user {$user_id}: " . $wpdb->last_error);
        return false;
    }
    
    // Log the deduction
    self::log_action($user_id, 'credit_deducted', "Deducted {$amount} credit(s). Balance: {$new_credits}");
    error_log("CREDIT DEDUCTED - User {$user_id}: -{$amount} credit(s), new balance: {$new_credits}");
    
    return true;
}
```

**Cải tiến:**
- ✅ Kiểm tra user có tồn tại trong bảng credits
- ✅ Log chi tiết mọi bước (success/fail)
- ✅ Xử lý trường hợp database error
- ✅ Return false rõ ràng khi fail

---

### 2. Lỗi không hiển thị cảnh báo khi hủy < 24h

**Vấn đề:**
- Học viên hủy buổi học confirmed < 24h trước giờ học
- **Không có cảnh báo** về việc sẽ không được hoàn tiền

**Yêu cầu:**
Hiển thị cảnh báo: 
> "Buổi học sẽ diễn ra trong vòng 24 giờ nữa, nên bạn sẽ KHÔNG được hoàn lại buổi học nếu hủy. Bạn có chắc muốn tiếp tục hủy?"

**Giải pháp:**

#### A. Backend - Trả về thông tin refund

**File: `includes/class-rest-api.php` - Function `cancel_session()`**

1. **Tracking thông tin session:**
```php
// Check if this is a future confirmed/pending session and if cancelled more than 24 hours before
$should_refund = false;
$hours_until_session = 0;
$is_confirmed_session = false;

if (in_array($session->status, ['confirmed', 'pending']) && !empty($session->start_time)) {
    $session_timestamp = strtotime($session->start_time);
    $current_timestamp = current_time('timestamp');
    $hours_until_session = ($session_timestamp - $current_timestamp) / 3600;
    $is_confirmed_session = ($session->status === 'confirmed');
    
    if ($hours_until_session > 24) {
        $should_refund = true;
        error_log('STUDENT CANCEL - Session is more than 24 hours away (' . round($hours_until_session, 2) . ' hours). Will refund credit.');
    } else {
        error_log('STUDENT CANCEL - Session is less than 24 hours away (' . round($hours_until_session, 2) . ' hours). No refund.');
    }
}
```

2. **Response với thông tin refund:**
```php
return [
    'success' => true,
    'refunded' => $refunded,
    'message' => $refunded ? 'Đã hủy buổi học và hoàn lại 1 buổi.' : 'Đã hủy buổi học.'
];
```

#### B. Frontend - Hiển thị cảnh báo

**File: `blocks/student-sessions-block.php`**

Thêm data attributes vào button cancel:
```php
// Calculate session timestamp for cancel warning
$session_timestamp = '';
if (!empty($session->start_time)) {
    $session_timestamp = strtotime($session->start_time);
} else if (!empty($session->session_date) && !empty($session->session_time)) {
    $session_timestamp = strtotime($session->session_date . ' ' . $session->session_time);
}

// In buttons:
data-session-time="' . $session_timestamp . '" data-session-status="confirmed"
```

**File: `blocks/student-sessions-block-frontend.js`**

Logic kiểm tra và cảnh báo:
```javascript
$sessionsBlock.on('click', '.dnd-btn-cancel', function() {
    const $button = $(this);
    const sessionId = $button.data('session-id');
    const sessionTime = $button.data('session-time');
    const sessionStatus = $button.data('session-status');
    
    // Check if this is a confirmed session and if it's within 24 hours
    let confirmMessage = 'Bạn có chắc muốn hủy buổi học này?';
    let willRefund = true;
    
    if (sessionStatus === 'confirmed' && sessionTime) {
        const now = Math.floor(Date.now() / 1000); // Current timestamp in seconds
        const hoursUntilSession = (sessionTime - now) / 3600;
        
        if (hoursUntilSession <= 24 && hoursUntilSession > 0) {
            willRefund = false;
            confirmMessage = 'Buổi học sẽ diễn ra trong vòng 24 giờ nữa, nên bạn sẽ KHÔNG được hoàn lại buổi học nếu hủy.\n\nBạn có chắc muốn tiếp tục hủy?';
        } else if (hoursUntilSession > 24) {
            confirmMessage = 'Buổi học còn hơn 24 giờ nữa, bạn sẽ được hoàn lại 1 buổi học.\n\nBạn có chắc muốn hủy?';
        }
    }
    
    if (confirm(confirmMessage)) {
        // Disable button and show loading
        $button.prop('disabled', true).text('Đang xử lý...');
        cancelSession(sessionId, $button);
    }
});
```

Hiển thị message từ server sau khi hủy:
```javascript
function cancelSession(sessionId, $button) {
    $.ajax({
        url: dnd_speaking_data.rest_url + 'cancel-session',
        method: 'POST',
        headers: {
            'X-WP-Nonce': dnd_speaking_data.nonce
        },
        data: {
            session_id: sessionId
        },
        success: function(response) {
            if (response.success) {
                // Show success message
                if (response.message) {
                    alert(response.message);
                }
                loadSessions(); // Reload after cancel
            }
            // ... error handling
        }
    });
}
```

---

## Files đã sửa

1. ✅ `includes/class-helpers.php`
   - Cải thiện `deduct_user_credits()` với error logging
   
2. ✅ `includes/class-rest-api.php`
   - Thêm tracking `hours_until_session` và `is_confirmed_session`
   - Return message chi tiết về refund
   
3. ✅ `blocks/student-sessions-block.php`
   - Thêm `data-session-time` và `data-session-status` vào button cancel
   
4. ✅ `blocks/student-sessions-block-frontend.js`
   - Logic kiểm tra 24h trước khi confirm
   - Hiển thị cảnh báo rõ ràng
   - Show message từ server

---

## Testing

### Test Case 1: Credit deduction khi teacher accept

1. Admin cấp credits cho học viên (ví dụ: 5 buổi)
2. Học viên book lịch
3. Giáo viên accept
4. **Expected:** 
   - Học viên bị trừ 1 buổi (còn 4 buổi)
   - Log trong error_log: `CREDIT DEDUCTED - User X: -1 credit(s), new balance: 4`

### Test Case 2: Hủy session > 24h (Có hoàn tiền)

1. Học viên có session confirmed, thời gian học còn > 24h
2. Click "Hủy"
3. **Expected:**
   - Cảnh báo: "Buổi học còn hơn 24 giờ nữa, bạn sẽ được hoàn lại 1 buổi học. Bạn có chắc muốn hủy?"
   - Sau khi confirm: "Đã hủy buổi học và hoàn lại 1 buổi."
   - Học viên được hoàn 1 buổi

### Test Case 3: Hủy session < 24h (Không hoàn tiền)

1. Học viên có session confirmed, thời gian học còn < 24h
2. Click "Hủy"
3. **Expected:**
   - Cảnh báo: "Buổi học sẽ diễn ra trong vòng 24 giờ nữa, nên bạn sẽ KHÔNG được hoàn lại buổi học nếu hủy. Bạn có chắc muốn tiếp tục hủy?"
   - Sau khi confirm: "Đã hủy buổi học."
   - Học viên KHÔNG được hoàn buổi

### Test Case 4: Hủy session pending

1. Học viên có session pending
2. Click "Hủy"
3. **Expected:**
   - Cảnh báo đơn giản: "Bạn có chắc muốn hủy buổi học này?"
   - Session bị hủy (không có hoàn tiền vì chưa bị trừ)

---

## Notes

- ✅ Logging đầy đủ cho debug
- ✅ Xử lý edge cases (user không tồn tại, DB error)
- ✅ UX rõ ràng với cảnh báo chi tiết
- ✅ Message feedback sau khi hủy
- ✅ Tính toán chính xác 24h bằng timestamp

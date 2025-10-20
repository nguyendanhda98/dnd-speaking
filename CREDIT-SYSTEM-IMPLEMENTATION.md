# Credit System Implementation - DND Speaking Plugin

## Tổng quan
Hệ thống quản lý buổi học (credits) cho học viên với các quy tắc trừ và hoàn trả rõ ràng.

## Quy tắc trừ buổi học

### 1. Student Start Now
**Khi nào trừ:** Khi học viên click "Start Now" và nhận được link phòng thành công từ Discord webhook.

**Số buổi trừ:** 1 buổi

**Flow:**
1. Học viên click "Start Now" trên teacher card
2. Backend kiểm tra điều kiện (Discord connected, teacher available, etc.)
3. Gửi webhook đến Discord bot để tạo phòng
4. Webhook trả về success với room_link
5. **Trừ 1 buổi học** (credit deduction)
6. Tạo session với status = 'in_progress'
7. Lưu room_link vào database
8. Redirect học viên đến Discord room

**Code location:** `includes/class-rest-api.php` - function `student_start_now()`

```php
// Deduct credit after successfully getting room link
if (!DND_Speaking_Helpers::deduct_user_credits($user_id)) {
    return new WP_Error('insufficient_credits', 'Không đủ buổi học để tham gia', ['status' => 400]);
}
```

---

### 2. Book Session → Teacher Confirm
**Khi nào trừ:** Khi giáo viên xác nhận (confirm) buổi học đã được student book.

**Số buổi trừ:** 1 buổi

**Flow:**
1. Học viên book lịch → Tạo session với status = 'pending'
2. **KHÔNG** trừ buổi học lúc này
3. Giáo viên xem session request
4. Giáo viên click "Xác nhận" (Accept)
5. **Trừ 1 buổi học** của học viên
6. Session status chuyển sang 'confirmed'
7. Nếu không đủ buổi học → Rollback về 'pending', thông báo lỗi

**Code location:** `includes/class-admin.php` - function `handle_teacher_request()`

```php
// If accepted, deduct credits from student
if ($action === 'accept') {
    $student_id = $session->student_id;
    if (!DND_Speaking_Helpers::deduct_user_credits($student_id)) {
        // Rollback session confirmation if credit deduction fails
        $wpdb->update($sessions_table, ['status' => 'pending'], ['id' => $session_id]);
        wp_send_json_error('Student does not have enough credits');
    }
}
```

---

## Quy tắc hoàn trả buổi học

### 1. Giáo viên huỷ buổi học (Teacher Cancel)
**Khi nào hoàn:** Bất kỳ khi nào giáo viên huỷ session có status = 'confirmed' hoặc 'in_progress'.

**Số buổi hoàn:** 1 buổi

**Scenarios:**

#### A. Teacher cancel confirmed session (chưa bắt đầu)
- Session status = 'confirmed'
- Giáo viên click "Cancel" trong upcoming sessions
- Hoàn 1 buổi học cho học viên
- Session status → 'cancelled'

**Code location:** `includes/class-admin.php` - function `handle_upcoming_session()`

```php
// Refund credits to student when teacher cancels
$student_id = $session->student_id;
DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled session');
```

#### B. Teacher cancel in-progress session (đang học)
- Session status = 'in_progress'
- Giáo viên click "Hủy buổi học" trong session history
- Gửi webhook xóa Discord room
- Hoàn 1 buổi học cho học viên
- Reset teacher status về offline
- Session status → 'cancelled'

**Code location:** `includes/class-rest-api.php` - function `ajax_update_session_status()`

```php
// Refund credits to student when teacher cancels
$student_id = $session->student_id;
DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled in-progress session');
```

---

### 2. Học viên huỷ trước 24 giờ (Student Cancel Early)
**Khi nào hoàn:** Khi học viên huỷ session có status = 'confirmed' và thời gian còn lại > 24 giờ.

**Số buổi hoàn:** 1 buổi

**Flow:**
1. Học viên click "Hủy" trên session trong Student Sessions block
2. Backend kiểm tra session status = 'confirmed'
3. Tính thời gian từ hiện tại đến `start_time` của session
4. Nếu > 24 giờ → **Hoàn 1 buổi học**
5. Nếu ≤ 24 giờ → **KHÔNG hoàn** buổi học
6. Session status → 'cancelled'

**Code location:** `includes/class-rest-api.php` - function `cancel_session()`

```php
// Check if cancelled more than 24 hours before
$should_refund = false;
if (in_array($session->status, ['confirmed', 'pending']) && !empty($session->start_time)) {
    $session_timestamp = strtotime($session->start_time);
    $current_timestamp = current_time('timestamp');
    $hours_until_session = ($session_timestamp - $current_timestamp) / 3600;
    
    if ($hours_until_session > 24) {
        $should_refund = true;
    }
}

// Refund if eligible
if ($should_refund && $session->status === 'confirmed') {
    DND_Speaking_Helpers::refund_user_credits($user_id, 1, 'Student cancelled more than 24 hours before session');
}
```

---

## Trường hợp KHÔNG hoàn buổi học

### 1. Học viên huỷ session đang diễn ra (in_progress)
- Student đã bắt đầu buổi học (Start Now)
- Nếu student cancel → **KHÔNG hoàn** buổi học
- Lý do: Student đã tham gia phòng học

### 2. Học viên huỷ trong vòng 24 giờ trước buổi học
- Session status = 'confirmed'
- Thời gian còn lại ≤ 24 giờ
- Student cancel → **KHÔNG hoàn** buổi học
- Lý do: Quá gần giờ học

### 3. Giáo viên từ chối (decline) session pending
- Session status = 'pending'
- Teacher click "Từ chối"
- **KHÔNG cần hoàn** buổi học
- Lý do: Chưa trừ buổi học (chỉ trừ khi teacher accept)

### 4. Student cancel session pending
- Session status = 'pending'
- Student cancel
- **KHÔNG cần hoàn** buổi học
- Lý do: Chưa trừ buổi học

---

## Helper Functions

### deduct_user_credits($user_id, $amount = 1)
**File:** `includes/class-helpers.php`

Trừ buổi học từ tài khoản student.

**Returns:**
- `true` nếu thành công (và đủ buổi học)
- `false` nếu không đủ buổi học

**Features:**
- Kiểm tra số buổi học hiện tại
- Cập nhật database
- Ghi log action

```php
public static function deduct_user_credits($user_id, $amount = 1) {
    global $wpdb;
    $table = $wpdb->prefix . 'dnd_speaking_credits';
    $current_credits = self::get_user_credits($user_id);
    
    if ($current_credits < $amount) {
        return false;
    }
    
    $new_credits = $current_credits - $amount;
    $wpdb->update($table, ['credits' => $new_credits], ['user_id' => $user_id]);
    self::log_action($user_id, 'credit_deducted', "Deducted {$amount} credit(s). Balance: {$new_credits}");
    
    return true;
}
```

---

### refund_user_credits($user_id, $amount = 1, $reason = '')
**File:** `includes/class-helpers.php`

Hoàn trả buổi học cho student.

**Parameters:**
- `$user_id`: WordPress user ID của student
- `$amount`: Số buổi học cần hoàn (default = 1)
- `$reason`: Lý do hoàn trả (cho logging)

**Features:**
- Tự động tạo record nếu user chưa có trong bảng credits
- Cập nhật database
- Ghi log action với reason

```php
public static function refund_user_credits($user_id, $amount = 1, $reason = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'dnd_speaking_credits';
    $current_credits = self::get_user_credits($user_id);
    $new_credits = $current_credits + $amount;
    
    if ($current_credits > 0) {
        $wpdb->update($table, ['credits' => $new_credits], ['user_id' => $user_id]);
    } else {
        $wpdb->insert($table, ['user_id' => $user_id, 'credits' => $amount]);
    }
    
    $log_message = "Refunded {$amount} credit(s). Balance: {$new_credits}";
    if ($reason) {
        $log_message .= " Reason: {$reason}";
    }
    self::log_action($user_id, 'credit_refunded', $log_message);
    
    return true;
}
```

---

## Testing Checklist

### Test Case 1: Start Now - Successful
1. Student có 5 buổi học
2. Click "Start Now" với teacher online
3. Webhook success, nhận được room link
4. **Expected:** Trừ 1 buổi → còn 4 buổi
5. Session được tạo với status = 'in_progress'

### Test Case 2: Start Now - Insufficient Credits
1. Student có 0 buổi học
2. Click "Start Now"
3. Webhook success, nhận được room link
4. **Expected:** Error "Không đủ buổi học để tham gia"
5. Không tạo session

### Test Case 3: Book Session - Teacher Accept
1. Student có 5 buổi học, book lịch
2. Session tạo với status = 'pending'
3. **Expected:** Vẫn còn 5 buổi (chưa trừ)
4. Teacher click "Xác nhận"
5. **Expected:** Trừ 1 buổi → còn 4 buổi
6. Session status = 'confirmed'

### Test Case 4: Book Session - Teacher Decline
1. Student có 5 buổi học, book lịch
2. Session tạo với status = 'pending'
3. Teacher click "Từ chối"
4. **Expected:** Vẫn còn 5 buổi (không trừ, không hoàn)
5. Session status = 'declined'

### Test Case 5: Teacher Cancel Confirmed Session
1. Student có 4 buổi học
2. Session status = 'confirmed' (đã trừ 1 buổi)
3. Teacher cancel session
4. **Expected:** Hoàn 1 buổi → còn 5 buổi
5. Session status = 'cancelled'

### Test Case 6: Teacher Cancel In-Progress Session
1. Student có 4 buổi học
2. Session status = 'in_progress' (Start Now, đã trừ 1 buổi)
3. Teacher cancel session
4. **Expected:** Hoàn 1 buổi → còn 5 buổi
5. Discord room bị xóa
6. Teacher status = offline
7. Session status = 'cancelled'

### Test Case 7: Student Cancel > 24h Before
1. Student có 4 buổi học
2. Session confirmed, start_time = 2 ngày sau
3. Student cancel session
4. **Expected:** Hoàn 1 buổi → còn 5 buổi
5. Session status = 'cancelled'

### Test Case 8: Student Cancel < 24h Before
1. Student có 4 buổi học
2. Session confirmed, start_time = 10 giờ sau (< 24h)
3. Student cancel session
4. **Expected:** KHÔNG hoàn → vẫn còn 4 buổi
5. Session status = 'cancelled'

### Test Case 9: Student Cancel In-Progress
1. Student có 4 buổi học
2. Session status = 'in_progress'
3. Student cancel session
4. **Expected:** KHÔNG hoàn → vẫn còn 4 buổi
5. Discord room bị xóa
6. Teacher status = offline
7. Session status = 'cancelled'

### Test Case 10: Student Cancel Pending
1. Student có 5 buổi học
2. Session status = 'pending' (chưa teacher confirm)
3. Student cancel session
4. **Expected:** KHÔNG thay đổi → vẫn còn 5 buổi (vì chưa trừ)
5. Session status = 'cancelled'

---

## Database Schema

### Table: wp_dnd_speaking_credits
```sql
CREATE TABLE wp_dnd_speaking_credits (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    credits int(11) DEFAULT 0,
    PRIMARY KEY (id)
)
```

### Table: wp_dnd_speaking_logs
```sql
CREATE TABLE wp_dnd_speaking_logs (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    action varchar(255) NOT NULL,
    details text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
)
```

**Log Actions:**
- `credit_deducted` - Khi trừ buổi học
- `credit_refunded` - Khi hoàn buổi học
- `credit_added` - Khi admin thêm buổi học

---

## Files Modified

1. **includes/class-helpers.php**
   - Implemented `deduct_user_credits()` function
   - Added `refund_user_credits()` function

2. **includes/class-rest-api.php**
   - `student_start_now()`: Added credit deduction after successful webhook
   - `book_session()`: Removed credit deduction (moved to teacher confirm)
   - `cancel_session()`: Added refund logic for student cancellation with 24h rule
   - `ajax_update_session_status()`: Added refund for teacher cancellation of confirmed/in-progress sessions

3. **includes/class-admin.php**
   - `handle_teacher_request()`: Added credit deduction on teacher accept
   - `handle_upcoming_session()`: Added refund on teacher cancel

---

## Notes

- Tất cả các thay đổi credit đều được log vào bảng `wp_dnd_speaking_logs`
- Timezone mặc định: Asia/Ho_Chi_Minh
- Credit system có thể dễ dàng mở rộng cho các tính năng khác
- Rollback mechanism đảm bảo data consistency khi credit deduction fails

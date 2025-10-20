# Update: Credit System Flow Changes

## Ngày: 20/10/2025

## Yêu cầu thay đổi

### 1. Book lịch → Trừ buổi ngay lập tức
- **CŨ:** Học viên book → Chờ teacher confirm → Trừ buổi khi confirm
- **MỚI:** Học viên book → **Trừ buổi ngay** → Teacher confirm (không trừ thêm)

### 2. Teacher hủy → Luôn hoàn tiền
- **CŨ:** Teacher cancel có thể không hoàn tiền trong một số trường hợp
- **MỚI:** Teacher hủy **BẤT KỲ** session nào → **Luôn hoàn 1 buổi**

---

## Các thay đổi đã thực hiện

### ✅ 1. Book Session - Trừ credit ngay lập tức

**File: `includes/class-rest-api.php`**

**Function: `book_session()`**

```php
// Check if slot is still available
if ($existing > 0) {
    return new WP_Error('slot_taken', 'This time slot is no longer available', ['status' => 400]);
}

// Deduct credits immediately when booking
if (!DND_Speaking_Helpers::deduct_user_credits($student_id, 1)) {
    return new WP_Error('insufficient_credits', 'Không đủ buổi học', ['status' => 400]);
}

// Book the session
$insert_data = [
    'student_id' => $student_id,
    'teacher_id' => $teacher_id,
    'start_time' => $start_time,
    'status' => 'pending'
];
```

**Thay đổi:**
- ✅ Trừ credit NGAY khi học viên book
- ✅ Nếu không đủ credit → Return error, không tạo session
- ✅ Session được tạo với status = 'pending'

---

### ✅ 2. Teacher Confirm - Không trừ credit

**File: `includes/class-rest-api.php`**

**Function: `ajax_update_session_status()`**

**CŨ:**
```php
// Case 1.6: Confirming a pending session - DEDUCT CREDIT
if ($new_status === 'confirmed' && $session->status === 'pending') {
    $student_id = $session->student_id;
    if (!DND_Speaking_Helpers::deduct_user_credits($student_id, 1)) {
        wp_send_json_error('Học viên không đủ buổi học để xác nhận');
        return;
    }
}
```

**MỚI:**
```php
// Case 1.6: Teacher confirms pending session - NO CREDIT DEDUCTION
// Credits are already deducted when student books the session
// Just update the status, no need to deduct again
```

**File: `includes/class-admin.php`**

**Function: `handle_teacher_request()`**

**MỚI:**
```php
// Credits are already deducted when student books
// If accepted, no need to deduct again
// If declined, refund the credits
if ($action === 'decline') {
    $student_id = $session->student_id;
    DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher declined session');
}
```

**Thay đổi:**
- ✅ Xóa logic trừ credit khi teacher confirm
- ✅ **Thêm:** Teacher decline → Hoàn lại credit

---

### ✅ 3. Teacher Cancel - Luôn hoàn tiền

**File: `includes/class-rest-api.php`**

**Function: `ajax_update_session_status()`**

Thêm 3 cases:

```php
// Case 1: Teacher cancels in_progress session - ALWAYS refund
if ($new_status === 'cancelled' && $session->status === 'in_progress' && !empty($session->discord_channel)) {
    // ... webhook cleanup ...
    
    // Teacher cancels in-progress session - ALWAYS refund to student
    $student_id = $session->student_id;
    DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled in-progress session');
    error_log('TEACHER CANCEL IN_PROGRESS - Refunded credit to student: ' . $student_id);
}

// Case 1.5: Teacher cancels confirmed session - ALWAYS refund
if ($new_status === 'cancelled' && $session->status === 'confirmed') {
    $student_id = $session->student_id;
    DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled confirmed session');
    error_log('TEACHER CANCEL CONFIRMED - Refunded credit to student: ' . $student_id);
}

// Case 1.6: Teacher cancels pending session - ALWAYS refund
if ($new_status === 'cancelled' && $session->status === 'pending') {
    $student_id = $session->student_id;
    DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled/declined pending session');
    error_log('TEACHER CANCEL PENDING - Refunded credit to student: ' . $student_id);
}
```

**File: `includes/class-admin.php`**

**Function: `handle_upcoming_session()`**

```php
// Already has refund logic
$student_id = $session->student_id;
DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled session');
```

**Thay đổi:**
- ✅ Teacher cancel **pending** → Hoàn tiền
- ✅ Teacher cancel **confirmed** → Hoàn tiền
- ✅ Teacher cancel **in_progress** → Hoàn tiền
- ✅ Teacher **decline** (trong handle_teacher_request) → Hoàn tiền

---

### ✅ 4. Student Cancel - Cập nhật logic

**File: `includes/class-rest-api.php`**

**Function: `cancel_session()`**

```php
// Refund credit logic (since credit was deducted when booking):
// - Pending: Always refund (teacher hasn't accepted yet)
// - Confirmed/In-progress: Refund only if > 24 hours before session
$refunded = false;

if ($session->status === 'pending') {
    // Pending sessions - always refund since teacher hasn't confirmed yet
    DND_Speaking_Helpers::refund_user_credits($user_id, 1, 'Student cancelled pending session');
    $refunded = true;
    error_log('STUDENT CANCEL PENDING - Refunded credit to student: ' . $user_id);
} else if ($should_refund && in_array($session->status, ['confirmed', 'in_progress'])) {
    // Confirmed/In-progress sessions - refund only if > 24 hours before
    DND_Speaking_Helpers::refund_user_credits($user_id, 1, 'Student cancelled more than 24 hours before session');
    $refunded = true;
    error_log('STUDENT CANCEL >24H - Refunded credit to student: ' . $user_id);
} else if (!$should_refund && in_array($session->status, ['confirmed', 'in_progress'])) {
    error_log('STUDENT CANCEL <24H - No refund for student: ' . $user_id);
}
```

**Thay đổi:**
- ✅ **Pending:** Luôn hoàn tiền (vì teacher chưa confirm)
- ✅ **Confirmed > 24h:** Hoàn tiền
- ✅ **Confirmed < 24h:** KHÔNG hoàn
- ✅ **In-progress > 24h:** Hoàn tiền
- ✅ **In-progress < 24h:** KHÔNG hoàn

---

### ✅ 5. Frontend - Cập nhật cảnh báo

**File: `blocks/student-sessions-block-frontend.js`**

```javascript
if (sessionStatus === 'pending') {
    confirmMessage = 'Buổi học chưa được giáo viên xác nhận, bạn sẽ được hoàn lại 1 buổi học.\n\nBạn có chắc muốn hủy?';
} else if (sessionStatus === 'confirmed' && sessionTime) {
    const now = Math.floor(Date.now() / 1000);
    const hoursUntilSession = (sessionTime - now) / 3600;
    
    if (hoursUntilSession <= 24 && hoursUntilSession > 0) {
        confirmMessage = 'Buổi học sẽ diễn ra trong vòng 24 giờ nữa, nên bạn sẽ KHÔNG được hoàn lại buổi học nếu hủy.\n\nBạn có chắc muốn tiếp tục hủy?';
    } else if (hoursUntilSession > 24) {
        confirmMessage = 'Buổi học còn hơn 24 giờ nữa, bạn sẽ được hoàn lại 1 buổi học.\n\nBạn có chắc muốn hủy?';
    }
} else if (sessionStatus === 'in_progress' && sessionTime) {
    // Similar logic for in_progress
}
```

**Thay đổi:**
- ✅ Thêm message riêng cho **pending** session
- ✅ Giữ nguyên logic 24h cho **confirmed/in_progress**

---

## Flow mới - Tổng kết

### 📘 Scenario 1: Học viên book lịch thành công

```
1. Học viên có 5 buổi
2. Book lịch → Trừ ngay 1 buổi → Còn 4 buổi
3. Session status = 'pending'
4. Teacher confirm → Session status = 'confirmed' (không trừ thêm)
5. Học viên vẫn còn 4 buổi
```

### 📘 Scenario 2: Teacher decline/cancel session

```
Teacher decline pending session:
→ Hoàn 1 buổi cho học viên

Teacher cancel confirmed session:
→ Hoàn 1 buổi cho học viên

Teacher cancel in-progress session:
→ Hoàn 1 buổi cho học viên
```

**Kết luận:** Teacher hủy **BẤT KỲ LÚC NÀO** → Học viên **LUÔN ĐƯỢC HOÀN**

### 📘 Scenario 3: Student cancel session

```
Student cancel PENDING:
→ Hoàn 1 buổi (vì teacher chưa confirm)

Student cancel CONFIRMED/IN-PROGRESS:
→ Kiểm tra thời gian:
  - > 24h: Hoàn 1 buổi
  - ≤ 24h: KHÔNG hoàn
```

---

## Files đã sửa

1. ✅ `includes/class-rest-api.php`
   - `book_session()`: Thêm trừ credit
   - `ajax_update_session_status()`: Xóa trừ credit khi confirm, thêm hoàn tiền cho mọi teacher cancel
   - `cancel_session()`: Cập nhật logic student cancel

2. ✅ `includes/class-admin.php`
   - `handle_teacher_request()`: Xóa trừ credit khi accept, thêm hoàn tiền khi decline

3. ✅ `blocks/student-sessions-block-frontend.js`
   - Thêm message cho pending cancel
   - Giữ logic 24h cho confirmed/in-progress

---

## Testing Scenarios

### Test 1: Book session ✅
```
Given: Học viên có 3 buổi
When: Book lịch với teacher
Then: 
  - Session created với status = 'pending'
  - Học viên còn 2 buổi
  - Log: "CREDIT DEDUCTED - User X: -1 credit(s), new balance: 2"
```

### Test 2: Teacher confirm ✅
```
Given: Session pending
When: Teacher click "Xác nhận"
Then:
  - Session status = 'confirmed'
  - Học viên vẫn còn 2 buổi (không trừ thêm)
  - NO log about credit deduction
```

### Test 3: Teacher decline ✅
```
Given: Session pending, học viên có 2 buổi
When: Teacher click "Từ chối"
Then:
  - Session status = 'declined'
  - Học viên có 3 buổi (hoàn lại)
  - Log: "CREDIT REFUNDED - ... Reason: Teacher declined session"
```

### Test 4: Teacher cancel confirmed ✅
```
Given: Session confirmed, học viên có 2 buổi
When: Teacher cancel session
Then:
  - Session status = 'cancelled'
  - Học viên có 3 buổi (hoàn lại)
  - Log: "TEACHER CANCEL CONFIRMED - Refunded 1 credit to student"
```

### Test 5: Teacher cancel in-progress ✅
```
Given: Session in-progress, học viên có 2 buổi
When: Teacher cancel session
Then:
  - Discord room deleted
  - Session status = 'cancelled'
  - Học viên có 3 buổi (hoàn lại)
  - Log: "TEACHER CANCEL IN_PROGRESS - Refunded credit to student"
```

### Test 6: Student cancel pending ✅
```
Given: Session pending, học viên có 2 buổi
When: Student click "Hủy"
Then:
  - Alert: "Buổi học chưa được giáo viên xác nhận, bạn sẽ được hoàn lại 1 buổi học"
  - Session cancelled
  - Học viên có 3 buổi (hoàn lại)
  - Log: "STUDENT CANCEL PENDING - Refunded credit"
```

### Test 7: Student cancel confirmed > 24h ✅
```
Given: Session confirmed ngày mai, học viên có 2 buổi
When: Student click "Hủy"
Then:
  - Alert: "Buổi học còn hơn 24 giờ nữa, bạn sẽ được hoàn lại 1 buổi học"
  - Session cancelled
  - Học viên có 3 buổi (hoàn lại)
  - Alert sau: "Đã hủy buổi học và hoàn lại 1 buổi."
```

### Test 8: Student cancel confirmed < 24h ⚠️
```
Given: Session hôm nay lúc 20:00, hiện tại 10:00, học viên có 2 buổi
When: Student click "Hủy"
Then:
  - Alert: "Buổi học sẽ diễn ra trong vòng 24 giờ nữa, nên bạn sẽ KHÔNG được hoàn lại buổi học nếu hủy"
  - Session cancelled
  - Học viên vẫn có 2 buổi (KHÔNG hoàn)
  - Alert sau: "Đã hủy buổi học."
```

---

## Summary

✅ **Book → Trừ ngay:** Đơn giản hóa flow, không phải đợi teacher  
✅ **Teacher cancel → Luôn hoàn:** Fair cho học viên  
✅ **Student cancel:** Linh hoạt theo status và thời gian  
✅ **Logging đầy đủ:** Dễ debug và track  

🎉 **HỆ THỐNG CREDIT ĐÃ CÂN BẰNG VÀ CÔNG BẰNG!**

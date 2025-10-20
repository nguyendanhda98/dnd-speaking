# Fix Refund Logic - Final Implementation

**Date:** October 20, 2025  
**Summary:** Updated credit refund logic to match new business requirements

---

## New Business Rules

### 1. Học viên huỷ buổi học
| Trạng thái | Điều kiện | Hoàn tiền? | Lý do |
|-----------|----------|-----------|-------|
| **Pending** | Bất kỳ lúc nào | ✅ Có | Giáo viên chưa accept |
| **Confirmed** | >24h trước buổi học | ✅ Có | Còn đủ thời gian |
| **Confirmed** | ≤24h trước buổi học | ❌ Không | Quá gần giờ học |
| **In-progress** | Bất kỳ lúc nào | ❌ Không | Đã tham gia rồi |

### 2. Giáo viên huỷ buổi học
| Trạng thái | Điều kiện | Hoàn tiền? | Lý do |
|-----------|----------|-----------|-------|
| **Pending** | Bất kỳ lúc nào | ✅ Có | Lỗi từ giáo viên |
| **Confirmed** | Bất kỳ lúc nào | ✅ Có | Lỗi từ giáo viên |
| **In-progress** | Bất kỳ lúc nào | ✅ Có | Lỗi từ giáo viên |

### 3. Credit Check
- **Book Session:** Check credits và trừ ngay khi book
- **Start Now:** Check credits trước khi gọi webhook Discord
- **Return Error:** Nếu không đủ buổi → trả lỗi `insufficient_credits` ngay lập tức

---

## Code Changes

### 1. Backend - `includes/class-rest-api.php`

#### Function: `cancel_session()` (Student Cancel)

**OLD LOGIC:**
```php
// Confirmed/In-progress sessions - refund only if > 24 hours before
if ($should_refund && in_array($session->status, ['confirmed', 'in_progress'])) {
    DND_Speaking_Helpers::refund_user_credits($user_id, 1, 'Student cancelled more than 24 hours before session');
    $refunded = true;
}
```

**NEW LOGIC:**
```php
// NEW LOGIC:
// - Pending: Always refund (teacher hasn't accepted yet)
// - Confirmed: Refund only if > 24 hours before session
// - In-progress: NEVER refund (student already joined the session)

if ($session->status === 'pending') {
    // Pending sessions - always refund since teacher hasn't confirmed yet
    DND_Speaking_Helpers::refund_user_credits($user_id, 1, 'Student cancelled pending session');
    $refunded = true;
    error_log('STUDENT CANCEL PENDING - Refunded credit to student: ' . $user_id);
} else if ($session->status === 'confirmed') {
    // Confirmed sessions - refund only if > 24 hours before
    if ($should_refund) {
        DND_Speaking_Helpers::refund_user_credits($user_id, 1, 'Student cancelled confirmed session more than 24 hours before');
        $refunded = true;
        error_log('STUDENT CANCEL CONFIRMED >24H - Refunded credit to student: ' . $user_id);
    } else {
        error_log('STUDENT CANCEL CONFIRMED <24H - No refund for student: ' . $user_id);
    }
} else if ($session->status === 'in_progress') {
    // In-progress sessions - NEVER refund (student already joined)
    error_log('STUDENT CANCEL IN_PROGRESS - No refund (student already joined session): ' . $user_id);
}
```

**Changes:**
- Tách riêng logic cho từng status (pending/confirmed/in_progress)
- In-progress session: KHÔNG hoàn tiền (student đã tham gia)
- Confirmed session: Chỉ hoàn nếu >24h
- Pending session: Luôn hoàn

#### Function: `ajax_update_session_status()` (Teacher Cancel via Dashboard)

**Status:** ✅ Already correct - all teacher cancels refund:
- In-progress → refund
- Confirmed → refund  
- Pending → refund

#### Function: `student_start_now()` (Credit Check)

**Addition:**
```php
// Check credits EARLY: if student has no credits, return immediately
if (!DND_Speaking_Helpers::get_user_credits($user_id) || DND_Speaking_Helpers::get_user_credits($user_id) < 1) {
    return new WP_Error('insufficient_credits', 'Không đủ buổi học để tham gia', ['status' => 400]);
}
```

**Changes:**
- Check credits BEFORE calling Discord webhook
- Return error immediately if insufficient credits

#### Function: `book_session()` (Credit Check)

**Status:** ✅ Already correct:
```php
// Deduct credits immediately when booking
if (!DND_Speaking_Helpers::deduct_user_credits($student_id, 1)) {
    return new WP_Error('insufficient_credits', 'Không đủ buổi học', ['status' => 400]);
}
```

---

### 2. Backend - `includes/class-admin.php`

#### Function: `handle_teacher_request()` (Teacher Decline Pending)

**Status:** ✅ Already correct:
```php
// If declined, refund the credits
if ($action === 'decline') {
    $student_id = $session->student_id;
    DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher declined session');
}
```

#### Function: `handle_upcoming_session()` (Teacher Cancel Confirmed)

**OLD LOGIC:**
```php
// Refund credits to student when teacher cancels
$student_id = $session->student_id;
DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled session');

// In case session was pending (teacher cancelled before confirming), ensure pending also refunded
if ($session->status === 'pending') {
    DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled pending session');
}
```

**NEW LOGIC:**
```php
// Teacher cancels confirmed session - ALWAYS refund credits to student
$student_id = $session->student_id;
DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled confirmed session');
error_log('TEACHER CANCEL CONFIRMED SESSION (via handle_upcoming_session) - Refunded 1 credit to student: ' . $student_id);
```

**Changes:**
- Removed duplicate refund check for pending (function only handles confirmed sessions)
- Simplified logic and added clear logging

---

### 3. Frontend - `blocks/student-sessions-block-frontend.js`

#### Cancel Button Warning Messages

**OLD LOGIC:**
```javascript
else if (sessionStatus === 'in_progress' && sessionTime) {
    const now = Math.floor(Date.now() / 1000);
    const hoursUntilSession = (sessionTime - now) / 3600;
    
    if (hoursUntilSession <= 24 && hoursUntilSession > 0) {
        willRefund = false;
        confirmMessage = 'Buổi học sẽ diễn ra trong vòng 24 giờ nữa...';
    } else if (hoursUntilSession > 24) {
        confirmMessage = 'Buổi học còn hơn 24 giờ nữa...';
    }
}
```

**NEW LOGIC:**
```javascript
else if (sessionStatus === 'in_progress') {
    // In-progress - NEVER refund (student already joined)
    willRefund = false;
    confirmMessage = 'Buổi học đang diễn ra hoặc bạn đã tham gia. Bạn sẽ KHÔNG được hoàn lại buổi học nếu hủy.\n\nBạn có chắc muốn tiếp tục hủy?';
}
```

**Changes:**
- In-progress sessions: Luôn hiển thị cảnh báo không hoàn tiền
- Không cần check thời gian cho in_progress (vì đã tham gia rồi)

---

## Testing Scenarios

### Test Case 1: Student Cancel Pending
1. Student books a session (status: pending)
2. Student cancels before teacher accepts
3. ✅ Expected: Refund 1 credit

### Test Case 2: Student Cancel Confirmed >24h
1. Student books session for 3 days later
2. Teacher accepts (status: confirmed)
3. Student cancels (still >24h before session)
4. ✅ Expected: Refund 1 credit

### Test Case 3: Student Cancel Confirmed <24h
1. Student books session for tomorrow
2. Teacher accepts (status: confirmed)
3. Student cancels (<24h before session)
4. ❌ Expected: NO refund

### Test Case 4: Student Cancel In-Progress (Start Now)
1. Student clicks "Start Now" (status: in_progress)
2. Student joins Discord room
3. Student cancels session
4. ❌ Expected: NO refund (already joined)

### Test Case 5: Teacher Cancel Any Status
1. Teacher cancels pending/confirmed/in_progress session
2. ✅ Expected: ALWAYS refund 1 credit to student

### Test Case 6: Insufficient Credits - Start Now
1. Student has 0 credits
2. Student clicks "Start Now"
3. ✅ Expected: Error "Không đủ buổi học để tham gia" immediately

### Test Case 7: Insufficient Credits - Book
1. Student has 0 credits
2. Student tries to book a session
3. ✅ Expected: Error "Không đủ buổi học" immediately

---

## Summary of All Refund Paths

### Student Cancels
| Path | File | Function | Status | Refund Logic |
|------|------|----------|--------|-------------|
| REST API | class-rest-api.php | cancel_session() | ✅ Fixed | Pending=yes, Confirmed=24h, In-progress=no |

### Teacher Cancels
| Path | File | Function | Status | Refund Logic |
|------|------|----------|--------|-------------|
| AJAX Dashboard | class-rest-api.php | ajax_update_session_status() | ✅ Correct | Always refund (pending/confirmed/in_progress) |
| Admin Decline | class-admin.php | handle_teacher_request() | ✅ Correct | Always refund on decline |
| Admin Cancel | class-admin.php | handle_upcoming_session() | ✅ Fixed | Always refund |

### Credit Checks
| Path | File | Function | Status | Check Logic |
|------|------|----------|--------|-------------|
| Book Session | class-rest-api.php | book_session() | ✅ Correct | Check before insert |
| Start Now | class-rest-api.php | student_start_now() | ✅ Fixed | Check before webhook |

---

## Files Modified

1. **includes/class-rest-api.php**
   - `cancel_session()` - Fixed student cancel logic for in_progress
   - `student_start_now()` - Added early credit check

2. **includes/class-admin.php**
   - `handle_upcoming_session()` - Simplified teacher cancel refund logic

3. **blocks/student-sessions-block-frontend.js**
   - Updated cancel confirmation messages for in_progress sessions

---

## Database Logging

All refund operations now include detailed error_log entries:
- `STUDENT CANCEL PENDING - Refunded credit to student: {id}`
- `STUDENT CANCEL CONFIRMED >24H - Refunded credit to student: {id}`
- `STUDENT CANCEL CONFIRMED <24H - No refund for student: {id}`
- `STUDENT CANCEL IN_PROGRESS - No refund (student already joined session): {id}`
- `TEACHER CANCEL {STATUS} SESSION - Refunded 1 credit to student: {id}`

Check PHP error logs to verify refund operations in production.

---

## Next Steps

1. ✅ Code changes completed
2. ✅ Syntax checks passed
3. ⏳ Deploy to staging environment
4. ⏳ Run manual testing for all scenarios above
5. ⏳ Monitor PHP error logs for refund operations
6. ⏳ Verify credit balance changes in database
7. ⏳ Deploy to production after successful testing

---

## Notes

- Credits are deducted immediately at booking time
- All teacher cancellations always refund (business rule)
- In-progress sessions mean student already joined → no refund
- Frontend displays appropriate warnings before cancel
- Error logs help track all refund operations for debugging

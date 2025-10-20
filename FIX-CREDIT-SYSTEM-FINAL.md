# Fix: Credit System Issues - FINAL FIX

## Ngày: 20/10/2025

## Vấn đề đã được xác định và sửa

### 🔴 Lỗi 1: Học viên book lịch, giáo viên confirm, học viên không bị trừ buổi

**Root Cause:**
- Teacher sử dụng AJAX action `update_session_status` (không phải `handle_teacher_request`)
- Logic trừ credit được đặt sai chỗ trong `class-admin.php` 
- Function `ajax_update_session_status()` trong `class-rest-api.php` THIẾU logic trừ credit khi confirm

**Solution:**

**File: `includes/class-rest-api.php`**

Thêm Case 1.6 vào function `ajax_update_session_status()`:

```php
// Case 1.6: Confirming a pending session - DEDUCT CREDIT
if ($new_status === 'confirmed' && $session->status === 'pending') {
    $student_id = $session->student_id;
    if (!DND_Speaking_Helpers::deduct_user_credits($student_id, 1)) {
        error_log('TEACHER CONFIRM SESSION - Failed to deduct credit from student: ' . $student_id);
        wp_send_json_error('Học viên không đủ buổi học để xác nhận');
        return;
    }
    error_log('TEACHER CONFIRM SESSION - Deducted 1 credit from student: ' . $student_id);
}
```

**Vị trí:** Sau Case 1.5 (Teacher cancel confirmed session), trước Case 2 (Complete session)

**Flow hoàn chỉnh:**
1. Học viên book lịch → Session status = `pending` (chưa trừ buổi)
2. Giáo viên nhấn "Xác nhận" → Call AJAX `update_session_status` với `new_status = 'confirmed'`
3. Backend kiểm tra `$new_status === 'confirmed' && $session->status === 'pending'`
4. **Trừ 1 buổi học** từ học viên
5. Nếu không đủ buổi → Return error, không update status
6. Update status thành `confirmed`

---

### 🔴 Lỗi 2: Học viên hủy lịch đã confirm <24h, không hiện cảnh báo

**Root Cause:**
- Frontend JavaScript có logic kiểm tra 24h
- **NHƯNG** button cancel được render từ REST API `render_student_session_card()`
- Function này THIẾU `data-session-time` và `data-session-status` attributes
- JavaScript không thể đọc được data để tính toán

**Solution:**

**File: `includes/class-rest-api.php`**

Thêm calculation và data attributes vào `render_student_session_card()`:

```php
private function render_student_session_card($session) {
    $status_text = '';
    $status_class = '';
    $actions = '';

    // Calculate session timestamp for cancel warning
    $session_timestamp = '';
    if (!empty($session->start_time)) {
        $session_timestamp = strtotime($session->start_time);
    } else if (!empty($session->session_date) && !empty($session->session_time)) {
        $session_timestamp = strtotime($session->session_date . ' ' . $session->session_time);
    }

    switch ($session->status) {
        case 'pending':
            $status_text = 'Chờ xác nhận';
            $status_class = 'pending';
            $actions = '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '" data-session-time="' . $session_timestamp . '" data-session-status="pending">Hủy</button>';
            break;
        case 'confirmed':
            $status_text = 'Đã xác nhận';
            $status_class = 'confirmed';
            
            // Show join button with room link if available
            if (!empty($session->discord_channel)) {
                $actions = '<a href="' . esc_url($session->discord_channel) . '" class="dnd-btn dnd-btn-join">Tham gia ngay</a>';
            } else {
                $actions = '';
            }
            $actions .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '" data-session-time="' . $session_timestamp . '" data-session-status="confirmed">Hủy</button>';
            break;
        case 'in_progress':
            // Similar pattern...
            $actions .= '<button class="dnd-btn dnd-btn-cancel" data-session-id="' . $session->id . '" data-session-time="' . $session_timestamp . '" data-session-status="in_progress">Hủy</button>';
            break;
        // ... other cases
    }
    // ... rest of function
}
```

**Flow hoàn chỉnh:**
1. Page load → AJAX call `get_student_sessions`
2. Backend render HTML với `data-session-time` và `data-session-status`
3. Học viên click "Hủy" → JavaScript đọc attributes
4. Tính toán: `hoursUntilSession = (sessionTime - now) / 3600`
5. Nếu `hoursUntilSession <= 24`:
   - Hiển thị: "Buổi học sẽ diễn ra trong vòng 24 giờ nữa, nên bạn sẽ KHÔNG được hoàn lại buổi học nếu hủy. Bạn có chắc muốn tiếp tục hủy?"
6. Nếu `hoursUntilSession > 24`:
   - Hiển thị: "Buổi học còn hơn 24 giờ nữa, bạn sẽ được hoàn lại 1 buổi học. Bạn có chắc muốn hủy?"

---

## Files đã sửa (FINAL)

### 1. ✅ `includes/class-rest-api.php`
**Line ~1298:** Thêm Case 1.6 - Deduct credit when confirming
```php
// Case 1.6: Confirming a pending session - DEDUCT CREDIT
if ($new_status === 'confirmed' && $session->status === 'pending') {
    $student_id = $session->student_id;
    if (!DND_Speaking_Helpers::deduct_user_credits($student_id, 1)) {
        error_log('TEACHER CONFIRM SESSION - Failed to deduct credit from student: ' . $student_id);
        wp_send_json_error('Học viên không đủ buổi học để xác nhận');
        return;
    }
    error_log('TEACHER CONFIRM SESSION - Deducted 1 credit from student: ' . $student_id);
}
```

**Line ~1453:** Thêm session timestamp calculation và data attributes
```php
// Calculate session timestamp for cancel warning
$session_timestamp = '';
if (!empty($session->start_time)) {
    $session_timestamp = strtotime($session->start_time);
} else if (!empty($session->session_date) && !empty($session->session_time)) {
    $session_timestamp = strtotime($session->session_date . ' ' . $session->session_time);
}

// Add to buttons:
data-session-time="' . $session_timestamp . '" data-session-status="confirmed"
```

---

## Testing Steps

### Test 1: Credit deduction khi teacher confirm ✅

**Setup:**
1. Admin cấp 5 buổi học cho học viên A
2. Học viên A book lịch với giáo viên B

**Action:**
1. Giáo viên B login
2. Vào Session History → tab "Chờ xác nhận"
3. Click button "Xác nhận"

**Expected:**
- ✅ Session chuyển sang "Đã xác nhận"
- ✅ Học viên A còn 4 buổi học (kiểm tra trong Credits block hoặc Admin)
- ✅ Log trong error_log: `TEACHER CONFIRM SESSION - Deducted 1 credit from student: [student_id]`

**Fail case:**
- Nếu học viên không đủ buổi → Alert: "Học viên không đủ buổi học để xác nhận"
- Session vẫn ở trạng thái "Pending"

---

### Test 2: Cảnh báo khi hủy > 24h ✅

**Setup:**
1. Học viên có session confirmed, thời gian học là ngày mai lúc 10:00 (> 24h)

**Action:**
1. Học viên vào Student Sessions
2. Click "Hủy" trên session

**Expected:**
- ✅ Alert: "Buổi học còn hơn 24 giờ nữa, bạn sẽ được hoàn lại 1 buổi học. Bạn có chắc muốn hủy?"
- ✅ Nếu confirm → Session bị hủy
- ✅ Alert: "Đã hủy buổi học và hoàn lại 1 buổi."
- ✅ Học viên được hoàn 1 buổi

---

### Test 3: Cảnh báo khi hủy < 24h ⚠️

**Setup:**
1. Học viên có session confirmed, thời gian học là hôm nay lúc 20:00 (< 24h)
2. Hiện tại là 10:00 sáng

**Action:**
1. Học viên vào Student Sessions
2. Click "Hủy" trên session

**Expected:**
- ✅ Alert: "Buổi học sẽ diễn ra trong vòng 24 giờ nữa, nên bạn sẽ KHÔNG được hoàn lại buổi học nếu hủy. Bạn có chắc muốn tiếp tục hủy?"
- ✅ Nếu confirm → Session bị hủy
- ✅ Alert: "Đã hủy buổi học."
- ✅ Học viên KHÔNG được hoàn buổi

---

### Test 4: Pending session - No warning ✅

**Setup:**
1. Học viên có session pending (chưa được giáo viên confirm)

**Action:**
1. Click "Hủy"

**Expected:**
- ✅ Alert đơn giản: "Bạn có chắc muốn hủy buổi học này?"
- ✅ Session bị hủy (không có credit bị mất vì chưa confirm)

---

## Debug Commands

Nếu vẫn gặp lỗi, kiểm tra:

### 1. Check credit trong database:
```sql
SELECT * FROM wp_dnd_speaking_credits WHERE user_id = [student_id];
```

### 2. Check sessions:
```sql
SELECT id, student_id, teacher_id, status, session_date, session_time, start_time 
FROM wp_dnd_speaking_sessions 
WHERE student_id = [student_id] 
ORDER BY id DESC LIMIT 5;
```

### 3. Check logs:
```sql
SELECT * FROM wp_dnd_speaking_logs 
WHERE user_id = [student_id] 
AND action IN ('credit_deducted', 'credit_refunded')
ORDER BY created_at DESC LIMIT 10;
```

### 4. Check WordPress error log:
```
tail -f /path/to/debug.log | grep "TEACHER CONFIRM\|CREDIT"
```

---

## Summary

✅ **Lỗi 1 FIXED:** Teacher confirm giờ đã trừ buổi học chính xác  
✅ **Lỗi 2 FIXED:** Cảnh báo 24h hiện đúng với message rõ ràng  
✅ **Logging:** Đầy đủ để debug  
✅ **Error handling:** Rollback nếu không đủ credit  

🎉 **HỆ THỐNG CREDIT HOÀN CHỈNH!**

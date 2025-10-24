# Race Condition Fix - Booking System

## Vấn đề
Khi 2 học viên cùng book một buổi học vào cùng một thời điểm (ví dụ: 1PM), cả 2 đều có thể book thành công do race condition:

1. Học viên A kiểm tra slot → Available
2. Học viên B kiểm tra slot → Available (vẫn chưa có ai booking)
3. Học viên A trừ credits và tạo session
4. Học viên B trừ credits và tạo session
5. Kết quả: Cả 2 đều bị trừ credits và có session, nhưng slot chỉ nên cho 1 người

## Giải pháp
Sử dụng **Database Transaction với Row Locking** để đảm bảo atomic operation:

### 1. Cải thiện hàm `book_session()` trong `class-rest-api.php`

**Thay đổi chính:**
- Bọc toàn bộ logic booking trong một transaction
- Sử dụng `SELECT ... FOR UPDATE` để lock row khi kiểm tra slot availability
- Nếu có lỗi ở bất kỳ bước nào, rollback transaction
- Chỉ commit khi tất cả các bước thành công

**Flow mới:**
```
START TRANSACTION
  ↓
SELECT ... FOR UPDATE (Lock slot để check)
  ↓
Nếu slot đã được đặt → ROLLBACK → Return error
  ↓
Deduct credits (atomic operation)
  ↓
Nếu không đủ credits → ROLLBACK → Return error
  ↓
Insert session
  ↓
Nếu insert thất bại → ROLLBACK → Return error
  ↓
COMMIT
  ↓
Return success
```

**Lợi ích:**
- `FOR UPDATE` lock row trong thời gian transaction, ngăn không cho request khác đọc và modify cùng lúc
- Transaction đảm bảo tất cả các bước (check, deduct credits, insert) là atomic
- Người book sau sẽ phải đợi transaction đầu tiên hoàn tất, và sẽ thấy slot đã được đặt

### 2. Cải thiện hàm `deduct_user_credits()` trong `class-helpers.php`

**Thay đổi chính:**
Thay vì:
```php
// Đọc credits
$current_credits = get_credits();
// Kiểm tra
if ($current_credits < $amount) return false;
// Update
update_credits($current_credits - $amount);
```

Sử dụng **Atomic UPDATE**:
```php
UPDATE credits 
SET credits = credits - $amount 
WHERE user_id = $user_id AND credits >= $amount
```

**Lợi ích:**
- Chỉ 1 query duy nhất, không có khoảng thời gian giữa read và write
- Database tự động đảm bảo atomic operation
- Nếu credits không đủ, query sẽ không update row nào (rows_affected = 0)

## Kết quả
Với 2 thay đổi này, khi 2 học viên cùng book 1 slot:

1. **Học viên A** bắt đầu transaction, lock slot với `FOR UPDATE`
2. **Học viên B** cũng cố gắng lock cùng slot, nhưng phải **chờ** transaction của A hoàn tất
3. **Học viên A** hoàn tất booking → COMMIT → giải phóng lock
4. **Học viên B** giờ mới có thể lock, nhưng sẽ thấy slot đã được đặt → ROLLBACK → Return error: "Slot này đã được đặt bởi học viên khác. Vui lòng chọn slot khác."

## Testing
Để test race condition, có thể:
1. Mở 2 tab browser
2. Cùng lúc click book cùng 1 slot ở cả 2 tab
3. Kết quả mong đợi: 1 người thành công, 1 người nhận error message

## Files đã thay đổi
- `includes/class-rest-api.php` - Hàm `book_session()`
- `includes/class-helpers.php` - Hàm `deduct_user_credits()`

## Ngày thực hiện
24/10/2025

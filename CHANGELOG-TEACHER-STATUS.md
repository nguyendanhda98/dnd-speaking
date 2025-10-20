# Changelog - Teacher Status Toggle Enhancement

## Ngày cập nhật: 20/10/2025

### Các thay đổi chính

#### 1. Thông báo xác nhận khi toggle Online/Offline
- Khi giáo viên click toggle để chuyển sang Online hoặc Offline, hệ thống sẽ hiển thị dialog xác nhận
- Nếu người dùng chọn "Cancel", toggle sẽ quay về trạng thái cũ
- Chỉ khi người dùng xác nhận "OK" thì mới thực hiện gửi request

#### 2. Thông báo "Đang tạo phòng học..."
- Khi giáo viên toggle sang Online, ngay sau khi xác nhận, hệ thống hiển thị thông báo:
  - **"⏳ Đang tạo phòng học..."** (màu xanh dương #0066cc)
- Thông báo này xuất hiện trong lúc chờ webhook trả về kết quả từ Discord server

#### 3. Thông báo "Tạo phòng thành công"
- Khi nhận được link room từ webhook, hệ thống hiển thị thông báo:
  - **"✓ Tạo phòng thành công!"** (màu xanh lá #00aa00)
- Link room được cập nhật vào nút "Tham gia phòng"
- Thông báo thành công tự động biến mất sau 3 giây (fade out)

#### 4. Xử lý lỗi chi tiết
- Nếu webhook không kết nối được: hiển thị lỗi màu đỏ với icon ✗
- Nếu webhook trả về lỗi: hiển thị message cụ thể từ server
- Nếu teacher chưa connect Discord: hiển thị link để connect
- Toggle tự động quay về trạng thái cũ khi có lỗi

#### 5. Hiệu ứng UI/UX
- Animation fade-in khi thông báo xuất hiện
- Màu sắc rõ ràng: xanh dương (đang xử lý), xanh lá (thành công), đỏ (lỗi)
- Icon trực quan: ⏳ (đang xử lý), ✓ (thành công), ✗ (lỗi)

### Files đã thay đổi

1. **blocks/teacher-header-block-frontend.js**
   - Thêm dialog xác nhận trước khi toggle
   - Hiển thị thông báo "Đang tạo phòng..." khi bắt đầu
   - Hiển thị thông báo "Tạo phòng thành công!" khi hoàn tất
   - Tự động ẩn thông báo thành công sau 3 giây
   - Cải thiện xử lý lỗi với thông báo chi tiết

2. **blocks/teacher-header-block.css**
   - Thêm animation fade-in cho thông báo
   - Cải thiện styling cho message box
   - Responsive design cho mobile

3. **includes/class-admin.php** (phương thức `update_teacher_availability`)
   - Kiểm tra response code từ webhook
   - Trả về message lỗi chi tiết hơn
   - Xử lý case webhook trả về error message

### Workflow hoàn chỉnh

```
Giáo viên click toggle -> 
  Hiển thị dialog xác nhận -> 
    Nếu OK:
      Hiển thị "Đang tạo phòng..." ->
        Gửi webhook ->
          Nếu thành công:
            Cập nhật link room ->
            Hiển thị "Tạo phòng thành công!" ->
            Tự động ẩn sau 3s
          Nếu thất bại:
            Hiển thị lỗi chi tiết ->
            Revert toggle về trạng thái cũ
    Nếu Cancel:
      Revert toggle về trạng thái cũ
```

### Testing Checklist

- [ ] Toggle Online: hiển thị dialog xác nhận
- [ ] Toggle Offline: hiển thị dialog xác nhận
- [ ] Click Cancel: toggle quay về trạng thái cũ
- [ ] Click OK khi toggle Online: hiển thị "Đang tạo phòng..."
- [ ] Webhook thành công: hiển thị "Tạo phòng thành công!" và cập nhật link
- [ ] Thông báo thành công tự động biến mất sau 3 giây
- [ ] Webhook thất bại: hiển thị lỗi và revert toggle
- [ ] Chưa connect Discord: hiển thị link connect
- [ ] Mobile responsive: thông báo hiển thị đúng trên mobile

### Lưu ý cho Developer

- Webhook phải trả về response code 200 và JSON với field `channelId`
- Nếu có lỗi, webhook nên trả về JSON với field `error` chứa message
- Timeout cho webhook là 30 giây
- Animation và styling được tối ưu cho cả desktop và mobile

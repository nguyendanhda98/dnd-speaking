# Listening Sessions Block - Hướng Dẫn Sử Dụng

## 📋 Tổng Quan

Block "Listening Sessions" cho phép admin quản lý các video YouTube mẫu để học viên có thể xem và hiểu rõ hơn về cách buổi học diễn ra với giáo viên.

## 🎯 Tính Năng

### Admin (Quản trị viên)
- ✅ Quản lý tập trung trong WordPress Admin Dashboard
- ✅ Thêm/Sửa/Xóa video YouTube
- ✅ Chọn giáo viên và học viên cho mỗi video
- ✅ Thêm thông tin: tiêu đề, mô tả, chủ đề, thời lượng
- ✅ Xem preview thumbnail từ YouTube
- ✅ Hiển thị danh sách đầy đủ với thông tin chi tiết

### Frontend (Người dùng)
- ✅ Xem danh sách video dưới dạng lưới
- ✅ Hiển thị thông tin giáo viên và học viên
- ✅ Xem chủ đề và thời lượng video
- ✅ Click để xem video trong modal popup
- ✅ Video tự động phát khi mở modal
- ✅ Responsive trên mọi thiết bị

## 📝 Hướng Dẫn Sử Dụng

### 1. Thêm Block vào Trang

1. Vào trang WordPress Editor (Gutenberg)
2. Click nút "+" để thêm block
3. Tìm kiếm "DND Listening Sessions"
4. Thêm block vào trang
5. Có thể tùy chỉnh tiêu đề trong Sidebar Settings

### 2. Quản Lý Video (Dành cho Admin)

#### Cách 1: Từ Frontend
- Khi đã đăng nhập với quyền admin, click vào nút **"⚙️ Quản lý Listening Sessions"** trên block

#### Cách 2: Từ Admin Dashboard
1. Vào **DND Speaking → Listening Sessions**
2. Điền form thêm video:
   - **Tiêu đề video** (*bắt buộc*): Ví dụ "Buổi học với Teacher John - Chủ đề Travel"
   - **YouTube URL** (*bắt buộc*): 
     - Format 1: `https://www.youtube.com/watch?v=ABC123xyz`
     - Format 2: `https://youtu.be/ABC123xyz`
   - **Mô tả** (tùy chọn): Mô tả ngắn gọn về buổi học
   - **Giáo viên** (*bắt buộc*): Chọn giáo viên từ danh sách
   - **Học viên** (*bắt buộc*): Chọn học viên từ danh sách
   - **Chủ đề bài học** (tùy chọn): Ví dụ "Travel", "Business English"
   - **Thời lượng** (tùy chọn): Số phút của video
3. Click **"➕ Thêm Listening Session"**

### 3. Chỉnh Sửa Video

1. Vào **DND Speaking → Listening Sessions**
2. Tìm video cần sửa trong danh sách
3. Click nút **"✏️ Sửa"**
4. Chỉnh sửa thông tin
5. Click **"💾 Cập Nhật"**

### 4. Xóa Video

1. Vào **DND Speaking → Listening Sessions**
2. Tìm video cần xóa trong danh sách
3. Click nút **"🗑️ Xóa"**
4. Xác nhận xóa

## 🎨 Giao Diện

### Frontend Display
```
┌─────────────────────────────────────────────┐
│         Nghe Buổi Học                       │
│                                             │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  │
│  │ [Video1] │  │ [Video2] │  │ [Video3] │  │
│  │ Thumbnail│  │ Thumbnail│  │ Thumbnail│  │
│  │──────────│  │──────────│  │──────────│  │
│  │ Tiêu đề  │  │ Tiêu đề  │  │ Tiêu đề  │  │
│  │ 👨‍🏫 Teacher│  │ 👨‍🏫 Teacher│  │ 👨‍🏫 Teacher│  │
│  │ 👨‍🎓 Student│  │ 👨‍🎓 Student│  │ 👨‍🎓 Student│  │
│  │ 📚 Topic  │  │ 📚 Topic  │  │ 📚 Topic  │  │
│  │ [Xem]    │  │ [Xem]    │  │ [Xem]    │  │
│  └──────────┘  └──────────┘  └──────────┘  │
└─────────────────────────────────────────────┘
```

### Admin Dashboard
```
┌─────────────────────────────────────────────┐
│    Quản Lý Listening Sessions               │
├─────────────────────────────────────────────┤
│  Form Thêm/Sửa                              │
│  ┌───────────────────────────────────────┐  │
│  │ Tiêu đề: [________________]          │  │
│  │ YouTube URL: [________________]       │  │
│  │ Mô tả: [_______________________]      │  │
│  │ Giáo viên: [▼ Select Teacher]         │  │
│  │ Học viên: [▼ Select Student]          │  │
│  │ Chủ đề: [________________]            │  │
│  │ Thời lượng: [__] phút                 │  │
│  │ [➕ Thêm] hoặc [💾 Cập Nhật]         │  │
│  └───────────────────────────────────────┘  │
├─────────────────────────────────────────────┤
│  Danh Sách Video                            │
│  ┌─────────────────────────────────────┐    │
│  │ Thumb │ Tiêu đề │ Teacher │ Actions │    │
│  ├─────────────────────────────────────┤    │
│  │ [img] │ Video 1 │ John    │ [👁️✏️🗑️]│    │
│  │ [img] │ Video 2 │ Mary    │ [👁️✏️🗑️]│    │
│  └─────────────────────────────────────┘    │
└─────────────────────────────────────────────┘
```

## 📁 Cấu Trúc File

```
blocks/
├── listening-sessions-block.php          # Backend logic
├── listening-sessions-block.js           # Gutenberg editor
├── listening-sessions-block.css          # Styles
└── listening-sessions-block-frontend.js  # Frontend interactions

includes/
└── class-admin.php                       # Admin page & handlers
```

## 🔧 Database

Video được lưu trong WordPress Options:
- Option name: `dnd_listening_sessions`
- Format: Array of objects

Cấu trúc mỗi session:
```php
[
    'id' => 'video_abc123',
    'title' => 'Tiêu đề video',
    'url' => 'https://youtube.com/...',
    'description' => 'Mô tả',
    'teacher_id' => 123,
    'student_id' => 456,
    'lesson_topic' => 'Travel',
    'video_duration' => 30,
    'created_at' => '2025-10-30 10:00:00',
    'updated_at' => '2025-10-30 11:00:00'
]
```

## 🎯 Use Cases

1. **Marketing**: Cho học viên mới xem mẫu buổi học
2. **Portfolio**: Giáo viên có thể show video của họ
3. **Student Testimonial**: Học viên hiện tại có thể xem trải nghiệm của học viên khác
4. **Quality Assurance**: Admin kiểm tra chất lượng buổi học

## 🚀 Tips & Best Practices

1. **Chọn video chất lượng cao**: Nên dùng video 720p hoặc 1080p
2. **Đặt tiêu đề rõ ràng**: Nên bao gồm tên giáo viên và chủ đề
3. **Thêm mô tả chi tiết**: Giúp học viên hiểu rõ nội dung
4. **Cập nhật thường xuyên**: Giữ danh sách video mới và relevant
5. **Phân loại theo level**: Có thể thêm video cho các level khác nhau

## 🐛 Troubleshooting

### Video không hiển thị thumbnail?
- Kiểm tra URL YouTube có đúng format không
- Đảm bảo video là public, không bị private

### Không xem được video?
- Kiểm tra video không bị block ở quốc gia của bạn
- Đảm bảo embed được bật cho video

### Admin không thấy nút "Quản lý"?
- Kiểm tra user có quyền `manage_options` không
- Đăng nhập với account Administrator

## 📞 Support

Nếu có vấn đề, liên hệ team phát triển hoặc check logs trong **DND Speaking → Logs**

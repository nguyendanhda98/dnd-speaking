<?php

/**
 * Email Notifications Handler for DND Speaking plugin
 * Handles sending emails for various session events
 */

class DND_Speaking_Email_Notifications {

    /**
     * Initialize the email notifications
     */
    public function __construct() {
        // No hooks needed here, we'll call methods directly from REST API
    }

    /**
     * Send email when student books a session
     * 
     * @param int $session_id Session ID
     * @param int $student_id Student user ID
     * @param int $teacher_id Teacher user ID
     * @param string $start_time Session start time (local timezone)
     */
    public function notify_teacher_new_booking($session_id, $student_id, $teacher_id, $start_time) {
        $teacher = get_userdata($teacher_id);
        $student = get_userdata($student_id);
        
        if (!$teacher || !$student) {
            error_log('EMAIL NOTIFICATION - User not found. Teacher ID: ' . $teacher_id . ', Student ID: ' . $student_id);
            return false;
        }
        
        $teacher_email = $teacher->user_email;
        $teacher_name = $teacher->display_name;
        $student_name = $student->display_name;
        
        // Format the time nicely
        $formatted_time = date('d/m/Y H:i', strtotime($start_time));
        
        $subject = '[DND Speaking] Bạn có yêu cầu đặt buổi học mới';
        
        $message = "Xin chào {$teacher_name},\n\n";
        $message .= "Học viên {$student_name} vừa đặt một buổi học với bạn.\n\n";
        $message .= "Thông tin buổi học:\n";
        $message .= "- Học viên: {$student_name}\n";
        $message .= "- Thời gian: {$formatted_time}\n";
        $message .= "- Trạng thái: Đang chờ xác nhận\n\n";
        $message .= "Vui lòng vào hệ thống để xác nhận hoặc từ chối buổi học này.\n\n";
        $message .= "Link quản lý: " . home_url('/') . "\n\n";
        $message .= "Trân trọng,\n";
        $message .= "DND Speaking Team";
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        $sent = wp_mail($teacher_email, $subject, $message, $headers);
        
        if ($sent) {
            error_log('EMAIL NOTIFICATION - Sent booking notification to teacher ' . $teacher_id . ' (' . $teacher_email . ')');
        } else {
            error_log('EMAIL NOTIFICATION - Failed to send booking notification to teacher ' . $teacher_id);
        }
        
        return $sent;
    }

    /**
     * Send email when student cancels a session
     * 
     * @param int $session_id Session ID
     * @param int $student_id Student user ID
     * @param int $teacher_id Teacher user ID
     * @param string $start_time Session start time (local timezone)
     * @param string $session_status Original session status (pending/confirmed/in_progress)
     */
    public function notify_teacher_student_cancelled($session_id, $student_id, $teacher_id, $start_time, $session_status) {
        $teacher = get_userdata($teacher_id);
        $student = get_userdata($student_id);
        
        if (!$teacher || !$student) {
            error_log('EMAIL NOTIFICATION - User not found. Teacher ID: ' . $teacher_id . ', Student ID: ' . $student_id);
            return false;
        }
        
        $teacher_email = $teacher->user_email;
        $teacher_name = $teacher->display_name;
        $student_name = $student->display_name;
        
        // Format the time nicely
        $formatted_time = date('d/m/Y H:i', strtotime($start_time));
        
        $subject = '[DND Speaking] Học viên đã hủy buổi học';
        
        $message = "Xin chào {$teacher_name},\n\n";
        $message .= "Học viên {$student_name} vừa hủy buổi học với bạn.\n\n";
        $message .= "Thông tin buổi học:\n";
        $message .= "- Học viên: {$student_name}\n";
        $message .= "- Thời gian: {$formatted_time}\n";
        $message .= "- Trạng thái trước đó: " . $this->get_status_label($session_status) . "\n\n";
        $message .= "Buổi học đã được hủy và slot thời gian của bạn đã được giải phóng.\n\n";
        $message .= "Trân trọng,\n";
        $message .= "DND Speaking Team";
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        $sent = wp_mail($teacher_email, $subject, $message, $headers);
        
        if ($sent) {
            error_log('EMAIL NOTIFICATION - Sent cancellation notification to teacher ' . $teacher_id . ' (' . $teacher_email . ')');
        } else {
            error_log('EMAIL NOTIFICATION - Failed to send cancellation notification to teacher ' . $teacher_id);
        }
        
        return $sent;
    }

    /**
     * Send email when teacher accepts/confirms a session
     * 
     * @param int $session_id Session ID
     * @param int $student_id Student user ID
     * @param int $teacher_id Teacher user ID
     * @param string $start_time Session start time (local timezone)
     */
    public function notify_student_session_confirmed($session_id, $student_id, $teacher_id, $start_time) {
        $teacher = get_userdata($teacher_id);
        $student = get_userdata($student_id);
        
        if (!$teacher || !$student) {
            error_log('EMAIL NOTIFICATION - User not found. Teacher ID: ' . $teacher_id . ', Student ID: ' . $student_id);
            return false;
        }
        
        $student_email = $student->user_email;
        $student_name = $student->display_name;
        $teacher_name = $teacher->display_name;
        
        // Format the time nicely
        $formatted_time = date('d/m/Y H:i', strtotime($start_time));
        
        $subject = '[DND Speaking] Buổi học của bạn đã được xác nhận';
        
        $message = "Xin chào {$student_name},\n\n";
        $message .= "Giáo viên {$teacher_name} đã xác nhận buổi học của bạn.\n\n";
        $message .= "Thông tin buổi học:\n";
        $message .= "- Giáo viên: {$teacher_name}\n";
        $message .= "- Thời gian: {$formatted_time}\n";
        $message .= "- Trạng thái: Đã xác nhận\n\n";
        $message .= "Vui lòng có mặt đúng giờ. Giáo viên sẽ bắt đầu buổi học và gửi link phòng học Discord cho bạn.\n\n";
        $message .= "Link quản lý buổi học: " . home_url('/') . "\n\n";
        $message .= "Chúc bạn có buổi học vui vẻ!\n\n";
        $message .= "Trân trọng,\n";
        $message .= "DND Speaking Team";
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        $sent = wp_mail($student_email, $subject, $message, $headers);
        
        if ($sent) {
            error_log('EMAIL NOTIFICATION - Sent confirmation notification to student ' . $student_id . ' (' . $student_email . ')');
        } else {
            error_log('EMAIL NOTIFICATION - Failed to send confirmation notification to student ' . $student_id);
        }
        
        return $sent;
    }

    /**
     * Send email when teacher cancels a session
     * 
     * @param int $session_id Session ID
     * @param int $student_id Student user ID
     * @param int $teacher_id Teacher user ID
     * @param string $start_time Session start time (local timezone)
     * @param string $session_status Original session status (pending/confirmed/in_progress)
     */
    public function notify_student_teacher_cancelled($session_id, $student_id, $teacher_id, $start_time, $session_status) {
        $teacher = get_userdata($teacher_id);
        $student = get_userdata($student_id);
        
        if (!$teacher || !$student) {
            error_log('EMAIL NOTIFICATION - User not found. Teacher ID: ' . $teacher_id . ', Student ID: ' . $student_id);
            return false;
        }
        
        $student_email = $student->user_email;
        $student_name = $student->display_name;
        $teacher_name = $teacher->display_name;
        
        // Format the time nicely
        $formatted_time = date('d/m/Y H:i', strtotime($start_time));
        
        $subject = '[DND Speaking] Buổi học đã bị hủy bởi giáo viên';
        
        $message = "Xin chào {$student_name},\n\n";
        $message .= "Rất tiếc, giáo viên {$teacher_name} đã hủy buổi học với bạn.\n\n";
        $message .= "Thông tin buổi học:\n";
        $message .= "- Giáo viên: {$teacher_name}\n";
        $message .= "- Thời gian: {$formatted_time}\n";
        $message .= "- Trạng thái trước đó: " . $this->get_status_label($session_status) . "\n\n";
        $message .= "Credits của bạn đã được hoàn lại. Bạn có thể đặt buổi học khác với giáo viên khác.\n\n";
        $message .= "Link đặt buổi học: " . home_url('/') . "\n\n";
        $message .= "Xin lỗi vì sự bất tiện này.\n\n";
        $message .= "Trân trọng,\n";
        $message .= "DND Speaking Team";
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        $sent = wp_mail($student_email, $subject, $message, $headers);
        
        if ($sent) {
            error_log('EMAIL NOTIFICATION - Sent teacher cancellation notification to student ' . $student_id . ' (' . $student_email . ')');
        } else {
            error_log('EMAIL NOTIFICATION - Failed to send teacher cancellation notification to student ' . $student_id);
        }
        
        return $sent;
    }

    /**
     * Send email when teacher starts a session
     * 
     * @param int $session_id Session ID
     * @param int $student_id Student user ID
     * @param int $teacher_id Teacher user ID
     * @param string $room_link Discord room link
     */
    public function notify_student_session_started($session_id, $student_id, $teacher_id, $room_link) {
        $teacher = get_userdata($teacher_id);
        $student = get_userdata($student_id);
        
        if (!$teacher || !$student) {
            error_log('EMAIL NOTIFICATION - User not found. Teacher ID: ' . $teacher_id . ', Student ID: ' . $student_id);
            return false;
        }
        
        $student_email = $student->user_email;
        $student_name = $student->display_name;
        $teacher_name = $teacher->display_name;
        
        $subject = '[DND Speaking] Buổi học đã bắt đầu - Vào phòng học ngay!';
        
        $message = "Xin chào {$student_name},\n\n";
        $message .= "Giáo viên {$teacher_name} đã bắt đầu buổi học của bạn.\n\n";
        $message .= "Vui lòng vào phòng học Discord ngay:\n";
        $message .= "{$room_link}\n\n";
        $message .= "Lưu ý: Vui lòng vào phòng trong vòng 5-10 phút để không bỏ lỡ buổi học.\n\n";
        $message .= "Chúc bạn có buổi học hiệu quả!\n\n";
        $message .= "Trân trọng,\n";
        $message .= "DND Speaking Team";
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        $sent = wp_mail($student_email, $subject, $message, $headers);
        
        if ($sent) {
            error_log('EMAIL NOTIFICATION - Sent session start notification to student ' . $student_id . ' (' . $student_email . ')');
        } else {
            error_log('EMAIL NOTIFICATION - Failed to send session start notification to student ' . $student_id);
        }
        
        return $sent;
    }

    /**
     * Get Vietnamese label for session status
     * 
     * @param string $status Session status
     * @return string Vietnamese label
     */
    private function get_status_label($status) {
        $labels = [
            'pending' => 'Đang chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'in_progress' => 'Đang diễn ra',
            'completed' => 'Đã hoàn thành',
            'cancelled' => 'Đã hủy'
        ];
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
}

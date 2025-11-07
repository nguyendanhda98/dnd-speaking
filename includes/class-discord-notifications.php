<?php

/**
 * Discord DM Notifications Handler for DND Speaking plugin
 * Handles sending Discord Direct Messages for various session events
 */

class DND_Speaking_Discord_Notifications {

    /**
     * Initialize the Discord notifications
     */
    public function __construct() {
        // No hooks needed here, we'll call methods directly from Email Notifications
    }

    /**
     * Send Discord DM to a user
     * 
     * @param int $user_id WordPress user ID
     * @param string $message Message content to send
     * @return bool Success status
     */
    private function send_discord_dm($user_id, $message) {
        // Get Discord user ID from user meta
        $discord_user_id = get_user_meta($user_id, 'discord_user_id', true);
        
        if (empty($discord_user_id)) {
            error_log('DISCORD DM - User ' . $user_id . ' does not have Discord connected');
            return false;
        }

        // Get bot token
        $bot_token = get_option('dnd_discord_bot_token');
        if (empty($bot_token)) {
            error_log('DISCORD DM - Bot token not configured');
            return false;
        }

        // Step 1: Create DM channel with user
        $dm_response = wp_remote_post('https://discord.com/api/users/@me/channels', [
            'headers' => [
                'Authorization' => 'Bot ' . $bot_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'recipient_id' => $discord_user_id
            ])
        ]);

        if (is_wp_error($dm_response)) {
            error_log('DISCORD DM - Failed to create DM channel: ' . $dm_response->get_error_message());
            return false;
        }

        $dm_body = json_decode(wp_remote_retrieve_body($dm_response), true);
        if (!isset($dm_body['id'])) {
            error_log('DISCORD DM - Invalid DM channel response');
            return false;
        }

        $channel_id = $dm_body['id'];

        // Step 2: Send message to DM channel
        $message_response = wp_remote_post('https://discord.com/api/channels/' . $channel_id . '/messages', [
            'headers' => [
                'Authorization' => 'Bot ' . $bot_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'content' => $message
            ])
        ]);

        if (is_wp_error($message_response)) {
            error_log('DISCORD DM - Failed to send message: ' . $message_response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($message_response);
        if ($response_code >= 200 && $response_code < 300) {
            error_log('DISCORD DM - Successfully sent message to user ' . $user_id);
            return true;
        } else {
            error_log('DISCORD DM - Failed to send message, response code: ' . $response_code);
            return false;
        }
    }

    /**
     * Notify teacher when student books a new session
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
            return false;
        }
        
        $teacher_name = $teacher->display_name;
        $student_name = $student->display_name;
        
        // Format the time nicely
        $formatted_time = date('d/m/Y H:i', strtotime($start_time));
        
        $message = "🔔 **Yêu cầu đặt buổi học mới**\n\n";
        $message .= "Xin chào **{$teacher_name}**,\n\n";
        $message .= "Học viên **{$student_name}** vừa đặt một buổi học với bạn.\n\n";
        $message .= "**Thông tin buổi học:**\n";
        $message .= "👤 Học viên: {$student_name}\n";
        $message .= "🕐 Thời gian: {$formatted_time}\n";
        $message .= "📊 Trạng thái: Đang chờ xác nhận\n\n";
        $message .= "Vui lòng vào hệ thống để xác nhận hoặc từ chối buổi học này.\n";
        $message .= "🔗 Link quản lý: " . home_url('/');
        
        return $this->send_discord_dm($teacher_id, $message);
    }

    /**
     * Notify teacher when student cancels a session
     * 
     * @param int $session_id Session ID
     * @param int $student_id Student user ID
     * @param int $teacher_id Teacher user ID
     * @param string $start_time Session start time (local timezone)
     * @param string $session_status Original session status
     */
    public function notify_teacher_student_cancelled($session_id, $student_id, $teacher_id, $start_time, $session_status) {
        $teacher = get_userdata($teacher_id);
        $student = get_userdata($student_id);
        
        if (!$teacher || !$student) {
            return false;
        }
        
        $teacher_name = $teacher->display_name;
        $student_name = $student->display_name;
        
        // Format the time nicely
        $formatted_time = date('d/m/Y H:i', strtotime($start_time));
        
        $message = "❌ **Buổi học đã bị hủy**\n\n";
        $message .= "Xin chào **{$teacher_name}**,\n\n";
        $message .= "Học viên **{$student_name}** vừa hủy buổi học với bạn.\n\n";
        $message .= "**Thông tin buổi học:**\n";
        $message .= "👤 Học viên: {$student_name}\n";
        $message .= "🕐 Thời gian: {$formatted_time}\n";
        $message .= "📊 Trạng thái trước đó: " . $this->get_status_label($session_status) . "\n\n";
        $message .= "Buổi học đã được hủy và slot thời gian của bạn đã được giải phóng.";
        
        return $this->send_discord_dm($teacher_id, $message);
    }

    /**
     * Notify student when teacher accepts/confirms a session
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
            return false;
        }
        
        $student_name = $student->display_name;
        $teacher_name = $teacher->display_name;
        
        // Format the time nicely
        $formatted_time = date('d/m/Y H:i', strtotime($start_time));
        
        $message = "✅ **Buổi học đã được xác nhận**\n\n";
        $message .= "Xin chào **{$student_name}**,\n\n";
        $message .= "Giáo viên **{$teacher_name}** đã xác nhận buổi học của bạn.\n\n";
        $message .= "**Thông tin buổi học:**\n";
        $message .= "👨‍🏫 Giáo viên: {$teacher_name}\n";
        $message .= "🕐 Thời gian: {$formatted_time}\n";
        $message .= "📊 Trạng thái: Đã xác nhận\n\n";
        $message .= "Vui lòng có mặt đúng giờ. Giáo viên sẽ bắt đầu buổi học và gửi link phòng học Discord cho bạn.\n\n";
        $message .= "🔗 Link quản lý buổi học: " . home_url('/') . "\n\n";
        $message .= "Chúc bạn có buổi học vui vẻ! 🎉";
        
        return $this->send_discord_dm($student_id, $message);
    }

    /**
     * Notify student when teacher cancels a session
     * 
     * @param int $session_id Session ID
     * @param int $student_id Student user ID
     * @param int $teacher_id Teacher user ID
     * @param string $start_time Session start time (local timezone)
     * @param string $session_status Original session status
     */
    public function notify_student_teacher_cancelled($session_id, $student_id, $teacher_id, $start_time, $session_status) {
        $teacher = get_userdata($teacher_id);
        $student = get_userdata($student_id);
        
        if (!$teacher || !$student) {
            return false;
        }
        
        $student_name = $student->display_name;
        $teacher_name = $teacher->display_name;
        
        // Format the time nicely
        $formatted_time = date('d/m/Y H:i', strtotime($start_time));
        
        $message = "❌ **Buổi học đã bị hủy bởi giáo viên**\n\n";
        $message .= "Xin chào **{$student_name}**,\n\n";
        $message .= "Rất tiếc, giáo viên **{$teacher_name}** đã hủy buổi học với bạn.\n\n";
        $message .= "**Thông tin buổi học:**\n";
        $message .= "👨‍🏫 Giáo viên: {$teacher_name}\n";
        $message .= "🕐 Thời gian: {$formatted_time}\n";
        $message .= "📊 Trạng thái trước đó: " . $this->get_status_label($session_status) . "\n\n";
        $message .= "Credits của bạn đã được hoàn lại. Bạn có thể đặt buổi học khác với giáo viên khác.\n\n";
        $message .= "🔗 Link đặt buổi học: " . home_url('/') . "\n\n";
        $message .= "Xin lỗi vì sự bất tiện này. 🙏";
        
        return $this->send_discord_dm($student_id, $message);
    }

    /**
     * Notify student when teacher starts a session
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
            return false;
        }
        
        $student_name = $student->display_name;
        $teacher_name = $teacher->display_name;
        
        $message = "🎓 **Buổi học đã bắt đầu - Vào phòng học ngay!**\n\n";
        $message .= "Xin chào **{$student_name}**,\n\n";
        $message .= "Giáo viên **{$teacher_name}** đã bắt đầu buổi học của bạn.\n\n";
        $message .= "🔊 **Vào phòng học Discord ngay:**\n";
        $message .= "{$room_link}\n\n";
        $message .= "⚠️ **Lưu ý:** Vui lòng vào phòng trong vòng 5-10 phút để không bỏ lỡ buổi học.\n\n";
        $message .= "Chúc bạn có buổi học hiệu quả! 💪";
        
        return $this->send_discord_dm($student_id, $message);
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

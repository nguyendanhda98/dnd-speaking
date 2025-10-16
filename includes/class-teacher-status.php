<?php
/**
 * Teacher status and list management
 */

class DND_Speaking_Teacher_Status {

    public function __construct() {
        add_shortcode('dnd_teacher_list', [$this, 'teacher_list_shortcode']);
        add_action('wp_ajax_toggle_availability', [$this, 'ajax_toggle_availability']);
        add_action('wp_ajax_nopriv_toggle_availability', [$this, 'ajax_toggle_availability']);
        add_action('wp_ajax_request_booking', [$this, 'ajax_request_booking']);
        add_action('wp_ajax_nopriv_request_booking', [$this, 'ajax_request_booking']);
    }

    public function teacher_list_shortcode() {
        if (!is_user_logged_in()) return '<p>Please log in to view teachers.</p>';

        $user = wp_get_current_user();
        if (!in_array('student', $user->roles)) return '<p>Only students can book sessions.</p>';

        $teachers = DND_Speaking_Helpers::get_online_teachers();

        ob_start();
        ?>
        <div class="dnd-teacher-list">
            <h2>Available Teachers</h2>
            <?php if ($teachers): ?>
                <ul>
                    <?php foreach ($teachers as $teacher): ?>
                        <li>
                            <strong><?php echo $teacher->display_name; ?></strong>
                            <button class="book-teacher" data-teacher-id="<?php echo $teacher->ID; ?>">Book Now</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No teachers available at the moment.</p>
            <?php endif; ?>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.book-teacher').on('click', function() {
                var teacherId = $(this).data('teacher-id');
                $.post(ajaxurl, {
                    action: 'request_booking',
                    teacher_id: teacherId
                }, function(response) {
                    alert(response);
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function ajax_toggle_availability() {
        if (!is_user_logged_in()) wp_die('Unauthorized');

        $user = wp_get_current_user();
        if (!in_array('teacher', $user->roles)) wp_die('Not a teacher');

        $available = isset($_POST['available']) ? intval($_POST['available']) : 0;
        DND_Speaking_Helpers::set_teacher_availability($user->ID, $available);

        wp_die($available ? 'Available' : 'Unavailable');
    }

    public function ajax_request_booking() {
        if (!is_user_logged_in()) wp_die('Unauthorized');

        $student_id = get_current_user_id();
        $teacher_id = intval($_POST['teacher_id']);

        if (!DND_Speaking_Helpers::is_teacher_available($teacher_id)) wp_die('Teacher not available');

        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_bookings';
        $wpdb->insert($table, [
            'student_id' => $student_id,
            'teacher_id' => $teacher_id,
            'status' => 'pending'
        ]);

        // Notify teacher (you can add email/notification here)

        wp_die('Booking request sent');
    }
}

// includes/class-student-dashboard.php
class DND_Speaking_Student_Dashboard {
    public function __construct() {
        add_shortcode('dnd_student_dashboard', [$this, 'shortcode_dashboard']);
    }

    public function shortcode_dashboard() {
        if (!is_user_logged_in()) return '<p>Please log in to view your dashboard.</p>';

        $user_id = get_current_user_id();
        $credits = DND_Speaking_Helpers::get_user_credits($user_id);

        ob_start();
        ?>
        <div class="dnd-student-dashboard">
            <h2>Your Speaking Sessions</h2>
            <p>Remaining Sessions: <strong><?php echo $credits; ?></strong></p>
            <a href="<?php echo site_url('/teacher-list/'); ?>" class="button">Book a Session</a>
        </div>
        <?php
        return ob_get_clean();
    }
}

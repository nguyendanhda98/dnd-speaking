// includes/class-student-dashboard.php
class DND_Speaking_Student_Dashboard {
    public function __construct() {
        add_action('bp_setup_nav', [$this, 'add_profile_tab']);
        add_shortcode('dnd_student_dashboard', [$this, 'shortcode_dashboard']);
    }

    public function add_profile_tab() {
        bp_core_new_nav_item([
            'name' => __('Speaking Sessions', 'dnd-speaking'),
            'slug' => 'speaking-sessions',
            'screen_function' => [$this, 'screen_content'],
            'position' => 90,
            'default_subnav_slug' => 'sessions',
        ]);
    }

    public function screen_content() {
        add_action('bp_template_content', [$this, 'load_template']);
        bp_core_load_template('buddypress/members/single/plugins');
    }

    public function load_template() {
        include plugin_dir_path(__FILE__) . '../public/partials/dashboard-student.php';
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

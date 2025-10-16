<?php
/**
 * Admin settings for DND Speaking
 */

class DND_Speaking_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_add_credits', [$this, 'handle_add_credits']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'DND Speaking',
            'DND Speaking',
            'manage_options',
            'dnd-speaking',
            [$this, 'admin_page'],
            'dashicons-microphone',
            30
        );

        add_submenu_page(
            'dnd-speaking',
            'Students',
            'Students',
            'manage_options',
            'dnd-speaking-students',
            [$this, 'students_page']
        );

        add_submenu_page(
            'dnd-speaking',
            'Teachers',
            'Teachers',
            'manage_options',
            'dnd-speaking-teachers',
            [$this, 'teachers_page']
        );

        add_submenu_page(
            'dnd-speaking',
            'Sessions',
            'Sessions',
            'manage_options',
            'dnd-speaking-sessions',
            [$this, 'sessions_page']
        );

        add_submenu_page(
            'dnd-speaking',
            'Logs',
            'Logs',
            'manage_options',
            'dnd-speaking-logs',
            [$this, 'logs_page']
        );

        add_submenu_page(
            'dnd-speaking',
            'Settings',
            'Settings',
            'manage_options',
            'dnd-speaking-settings',
            [$this, 'settings_page']
        );
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>DND Speaking</h1>
            <p>Welcome to DND Speaking management.</p>
        </div>
        <?php
    }

    public function students_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_credits';
        $students = $wpdb->get_results("SELECT * FROM $table ORDER BY credits DESC");

        if (isset($_GET['added'])) {
            echo '<div class="notice notice-success"><p>Credits added successfully.</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>Students</h1>
            <h2>Add Credits</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="add_credits">
                <?php wp_nonce_field('add_credits_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="user_id">User ID</label></th>
                        <td><input type="number" name="user_id" id="user_id" required></td>
                    </tr>
                    <tr>
                        <th><label for="credits">Credits to Add</label></th>
                        <td><input type="number" name="credits" id="credits" required></td>
                    </tr>
                </table>
                <?php submit_button('Add Credits'); ?>
            </form>

            <h2>Students List</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Remaining Credits</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo $student->user_id; ?></td>
                            <td><?php echo get_user_by('id', $student->user_id)->display_name; ?></td>
                            <td><?php echo $student->credits; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function teachers_page() {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'dnd_speaking_sessions';
        $teachers = $wpdb->get_results("SELECT teacher_id, COUNT(*) as sessions FROM $table_sessions GROUP BY teacher_id ORDER BY sessions DESC");

        ?>
        <div class="wrap">
            <h1>Teachers</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Sessions Taught</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teachers as $teacher): ?>
                        <tr>
                            <td><?php echo $teacher->teacher_id; ?></td>
                            <td><?php echo get_user_by('id', $teacher->teacher_id)->display_name; ?></td>
                            <td><?php echo $teacher->sessions; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function sessions_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_sessions';
        $sessions = $wpdb->get_results("SELECT * FROM $table ORDER BY start_time DESC");

        ?>
        <div class="wrap">
            <h1>Sessions History</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Teacher</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Duration (min)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td><?php echo $session->id; ?></td>
                            <td><?php echo get_user_by('id', $session->student_id)->display_name; ?></td>
                            <td><?php echo get_user_by('id', $session->teacher_id)->display_name; ?></td>
                            <td><?php echo $session->start_time; ?></td>
                            <td><?php echo $session->end_time ?: 'N/A'; ?></td>
                            <td><?php echo $session->duration; ?></td>
                            <td><?php echo $session->status; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function logs_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_logs';
        $logs = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 100");

        ?>
        <div class="wrap">
            <h1>Logs</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log->id; ?></td>
                            <td><?php echo get_user_by('id', $log->user_id)->display_name; ?></td>
                            <td><?php echo $log->action; ?></td>
                            <td><?php echo $log->details; ?></td>
                            <td><?php echo $log->created_at; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('dnd_speaking_settings');
                do_settings_sections('dnd_speaking_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting('dnd_speaking_settings', 'dnd_session_duration');
        register_setting('dnd_speaking_settings', 'dnd_default_credits');

        add_settings_section(
            'dnd_speaking_main',
            'Main Settings',
            null,
            'dnd_speaking_settings'
        );

        add_settings_field(
            'session_duration',
            'Default Session Duration (minutes)',
            [$this, 'session_duration_field'],
            'dnd_speaking_settings',
            'dnd_speaking_main'
        );

        add_settings_field(
            'default_credits',
            'Default Credits for New Users',
            [$this, 'default_credits_field'],
            'dnd_speaking_settings',
            'dnd_speaking_main'
        );
    }

    public function session_duration_field() {
        $value = get_option('dnd_session_duration', 25);
        echo '<input type="number" name="dnd_session_duration" value="' . esc_attr($value) . '" />';
    }

    public function default_credits_field() {
        $value = get_option('dnd_default_credits', 0);
        echo '<input type="number" name="dnd_default_credits" value="' . esc_attr($value) . '" />';
    }

    public function handle_add_credits() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'add_credits_nonce')) {
            wp_die('Security check failed');
        }

        $user_id = intval($_POST['user_id']);
        $credits = intval($_POST['credits']);

        DND_Speaking_Helpers::add_user_credits($user_id, $credits);
        DND_Speaking_Helpers::log_action(get_current_user_id(), 'add_credits', "Added $credits credits to user $user_id");

        wp_redirect(admin_url('admin.php?page=dnd-speaking-students&added=1'));
        exit;
    }
}
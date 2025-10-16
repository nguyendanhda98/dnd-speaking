<?php
/**
 * Admin settings for DND Speaking
 */

class DND_Speaking_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
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

        ?>
        <div class="wrap">
            <h1>Students</h1>
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
}
<?php
/**
 * Admin settings for DND Speaking
 */

class DND_Speaking_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
        add_action('wp_ajax_update_teacher_availability', [$this, 'update_teacher_availability']);
        add_action('wp_ajax_handle_teacher_request', [$this, 'handle_teacher_request']);
        add_action('wp_ajax_handle_upcoming_session', [$this, 'handle_upcoming_session']);
        add_action('wp_ajax_save_teacher_schedule', [$this, 'save_teacher_schedule']);
        add_filter('pre_update_option_dnd_discord_bot_token', [$this, 'validate_discord_bot_token'], 10, 2);
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
                        <th><label for="user_id">Select Student</label></th>
                        <td>
                            <select name="user_id" id="user_id" required>
                                <option value="">Choose a student...</option>
                                <?php
                                // Get all users except administrators
                                $users = get_users([
                                    'role__not_in' => ['administrator'],
                                    'orderby' => 'display_name',
                                    'order' => 'ASC'
                                ]);
                                foreach ($users as $user) {
                                    echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . ' (' . esc_html($user->user_login) . ')</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="credits">Credits to Add</label></th>
                        <td><input type="number" name="credits" id="credits" required min="1"></td>
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
        $teacher_role = get_option('dnd_teacher_role', 'teacher');
        
        // Get all users with teacher role
        $users = get_users(['role' => $teacher_role]);
        
        // Get session counts for each teacher
        $session_counts = $wpdb->get_results("SELECT teacher_id, COUNT(*) as sessions FROM $table_sessions GROUP BY teacher_id", ARRAY_A);
        $session_count_map = [];
        foreach ($session_counts as $count) {
            $session_count_map[$count['teacher_id']] = $count['sessions'];
        }

        ?>
        <div class="wrap">
            <h1>Teachers (Role: <?php echo esc_html(wp_roles()->get_names()[$teacher_role] ?? $teacher_role); ?>)</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Sessions Taught</th>
                        <th>Available</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): 
                        $sessions = $session_count_map[$user->ID] ?? 0;
                        $available = get_user_meta($user->ID, 'dnd_available', true) == '1';
                    ?>
                        <tr>
                            <td><?php echo $user->ID; ?></td>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><?php echo $sessions; ?></td>
                            <td><?php echo $available ? 'Yes' : 'No'; ?></td>
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
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'main';
        $active_sub_tab = isset($_GET['sub_tab']) ? $_GET['sub_tab'] : 'app_details';
        ?>
        <div class="wrap">
            <h1>Settings</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=dnd-speaking-settings&tab=main" class="nav-tab <?php echo $active_tab == 'main' ? 'nav-tab-active' : ''; ?>">Main</a>
                <a href="?page=dnd-speaking-settings&tab=discord" class="nav-tab <?php echo $active_tab == 'discord' ? 'nav-tab-active' : ''; ?>">Discord</a>
            </h2>
            <?php if ($active_tab == 'discord'): ?>
            <h2 class="nav-tab-wrapper">
                <a href="?page=dnd-speaking-settings&tab=discord&sub_tab=app_details" class="nav-tab <?php echo $active_sub_tab == 'app_details' ? 'nav-tab-active' : ''; ?>">Application Details</a>
                <a href="?page=dnd-speaking-settings&tab=discord&sub_tab=advanced" class="nav-tab <?php echo $active_sub_tab == 'advanced' ? 'nav-tab-active' : ''; ?>">Advanced</a>
            </h2>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                if ($active_tab == 'main') {
                    settings_fields('dnd_speaking_settings');
                    do_settings_sections('dnd_speaking_settings');
                    submit_button();
                } elseif ($active_tab == 'discord') {
                    settings_fields('dnd_speaking_discord_settings');
                    if ($active_sub_tab == 'app_details') {
                        ?>
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px;">
                            <label for="dnd_discord_client_id" style="width: 150px; font-weight: bold;">Client ID</label>
                            <input type="text" id="dnd_discord_client_id" name="dnd_discord_client_id" value="<?php echo esc_attr(get_option('dnd_discord_client_id')); ?>" style="max-width: 300px;" />
                        </div>
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px;">
                            <label for="dnd_discord_client_secret" style="width: 150px; font-weight: bold;">Client Secret</label>
                            <input type="password" id="dnd_discord_client_secret" name="dnd_discord_client_secret" value="<?php echo esc_attr(get_option('dnd_discord_client_secret')); ?>" style="max-width: 300px;" />
                        </div>
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px;">
                            <label for="dnd_discord_redirect_url" style="width: 150px; font-weight: bold;">Redirect URL</label>
                            <input type="url" id="dnd_discord_redirect_url" name="dnd_discord_redirect_url" value="<?php echo esc_attr(get_option('dnd_discord_redirect_url')); ?>" style="max-width: 300px;" />
                        </div>
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px;">
                            <label for="dnd_discord_admin_redirect_url" style="width: 150px; font-weight: bold;">Admin Redirect URL</label>
                            <input type="url" id="dnd_discord_admin_redirect_url" name="dnd_discord_admin_redirect_url" value="<?php echo esc_attr(get_option('dnd_discord_admin_redirect_url')); ?>" style="max-width: 300px;" />
                        </div>
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px;">
                            <label for="dnd_discord_bot_token" style="width: 150px; font-weight: bold;">Bot Token</label>
                            <input type="password" id="dnd_discord_bot_token" name="dnd_discord_bot_token" value="<?php echo esc_attr(get_option('dnd_discord_bot_token')); ?>" style="max-width: 300px;" />
                        </div>
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px;">
                            <label for="dnd_discord_server_id" style="width: 150px; font-weight: bold;">Server ID</label>
                            <input type="text" id="dnd_discord_server_id" name="dnd_discord_server_id" value="<?php echo esc_attr(get_option('dnd_discord_server_id')); ?>" style="max-width: 300px;" />
                        </div>
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px;">
                            <label for="dnd_discord_connect_to_bot" style="width: 150px; font-weight: bold;">Connect to Bot</label>
                            <input type="checkbox" id="dnd_discord_connect_to_bot" name="dnd_discord_connect_to_bot" value="1" <?php checked(1, get_option('dnd_discord_connect_to_bot'), true); ?> />
                        </div>
                        <?php
                        $this->display_bot_status();
                        ?>
                        <?php
                    } elseif ($active_sub_tab == 'advanced') {
                        echo '<p>Advanced settings will be added here.</p>';
                    }
                    ?>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <?php submit_button(); ?>
                    </div>
                    <?php
                }
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting('dnd_speaking_settings', 'dnd_session_duration');
        register_setting('dnd_speaking_settings', 'dnd_default_credits');
        register_setting('dnd_speaking_settings', 'dnd_teacher_role');

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

        add_settings_field(
            'teacher_role',
            'Teacher Role',
            [$this, 'teacher_role_field'],
            'dnd_speaking_settings',
            'dnd_speaking_main'
        );

        // Discord settings
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_client_id');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_client_secret');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_redirect_url');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_admin_redirect_url');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_bot_token');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_server_id');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_connect_to_bot');

        add_settings_section(
            'dnd_speaking_discord_app_details',
            'Application Details',
            null,
            'dnd_speaking_discord_settings'
        );

        add_settings_section(
            'dnd_speaking_discord_advanced',
            'Advanced',
            null,
            'dnd_speaking_discord_settings'
        );

        add_settings_field(
            'discord_client_id',
            'Client ID',
            [$this, 'discord_client_id_field'],
            'dnd_speaking_discord_settings',
            'dnd_speaking_discord_app_details'
        );

        add_settings_field(
            'discord_client_secret',
            'Client Secret',
            [$this, 'discord_client_secret_field'],
            'dnd_speaking_discord_settings',
            'dnd_speaking_discord_app_details'
        );

        add_settings_field(
            'discord_redirect_url',
            'Redirect URL',
            [$this, 'discord_redirect_url_field'],
            'dnd_speaking_discord_settings',
            'dnd_speaking_discord_app_details'
        );

        add_settings_field(
            'discord_admin_redirect_url',
            'Admin Redirect URL',
            [$this, 'discord_admin_redirect_url_field'],
            'dnd_speaking_discord_settings',
            'dnd_speaking_discord_app_details'
        );

        add_settings_field(
            'discord_connect_to_bot',
            'Connect to Bot',
            [$this, 'discord_connect_to_bot_field'],
            'dnd_speaking_discord_settings',
            'dnd_speaking_discord_app_details'
        );

        add_settings_field(
            'discord_bot_token',
            'Bot Token',
            [$this, 'discord_bot_token_field'],
            'dnd_speaking_discord_settings',
            'dnd_speaking_discord_app_details'
        );

        add_settings_field(
            'discord_server_id',
            'Server ID',
            [$this, 'discord_server_id_field'],
            'dnd_speaking_discord_settings',
            'dnd_speaking_discord_app_details'
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

    public function teacher_role_field() {
        $value = get_option('dnd_teacher_role', 'teacher');
        $roles = wp_roles()->roles;
        
        echo '<select name="dnd_teacher_role">';
        foreach ($roles as $role_key => $role) {
            $selected = ($value === $role_key) ? 'selected' : '';
            echo '<option value="' . esc_attr($role_key) . '" ' . $selected . '>' . esc_html($role['name']) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Select which WordPress role should be considered as teachers.</p>';
    }

    public function discord_client_id_field() {
        $value = get_option('dnd_discord_client_id', '');
        echo '<input type="text" name="dnd_discord_client_id" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function discord_client_secret_field() {
        $value = get_option('dnd_discord_client_secret', '');
        echo '<input type="password" name="dnd_discord_client_secret" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function discord_redirect_url_field() {
        $value = get_option('dnd_discord_redirect_url', '');
        echo '<input type="url" name="dnd_discord_redirect_url" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function discord_admin_redirect_url_field() {
        $value = get_option('dnd_discord_admin_redirect_url', '');
        echo '<input type="url" name="dnd_discord_admin_redirect_url" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function discord_connect_to_bot_field() {
        $value = get_option('dnd_discord_connect_to_bot', '');
        echo '<input type="checkbox" name="dnd_discord_connect_to_bot" value="1" ' . checked(1, $value, false) . ' /> Enable connection to bot';
    }

    public function discord_bot_token_field() {
        $value = get_option('dnd_discord_bot_token', '');
        echo '<input type="password" name="dnd_discord_bot_token" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function discord_server_id_field() {
        $value = get_option('dnd_discord_server_id', '');
        echo '<input type="text" name="dnd_discord_server_id" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function check_discord_bot_token($token) {
        if (empty($token)) {
            return 'No token provided';
        }
        $response = wp_remote_get('https://discord.com/api/v10/users/@me', array(
            'headers' => array(
                'Authorization' => 'Bot ' . $token,
                'User-Agent' => 'DND Speaking Plugin/1.0'
            ),
            'timeout' => 10
        ));
        if (is_wp_error($response)) {
            return 'Connection error: ' . $response->get_error_message();
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            return true; // Valid
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (isset($data['message'])) {
                return 'Error: ' . $data['message'];
            } else {
                return 'Error: HTTP ' . $code;
            }
        }
    }

    public function display_bot_status() {
        $bot_token = get_option('dnd_discord_bot_token', '');
        $connect_to_bot = get_option('dnd_discord_connect_to_bot', '');

        $check = $this->check_discord_bot_token($bot_token);
        if ($check === true && $connect_to_bot) {
            $status_text = 'Connected';
            $color = 'green';
        } else {
            if ($check === true) {
                $status_text = 'Token valid but not connected';
            } else {
                $status_text = $check; // Error message
            }
            $color = 'red';
        }

        echo '<div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px;">
            <label style="width: 150px; font-weight: bold;">Bot status</label>
            <span style="color: ' . $color . ';">' . $status_text . '</span>
        </div>';
    }

    public function validate_discord_bot_token($new_value, $old_value) {
        if (empty($new_value)) {
            return $new_value; // Allow empty
        }
        // Simple validation: check if it's a string
        if (!is_string($new_value)) {
            add_settings_error('dnd_discord_bot_token', 'invalid_token', 'Bot Token must be a valid string.');
            return $old_value;
        }
        // Here you could add API call to validate token
        // For now, assume valid
        return $new_value;
    }

    public function display_admin_notices() {
        settings_errors('dnd_speaking_discord_settings');
    }

    public function handle_add_credits() {
        // Check nonce for security
        if (!wp_verify_nonce($_POST['_wpnonce'], 'add_credits_nonce')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $user_id = intval($_POST['user_id']);
        $credits = intval($_POST['credits']);

        // Validate input
        if ($user_id <= 0 || $credits <= 0) {
            wp_die('Invalid input');
        }

        // Add credits using helper function
        DND_Speaking_Helpers::add_user_credits($user_id, $credits);

        // Log the action
        DND_Speaking_Helpers::log_action(get_current_user_id(), 'add_credits', "Added $credits credits to user $user_id");

        // Redirect back with success message
        wp_redirect(admin_url('admin.php?page=dnd-speaking-students&added=1'));
        exit;
    }

    public function update_teacher_availability() {
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'update_teacher_availability_nonce')) {
            wp_die('Security check failed');
        }

        $user_id = intval($_POST['user_id']);
        $available = intval($_POST['available']);

        // Only allow users to update their own availability
        if ($user_id !== get_current_user_id()) {
            wp_die('Unauthorized');
        }

        update_user_meta($user_id, 'dnd_available', $available);

        wp_send_json_success(['available' => $available]);
    }

    public function handle_teacher_request() {
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'teacher_requests_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $session_id = intval($_POST['session_id']);
        $action = sanitize_text_field($_POST['request_action']);
        $teacher_id = get_current_user_id();

        // Validate action
        if (!in_array($action, ['accept', 'decline'])) {
            wp_send_json_error('Invalid action');
        }

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        // Get the session and verify it belongs to this teacher
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sessions_table WHERE id = %d AND teacher_id = %d AND status = 'pending'",
            $session_id, $teacher_id
        ));

        if (!$session) {
            wp_send_json_error('Session not found or already processed');
        }

        // Update session status
        $new_status = ($action === 'accept') ? 'confirmed' : 'declined';

        $result = $wpdb->update(
            $sessions_table,
            ['status' => $new_status],
            ['id' => $session_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            wp_send_json_error('Failed to update session');
        }

        // If declined, we might want to refund credits or notify the student
        if ($action === 'decline') {
            // Optionally refund credits to student
            // This would depend on your business logic
        }

        wp_send_json_success(['status' => $new_status]);
    }

    public function handle_upcoming_session() {
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'upcoming_sessions_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $session_id = intval($_POST['session_id']);
        $action = sanitize_text_field($_POST['session_action']);
        $teacher_id = get_current_user_id();

        // Validate action
        if (!in_array($action, ['start', 'cancel'])) {
            wp_send_json_error('Invalid action');
        }

        global $wpdb;
        $sessions_table = $wpdb->prefix . 'dnd_speaking_sessions';

        // Get the session and verify it belongs to this teacher
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sessions_table WHERE id = %d AND teacher_id = %d AND status = 'confirmed'",
            $session_id, $teacher_id
        ));

        if (!$session) {
            wp_send_json_error('Session not found or not confirmed');
        }

        if ($action === 'start') {
            // Update session status to 'active'
            $result = $wpdb->update(
                $sessions_table,
                ['status' => 'active'],
                ['id' => $session_id],
                ['%s'],
                ['%d']
            );

            if ($result === false) {
                wp_send_json_error('Failed to start session');
            }

            wp_send_json_success(['status' => 'active', 'action' => 'started']);

        } elseif ($action === 'cancel') {
            // Update session status to 'cancelled'
            $result = $wpdb->update(
                $sessions_table,
                ['status' => 'cancelled'],
                ['id' => $session_id],
                ['%s'],
                ['%d']
            );

            if ($result === false) {
                wp_send_json_error('Failed to cancel session');
            }

            // Optionally refund credits to student
            // This would depend on your business logic

            wp_send_json_success(['status' => 'cancelled', 'action' => 'cancelled']);
        }
    }

    public function save_teacher_schedule() {
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'schedule_settings_nonce')) {
            wp_send_json_error('Security check failed');
        }

        $user_id = get_current_user_id();
        $schedule_data = json_decode(stripslashes($_POST['schedule_data']), true);

        // Validate schedule data
        if (!$schedule_data || !is_array($schedule_data)) {
            wp_send_json_error('Invalid schedule data');
        }

        // Validate each day's data
        $valid_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $validated_schedule = [];

        foreach ($valid_days as $day) {
            if (isset($schedule_data[$day])) {
                $day_data = $schedule_data[$day];

                $validated_schedule[$day] = [
                    'enabled' => isset($day_data['enabled']) ? (bool)$day_data['enabled'] : false,
                    'time_slots' => []
                ];

                if ($validated_schedule[$day]['enabled'] && isset($day_data['time_slots']) && is_array($day_data['time_slots'])) {
                    foreach ($day_data['time_slots'] as $slot) {
                        if (isset($slot['start']) && isset($slot['end'])) {
                            $start_time = $slot['start'];
                            $end_time = $slot['end'];

                            // Validate time format (HH:MM)
                            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start_time) ||
                                !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end_time)) {
                                wp_send_json_error('Invalid time format');
                            }

                            // Validate that start is before end
                            if (strtotime($start_time) >= strtotime($end_time)) {
                                wp_send_json_error('Start time must be before end time');
                            }

                            $validated_schedule[$day]['time_slots'][] = [
                                'start' => $start_time,
                                'end' => $end_time
                            ];
                        }
                    }

                    // Sort time slots by start time
                    usort($validated_schedule[$day]['time_slots'], function($a, $b) {
                        return strtotime($a['start']) - strtotime($b['start']);
                    });

                    // Ensure at least one time slot if enabled
                    if (empty($validated_schedule[$day]['time_slots'])) {
                        $validated_schedule[$day]['time_slots'][] = [
                            'start' => '09:00',
                            'end' => '17:00'
                        ];
                    }
                } else {
                    // Default time slot for disabled or missing days
                    $validated_schedule[$day]['time_slots'][] = [
                        'start' => '09:00',
                        'end' => '17:00'
                    ];
                }
            } else {
                // Default values for missing days
                $validated_schedule[$day] = [
                    'enabled' => false,
                    'time_slots' => [['start' => '09:00', 'end' => '17:00']]
                ];
            }
        }

        // Save to user meta
        $result = update_user_meta($user_id, 'dnd_weekly_schedule', $validated_schedule);

        if ($result === false) {
            wp_send_json_error('Failed to save schedule');
        }

        wp_send_json_success(['message' => 'Schedule saved successfully']);
    }

    public function get_teacher_availability_days() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'get_teacher_availability_days')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $teacher_id = intval($_POST['teacher_id']);
        
        // Get available days
        $available_days = get_user_meta($teacher_id, 'dnd_available_days', true);
        if (empty($available_days) || !is_array($available_days)) {
            $available_days = [1, 2, 3, 4, 5, 6, 7]; // Default all days
        }

        wp_send_json_success($available_days);
    }

    public function handle_update_teacher_availability() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'update_availability_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $teacher_id = intval($_POST['teacher_id']);
        $available_days = isset($_POST['available_days']) ? array_map('intval', $_POST['available_days']) : [];

        // Update user meta
        update_user_meta($teacher_id, 'dnd_available_days', $available_days);

        // Redirect back to teachers page with success message
        wp_redirect(add_query_arg('updated', 'availability', admin_url('admin.php?page=dnd-speaking-teachers')));
        exit;
    }
}
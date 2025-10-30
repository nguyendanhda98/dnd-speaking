<?php
/**
 * Admin settings for DND Speaking
 */

class DND_Speaking_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_post_bulk_add_lessons', [$this, 'handle_bulk_add_lessons']);
        add_action('admin_post_bulk_remove_lessons', [$this, 'handle_bulk_remove_lessons']);
        add_action('wp_ajax_update_teacher_availability', [$this, 'update_teacher_availability']);
        add_action('wp_ajax_handle_teacher_request', [$this, 'handle_teacher_request']);
        add_action('wp_ajax_handle_upcoming_session', [$this, 'handle_upcoming_session']);
        add_action('wp_ajax_save_teacher_schedule', [$this, 'save_teacher_schedule']);
        add_action('wp_ajax_get_pages', [$this, 'get_pages']);
        add_action('wp_ajax_load_students_list', [$this, 'ajax_load_students_list']);
        add_action('wp_ajax_save_teacher_youtube_url', [$this, 'save_teacher_youtube_url']);
        add_filter('pre_update_option_dnd_discord_bot_token', [$this, 'validate_discord_bot_token'], 10, 2);
        add_action('user_register', [$this, 'auto_assign_lessons_to_new_user']);
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
        
        // Check if viewing specific student details
        if (isset($_GET['student_id'])) {
            $this->student_details_page(intval($_GET['student_id']));
            return;
        }
        
        // Pagination settings
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
        $per_page = in_array($per_page, [1, 3, 5, 10, 20, 50, 100]) ? $per_page : 10;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Get total count
        $total_students = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $total_pages = ceil($total_students / $per_page);
        
        // Get students with pagination
        $students = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY credits DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        // Display notices
        if (isset($_GET['bulk_added'])) {
            $count = intval($_GET['bulk_added']);
            echo '<div class="notice notice-success is-dismissible"><p>✅ Đã thêm buổi học cho <strong>' . $count . '</strong> học viên.</p></div>';
        }
        if (isset($_GET['bulk_removed'])) {
            $count = intval($_GET['bulk_removed']);
            echo '<div class="notice notice-success is-dismissible"><p>✅ Đã trừ buổi học cho <strong>' . $count . '</strong> học viên.</p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>❌ Có lỗi xảy ra. Vui lòng kiểm tra logs.</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>Quản Lý Học Viên</h1>
            
            <!-- Manage Lessons Form -->
            <h2>Quản Lý Buổi Học</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="manage-lessons-form" novalidate>
                <input type="hidden" name="action" id="manage-action" value="bulk_add_lessons">
                <?php wp_nonce_field('bulk_lessons_nonce'); ?>
                
                <!-- Hidden field to store selected user IDs -->
                <input type="hidden" name="user_ids_hidden" id="user_ids_hidden" value="">
                
                <table class="form-table">
                    <tr>
                        <th><label>Chọn Học Viên</label></th>
                        <td>
                            <!-- Search Box -->
                            <div style="margin-bottom: 10px;">
                                <input type="text" id="student_search" placeholder="Tìm kiếm học viên (nhập tên hoặc username)..." style="width: 400px; padding: 5px;">
                            </div>
                            
                            <!-- Search Results -->
                            <div id="search_results" style="display: none; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; width: 400px; background: white; margin-bottom: 10px;">
                                <!-- Results will be populated here -->
                            </div>
                            
                            <!-- Selected Students -->
                            <div id="selected_students" style="border: 1px solid #ddd; min-height: 100px; max-height: 300px; overflow-y: auto; width: 400px; padding: 10px; background: #f9f9f9;">
                                <div id="selected_students_list">
                                    <p style="color: #666; font-style: italic;">Chưa chọn học viên nào. Sử dụng ô tìm kiếm bên trên để thêm học viên.</p>
                                </div>
                            </div>
                            <p class="description">Tìm kiếm và click vào học viên để thêm vào danh sách đã chọn</p>
                            
                            <!-- All users data for JavaScript -->
                            <script type="text/javascript">
                            var allStudents = [
                                <?php
                                // Get all users except administrators
                                $users = get_users([
                                    'role__not_in' => ['administrator'],
                                    'orderby' => 'display_name',
                                    'order' => 'ASC'
                                ]);
                                $student_data = [];
                                foreach ($users as $user) {
                                    $current_lessons = DND_Speaking_Helpers::get_user_lessons($user->ID);
                                    $student_data[] = sprintf(
                                        '{id: %d, name: "%s", username: "%s", lessons: %d}',
                                        $user->ID,
                                        esc_js($user->display_name),
                                        esc_js($user->user_login),
                                        $current_lessons
                                    );
                                }
                                echo implode(",\n                                ", $student_data);
                                ?>
                            ];
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="credits">Số Buổi Học</label></th>
                        <td>
                            <input type="number" name="credits" id="credits" required min="1" value="1" style="width: 100px;">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="apply_to_all">Áp Dụng Cho Toàn Bộ</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="apply_to_all" id="apply_to_all" value="1">
                                Áp dụng cho toàn bộ học viên (bỏ qua lựa chọn bên trên)
                            </label>
                            <p class="description" style="color: #d63638;">
                                <strong>⚠️ Cẩn thận:</strong> Khi chọn option này, buổi học sẽ được thêm/trừ cho TẤT CẢ học viên trong hệ thống!
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" onclick="return handleAddLessons()">
                        ➕ Thêm Buổi Học
                    </button>
                    <button type="button" class="button button-secondary" onclick="handleRemoveLessons()" style="margin-left: 10px;">
                        ➖ Trừ Buổi Học
                    </button>
                </p>
            </form>
            
            <style type="text/css">
            .search-result-item {
                padding: 8px 10px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
            }
            .search-result-item:hover {
                background-color: #f0f0f0;
            }
            .selected-student-tag {
                display: inline-block;
                background: #0073aa;
                color: white;
                padding: 5px 10px;
                margin: 5px 5px 5px 0;
                border-radius: 3px;
                font-size: 13px;
            }
            .selected-student-tag .remove-student {
                margin-left: 8px;
                cursor: pointer;
                font-weight: bold;
                color: #fff;
            }
            .selected-student-tag .remove-student:hover {
                color: #ff6b6b;
            }
            </style>
            
            <script type="text/javascript">
            // Selected students array
            var selectedStudents = [];
            
            // Search functionality
            document.getElementById('student_search').addEventListener('input', function(e) {
                var searchTerm = e.target.value.toLowerCase().trim();
                var resultsDiv = document.getElementById('search_results');
                
                if (searchTerm.length === 0) {
                    resultsDiv.style.display = 'none';
                    return;
                }
                
                // Filter students
                var filtered = allStudents.filter(function(student) {
                    return student.name.toLowerCase().includes(searchTerm) || 
                           student.username.toLowerCase().includes(searchTerm);
                });
                
                // Display results
                if (filtered.length === 0) {
                    resultsDiv.innerHTML = '<div style="padding: 10px; color: #666;">Không tìm thấy học viên nào</div>';
                    resultsDiv.style.display = 'block';
                } else {
                    var html = '';
                    filtered.forEach(function(student) {
                        // Check if already selected
                        var isSelected = selectedStudents.some(function(s) { return s.id === student.id; });
                        if (!isSelected) {
                            html += '<div class="search-result-item" onclick="addStudent(' + student.id + ')">' +
                                   '<strong>' + student.name + '</strong> (' + student.username + ')' +
                                   '<span style="float: right; color: #666;">' + student.lessons + ' buổi</span>' +
                                   '</div>';
                        }
                    });
                    
                    if (html === '') {
                        resultsDiv.innerHTML = '<div style="padding: 10px; color: #666;">Tất cả kết quả đã được chọn</div>';
                    } else {
                        resultsDiv.innerHTML = html;
                    }
                    resultsDiv.style.display = 'block';
                }
            });
            
            // Hide search results when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#student_search') && !e.target.closest('#search_results')) {
                    document.getElementById('search_results').style.display = 'none';
                }
            });
            
            // Add student to selection
            function addStudent(studentId) {
                var student = allStudents.find(function(s) { return s.id === studentId; });
                if (!student) return;
                
                // Check if already selected
                if (selectedStudents.some(function(s) { return s.id === studentId; })) {
                    return;
                }
                
                // Add to selected list
                selectedStudents.push(student);
                updateSelectedStudentsList();
                
                // Clear search
                document.getElementById('student_search').value = '';
                document.getElementById('search_results').style.display = 'none';
            }
            
            // Remove student from selection
            function removeStudent(studentId) {
                selectedStudents = selectedStudents.filter(function(s) { return s.id !== studentId; });
                updateSelectedStudentsList();
            }
            
            // Update selected students display
            function updateSelectedStudentsList() {
                var listDiv = document.getElementById('selected_students_list');
                var hiddenInput = document.getElementById('user_ids_hidden');
                
                if (selectedStudents.length === 0) {
                    listDiv.innerHTML = '<p style="color: #666; font-style: italic;">Chưa chọn học viên nào. Sử dụng ô tìm kiếm bên trên để thêm học viên.</p>';
                    hiddenInput.value = '';
                } else {
                    var html = '';
                    var ids = [];
                    selectedStudents.forEach(function(student) {
                        html += '<span class="selected-student-tag">' +
                               student.name + ' (' + student.lessons + ' buổi)' +
                               '<span class="remove-student" onclick="removeStudent(' + student.id + ')">×</span>' +
                               '</span>';
                        ids.push(student.id);
                    });
                    listDiv.innerHTML = html;
                    hiddenInput.value = ids.join(',');
                }
            }
            
            function handleAddLessons() {
                var applyToAll = document.getElementById('apply_to_all').checked;
                var credits = document.getElementById('credits').value;
                
                if (applyToAll) {
                    var totalUsers = allStudents.length;
                    if (!confirm('Bạn có chắc muốn THÊM ' + credits + ' buổi học cho TẤT CẢ ' + totalUsers + ' học viên không?')) {
                        return false;
                    }
                } else if (selectedStudents.length === 0) {
                    alert('Vui lòng chọn ít nhất một học viên hoặc tích "Áp dụng cho toàn bộ"');
                    return false;
                } else if (selectedStudents.length > 10) {
                    if (!confirm('Bạn đã chọn ' + selectedStudents.length + ' học viên. Bạn có chắc muốn THÊM ' + credits + ' buổi học cho tất cả những người này không?')) {
                        return false;
                    }
                }
                
                document.getElementById('manage-action').value = 'bulk_add_lessons';
                return true;
            }
            
            function handleRemoveLessons() {
                console.log('handleRemoveLessons called');
                var applyToAll = document.getElementById('apply_to_all').checked;
                var credits = document.getElementById('credits').value;
                
                console.log('Apply to all:', applyToAll);
                console.log('Credits:', credits);
                console.log('Selected students:', selectedStudents.length);
                
                if (applyToAll) {
                    var totalUsers = allStudents.length;
                    if (!confirm('⚠️ CẢNH BÁO: Bạn có chắc muốn TRỪ ' + credits + ' buổi học từ TẤT CẢ ' + totalUsers + ' học viên không?\n\nHành động này không thể hoàn tác!')) {
                        console.log('User cancelled (apply to all)');
                        return false;
                    }
                } else if (selectedStudents.length === 0) {
                    alert('Vui lòng chọn ít nhất một học viên hoặc tích "Áp dụng cho toàn bộ"');
                    console.log('No students selected');
                    return false;
                } else {
                    if (!confirm('Bạn có chắc muốn TRỪ ' + credits + ' buổi học từ ' + selectedStudents.length + ' học viên đã chọn không?\n\nHọc viên không đủ số buổi sẽ bị bỏ qua.')) {
                        console.log('User cancelled');
                        return false;
                    }
                }
                
                // Set action and submit form using proper method
                console.log('Setting action to bulk_remove_lessons');
                document.getElementById('manage-action').value = 'bulk_remove_lessons';
                console.log('Submitting form...');
                
                // Use HTMLFormElement.prototype.submit to avoid conflicts
                var form = document.getElementById('manage-lessons-form');
                HTMLFormElement.prototype.submit.call(form);
                
                return false; // Prevent default button behavior
            }
            </script>

            <hr>

            <!-- Students List -->
            <h2>Danh Sách Học Viên</h2>
            
            <!-- Pagination Controls Top -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <label for="per-page-selector">Hiển thị:</label>
                    <select id="per-page-selector" style="width: auto;">
                        <option value="1" <?php selected($per_page, 1); ?>>1 học viên</option>
                        <option value="3" <?php selected($per_page, 3); ?>>3 học viên</option>
                        <option value="5" <?php selected($per_page, 5); ?>>5 học viên</option>
                        <option value="10" <?php selected($per_page, 10); ?>>10 học viên</option>
                        <option value="20" <?php selected($per_page, 20); ?>>20 học viên</option>
                        <option value="50" <?php selected($per_page, 50); ?>>50 học viên</option>
                        <option value="100" <?php selected($per_page, 100); ?>>100 học viên</option>
                    </select>
                    <span id="total-students-display" style="margin-left: 10px; color: #666;">
                        Tổng: <strong><?php echo $total_students; ?></strong> học viên
                    </span>
                </div>
                
                <div id="pagination-top-container">
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo $total_students; ?> mục</span>
                        <span class="pagination-links">
                            <?php
                            // First page
                            if ($current_page > 1) {
                                echo '<a class="first-page button" href="#" data-page="1"><span aria-hidden="true">«</span></a>';
                                echo '<a class="prev-page button" href="#" data-page="' . ($current_page - 1) . '"><span aria-hidden="true">‹</span></a>';
                            } else {
                                echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
                                echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
                            }
                            
                            // Current page
                            echo '<span class="paging-input">';
                            echo '<label for="current-page-selector" class="screen-reader-text">Trang hiện tại</label>';
                            echo '<input class="current-page" id="current-page-selector" type="text" name="paged" value="' . $current_page . '" size="2" aria-describedby="table-paging">';
                            echo '<span class="tablenav-paging-text"> / <span class="total-pages">' . $total_pages . '</span></span>';
                            echo '</span>';
                            
                            // Last page
                            if ($current_page < $total_pages) {
                                echo '<a class="next-page button" href="#" data-page="' . ($current_page + 1) . '"><span aria-hidden="true">›</span></a>';
                                echo '<a class="last-page button" href="#" data-page="' . $total_pages . '"><span aria-hidden="true">»</span></a>';
                            } else {
                                echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                                echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
                            }
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
                </div>
            </div>
            
            <!-- Students Table Container -->
            <div id="students-table-container">
            <!-- Students Table Container -->
            <div id="students-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Tên Học Viên</th>
                            <th style="width: 120px;">Buổi Học Còn Lại</th>
                            <th style="width: 150px;">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 20px; color: #999;">
                                    Không có học viên nào
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): 
                                $user = get_userdata($student->user_id);
                                if (!$user) continue;
                            ?>
                                <tr>
                                    <td><?php echo $student->user_id; ?></td>
                                    <td><?php echo esc_html($user->display_name); ?></td>
                                    <td><strong><?php echo $student->credits; ?></strong></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=dnd-speaking-students&student_id=' . $student->user_id); ?>" class="button button-primary">
                                            Xem Chi Tiết
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls Bottom -->
            <div id="pagination-bottom-container">
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo $total_students; ?> mục</span>
                        <span class="pagination-links">
                            <?php
                            // First page
                            if ($current_page > 1) {
                                echo '<a class="first-page button" href="#" data-page="1"><span aria-hidden="true">«</span></a>';
                                echo '<a class="prev-page button" href="#" data-page="' . ($current_page - 1) . '"><span aria-hidden="true">‹</span></a>';
                            } else {
                                echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
                                echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
                            }
                            
                            // Current page
                            echo '<span class="paging-input">';
                            echo '<label for="current-page-selector-bottom" class="screen-reader-text">Trang hiện tại</label>';
                            echo '<input class="current-page" id="current-page-selector-bottom" type="text" name="paged" value="' . $current_page . '" size="2" aria-describedby="table-paging">';
                            echo '<span class="tablenav-paging-text"> / <span class="total-pages">' . $total_pages . '</span></span>';
                            echo '</span>';
                            
                            // Last page
                            if ($current_page < $total_pages) {
                                echo '<a class="next-page button" href="#" data-page="' . ($current_page + 1) . '"><span aria-hidden="true">›</span></a>';
                                echo '<a class="last-page button" href="#" data-page="' . $total_pages . '"><span aria-hidden="true">»</span></a>';
                            } else {
                                echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                                echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            <?php endif; ?>
            </div>
            
            <script type="text/javascript">
            // AJAX Pagination
            var currentPage = <?php echo $current_page; ?>;
            var perPage = <?php echo $per_page; ?>;
            var isLoading = false;
            
            function loadStudentsList(page, per_page) {
                if (isLoading) return;
                isLoading = true;
                
                // Show loading state
                var tableContainer = document.getElementById('students-table-container');
                tableContainer.style.opacity = '0.5';
                tableContainer.style.pointerEvents = 'none';
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'load_students_list',
                        nonce: '<?php echo wp_create_nonce('dnd_students_list_nonce'); ?>',
                        per_page: per_page,
                        paged: page
                    },
                    success: function(response) {
                        console.log('AJAX Response:', response);
                        if (response.success) {
                            console.log('Updating table...');
                            // Update table
                            tableContainer.innerHTML = response.data.table_html;
                            
                            // Update pagination controls
                            var paginationTopContainer = document.getElementById('pagination-top-container');
                            var paginationBottomContainer = document.getElementById('pagination-bottom-container');
                            
                            console.log('Pagination top container:', paginationTopContainer);
                            console.log('Pagination bottom container:', paginationBottomContainer);
                            console.log('Pagination HTML:', response.data.pagination_html);
                            
                            if (paginationTopContainer) {
                                paginationTopContainer.innerHTML = response.data.pagination_html || '';
                            }
                            
                            if (paginationBottomContainer) {
                                if (response.data.pagination_html) {
                                    paginationBottomContainer.innerHTML = '<div class="tablenav bottom">' + response.data.pagination_html + '</div>';
                                } else {
                                    paginationBottomContainer.innerHTML = '';
                                }
                            }
                            
                            // Update total students display
                            var totalStudentsDisplay = document.getElementById('total-students-display');
                            if (totalStudentsDisplay) {
                                totalStudentsDisplay.innerHTML = 'Tổng: <strong>' + response.data.total_students + '</strong> học viên';
                            }
                            
                            // Update per_page selector
                            var perPageSelector = document.getElementById('per-page-selector');
                            if (perPageSelector) {
                                perPageSelector.value = response.data.per_page;
                            }
                            
                            // Update current state
                            currentPage = response.data.current_page;
                            perPage = response.data.per_page;
                            
                            // Re-bind event listeners
                            bindPaginationEvents();
                            
                            // Remove loading state
                            tableContainer.style.opacity = '1';
                            tableContainer.style.pointerEvents = 'auto';
                        } else {
                            alert('Có lỗi xảy ra: ' + (response.data.message || 'Unknown error'));
                        }
                        isLoading = false;
                    },
                    error: function() {
                        alert('Có lỗi xảy ra khi tải danh sách học viên');
                        tableContainer.style.opacity = '1';
                        tableContainer.style.pointerEvents = 'auto';
                        isLoading = false;
                    }
                });
            }
            
            function bindPaginationEvents() {
                // Bind pagination links
                jQuery('#pagination-top-container a[data-page], #pagination-bottom-container a[data-page]').on('click', function(e) {
                    e.preventDefault();
                    var page = parseInt(jQuery(this).data('page'));
                    loadStudentsList(page, perPage);
                });
                
                // Bind page input change
                jQuery('#current-page-selector, #current-page-selector-bottom').on('change', function() {
                    var page = parseInt(jQuery(this).val());
                    var totalPages = parseInt(jQuery(this).closest('.paging-input').find('.total-pages').text());
                    
                    if (page > 0 && page <= totalPages) {
                        loadStudentsList(page, perPage);
                    } else {
                        alert('Vui lòng nhập số trang từ 1 đến ' + totalPages);
                        jQuery(this).val(currentPage);
                    }
                });
            }
            
            // Bind per page selector
            jQuery('#per-page-selector').on('change', function() {
                perPage = parseInt(jQuery(this).val());
                loadStudentsList(1, perPage); // Reset to page 1 when changing per page
            });
            
            // Initial bind
            jQuery(document).ready(function() {
                bindPaginationEvents();
            });
            </script>
        </div>
        <?php
    }

    public function teachers_page() {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'dnd_speaking_sessions';
        $teacher_role = get_option('dnd_teacher_role', 'teacher');
        
        // Check if viewing specific teacher details
        if (isset($_GET['teacher_id'])) {
            $this->teacher_details_page(intval($_GET['teacher_id']));
            return;
        }
        
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
                        <th>Actions</th>
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
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=dnd-speaking-teachers&teacher_id=' . $user->ID); ?>" class="button button-primary">
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function teacher_details_page($teacher_id) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'dnd_speaking_sessions';
        
        // Get teacher info
        $teacher = get_user_by('id', $teacher_id);
        if (!$teacher) {
            echo '<div class="wrap"><h1>Teacher not found</h1></div>';
            return;
        }
        
        // Get filter parameters
        $filter_year = isset($_GET['year']) ? intval($_GET['year']) : null;
        $filter_month = isset($_GET['month']) ? intval($_GET['month']) : null;
        $filter_day = isset($_GET['day']) ? intval($_GET['day']) : null;
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        
        // Build query with filters
        $where_clause = "teacher_id = %d";
        $query_params = [$teacher_id];
        
        if ($filter_year) {
            $where_clause .= " AND YEAR(start_time) = %d";
            $query_params[] = $filter_year;
        }
        
        if ($filter_month) {
            $where_clause .= " AND MONTH(start_time) = %d";
            $query_params[] = $filter_month;
        }
        
        if ($filter_day) {
            $where_clause .= " AND DAY(start_time) = %d";
            $query_params[] = $filter_day;
        }
        
        if ($filter_status !== 'all') {
            $where_clause .= " AND status = %s";
            $query_params[] = $filter_status;
        }
        
        // Get sessions
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, u.display_name as student_name 
             FROM $table_sessions s
             LEFT JOIN {$wpdb->users} u ON s.student_id = u.ID
             WHERE $where_clause
             ORDER BY start_time DESC",
            $query_params
        ));
        
        // Get available years for filter
        $years = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT YEAR(start_time) as year FROM $table_sessions WHERE teacher_id = %d AND start_time IS NOT NULL ORDER BY year DESC",
            $teacher_id
        ));
        
        // Calculate statistics
        $total_sessions = count($sessions);
        $completed_sessions = 0;
        $cancelled_sessions = 0;
        $total_duration = 0;
        
        foreach ($sessions as $session) {
            if ($session->status === 'completed') {
                $completed_sessions++;
                $total_duration += intval($session->duration);
            } elseif ($session->status === 'cancelled') {
                $cancelled_sessions++;
            }
        }
        
        ?>
        <div class="wrap dnd-teacher-details">
            <h1>
                Teacher Details: <?php echo esc_html($teacher->display_name); ?>
                <a href="<?php echo admin_url('admin.php?page=dnd-speaking-teachers'); ?>" class="page-title-action">← Back to Teachers</a>
            </h1>
            
            <!-- Statistics -->
            <div class="dnd-stats-cards">
                <div class="dnd-stat-card">
                    <div class="dnd-stat-label">Total Sessions</div>
                    <div class="dnd-stat-value"><?php echo $total_sessions; ?></div>
                </div>
                <div class="dnd-stat-card">
                    <div class="dnd-stat-label">Completed</div>
                    <div class="dnd-stat-value"><?php echo $completed_sessions; ?></div>
                </div>
                <div class="dnd-stat-card">
                    <div class="dnd-stat-label">Cancelled</div>
                    <div class="dnd-stat-value"><?php echo $cancelled_sessions; ?></div>
                </div>
                <div class="dnd-stat-card">
                    <div class="dnd-stat-label">Total Duration</div>
                    <div class="dnd-stat-value"><?php echo $total_duration; ?> min</div>
                </div>
            </div>
            
            <!-- YouTube Video Settings -->
            <div class="dnd-youtube-settings" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ccc; border-radius: 5px;">
                <h2>YouTube Video Settings</h2>
                <?php
                $youtube_url = get_user_meta($teacher_id, 'dnd_youtube_url', true);
                ?>
                <form id="teacher-youtube-form" style="margin-top: 15px;">
                    <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="youtube_url">YouTube Video URL:</label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="youtube_url" 
                                       name="youtube_url" 
                                       value="<?php echo esc_attr($youtube_url); ?>" 
                                       class="regular-text"
                                       placeholder="https://www.youtube.com/watch?v=...">
                                <p class="description">
                                    Enter the YouTube video URL for this teacher. Supported formats:
                                    <br>• https://www.youtube.com/watch?v=VIDEO_ID
                                    <br>• https://youtu.be/VIDEO_ID
                                </p>
                                <?php if ($youtube_url): ?>
                                    <div style="margin-top: 15px;">
                                        <strong>Preview:</strong><br>
                                        <?php
                                        // Extract video ID and show preview
                                        $video_id = '';
                                        if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $youtube_url, $matches)) {
                                            $video_id = $matches[1];
                                        } elseif (preg_match('/youtu\.be\/([^?]+)/', $youtube_url, $matches)) {
                                            $video_id = $matches[1];
                                        }
                                        if ($video_id):
                                        ?>
                                            <div style="position: relative; padding-bottom: 56.25%; height: 0; max-width: 560px;">
                                                <iframe 
                                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"
                                                    src="https://www.youtube.com/embed/<?php echo esc_attr($video_id); ?>" 
                                                    frameborder="0" 
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                    allowfullscreen>
                                                </iframe>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Save YouTube URL</button>
                        <span id="youtube-save-message" style="margin-left: 10px; color: green; display: none;">✓ Saved successfully!</span>
                    </p>
                </form>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#teacher-youtube-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var $form = $(this);
                    var $button = $form.find('button[type="submit"]');
                    var $message = $('#youtube-save-message');
                    
                    $button.prop('disabled', true).text('Saving...');
                    $message.hide();
                    
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'save_teacher_youtube_url',
                            teacher_id: $form.find('[name="teacher_id"]').val(),
                            youtube_url: $form.find('[name="youtube_url"]').val(),
                            nonce: '<?php echo wp_create_nonce('save_teacher_youtube_url'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $message.show();
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);
                            } else {
                                alert('Error: ' + (response.data || 'Unknown error'));
                            }
                        },
                        error: function() {
                            alert('An error occurred while saving.');
                        },
                        complete: function() {
                            $button.prop('disabled', false).text('Save YouTube URL');
                        }
                    });
                });
            });
            </script>
            
            <!-- Filters -->
            <div class="dnd-filters">
                <h2>Filter Sessions</h2>
                <form method="get" class="dnd-filter-form">
                    <input type="hidden" name="page" value="dnd-speaking-teachers">
                    <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
                    
                    <div class="dnd-filter-row">
                        <div class="dnd-filter-field">
                            <label for="filter_status">Status:</label>
                            <select name="status" id="filter_status">
                                <option value="all" <?php selected($filter_status, 'all'); ?>>All</option>
                                <option value="pending" <?php selected($filter_status, 'pending'); ?>>Pending</option>
                                <option value="confirmed" <?php selected($filter_status, 'confirmed'); ?>>Confirmed</option>
                                <option value="in_progress" <?php selected($filter_status, 'in_progress'); ?>>In Progress</option>
                                <option value="completed" <?php selected($filter_status, 'completed'); ?>>Completed</option>
                                <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="dnd-filter-field">
                            <label for="filter_year">Year:</label>
                            <select name="year" id="filter_year">
                                <option value="">All Years</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>" <?php selected($filter_year, $year); ?>><?php echo $year; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="dnd-filter-field">
                            <label for="filter_month">Month:</label>
                            <select name="month" id="filter_month">
                                <option value="">All Months</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php selected($filter_month, $m); ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="dnd-filter-field">
                            <label for="filter_day">Day:</label>
                            <select name="day" id="filter_day">
                                <option value="">All Days</option>
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <option value="<?php echo $d; ?>" <?php selected($filter_day, $d); ?>><?php echo $d; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="dnd-filter-field">
                            <button type="submit" class="button button-primary">Apply Filter</button>
                            <a href="<?php echo admin_url('admin.php?page=dnd-speaking-teachers&teacher_id=' . $teacher_id); ?>" class="button">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Sessions List -->
            <h2>Sessions List (<?php echo count($sessions); ?> results)</h2>
            <?php if (empty($sessions)): ?>
                <p>No sessions found with the current filters.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Duration (min)</th>
                            <th>Status</th>
                            <th>Discord Channel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><?php echo $session->id; ?></td>
                                <td><?php echo esc_html($session->student_name ?: 'Unknown'); ?></td>
                                <td><?php echo $session->start_time ?: 'N/A'; ?></td>
                                <td><?php echo $session->end_time ?: 'N/A'; ?></td>
                                <td><?php echo $session->duration ?: '0'; ?></td>
                                <td>
                                    <span class="dnd-status-badge dnd-status-<?php echo esc_attr($session->status); ?>">
                                        <?php echo ucfirst($session->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($session->discord_channel)): ?>
                                        <a href="<?php echo esc_url($session->discord_channel); ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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
                <a href="?page=dnd-speaking-settings&tab=discord&sub_tab=webhook_integration" class="nav-tab <?php echo $active_sub_tab == 'webhook_integration' ? 'nav-tab-active' : ''; ?>">Webhook Integration</a>
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
                        <!-- Hidden fields to preserve webhook settings -->
                        <input type="hidden" name="dnd_discord_webhook" value="<?php echo esc_attr(get_option('dnd_discord_webhook')); ?>" />
                        
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px; margin-top: 10px;">
                            <label for="dnd_discord_client_id" style="width: 150px; font-weight: bold;">Client ID</label>
                            <input type="text" id="dnd_discord_client_id" name="dnd_discord_client_id" value="<?php echo esc_attr(get_option('dnd_discord_client_id')); ?>" style="max-width: 300px;" />
                        </div>
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px;">
                            <label for="dnd_discord_client_secret" style="width: 150px; font-weight: bold;">Client Secret</label>
                            <input type="password" id="dnd_discord_client_secret" name="dnd_discord_client_secret" value="<?php echo esc_attr(get_option('dnd_discord_client_secret')); ?>" style="max-width: 300px;" />
                        </div>
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px;">
                            <label for="dnd_discord_redirect_url" style="width: 150px; font-weight: bold;">Redirect URL</label>
                            <div style="position: relative; width: 300px;">
                                <input type="text" id="dnd_discord_redirect_url_display" value="<?php echo esc_attr(get_option('dnd_discord_redirect_page_full') ?: get_option('dnd_discord_redirect_page') ?: (get_site_url() . '/wp-json/dnd-speaking/v1/discord/callback')); ?>" class="regular-text dnd-discord-redirect-input" readonly style="background-color: #f5f5f5; width: 100%; cursor: pointer; box-sizing: border-box;" />
                                <span class="dashicons dashicons-yes dnd-copy-feedback" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #46b450; display: none;"></span>
                            </div>
                        </div>
                        <p class="description" style="margin-left: 160px; margin-top: -10px;">Copy this URL and paste inside your https://discord.com/developers/applications -> 0Auth2 -> Redirects</p>
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px;">
                            <label for="dnd_discord_redirect_page" style="width: 150px; font-weight: bold;">Redirect Page</label>
                            <select id="dnd_discord_redirect_page" name="dnd_discord_redirect_page" style="max-width: 300px; width: 300px;" data-selected="<?php echo esc_attr(get_option('dnd_discord_redirect_page')); ?>">
                                <option value="">-- Select a page --</option>
                            </select>
                        </div>
                        <p class="description" style="margin-left: 160px; margin-top: -10px;">Select the page where users will be redirected after Discord authentication</p>
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px;">
                            <label for="dnd_discord_generated_url" style="width: 150px; font-weight: bold;">Generated URL</label>
                            <input type="url" id="dnd_discord_generated_url" name="dnd_discord_generated_url" value="<?php echo esc_attr(get_option('dnd_discord_generated_url')); ?>" style="max-width: 300px;" />
                        </div>
                        <p class="description" style="margin-left: 160px; margin-top: -10px;">Paste your URL from https://discord.com/developers/applications -> OAuth2 -> Generated URL</p>
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
                    } elseif ($active_sub_tab == 'webhook_integration') {
                        ?>
                        <!-- Hidden fields to preserve other settings -->
                        <input type="hidden" name="dnd_discord_client_id" value="<?php echo esc_attr(get_option('dnd_discord_client_id')); ?>" />
                        <input type="hidden" name="dnd_discord_client_secret" value="<?php echo esc_attr(get_option('dnd_discord_client_secret')); ?>" />
                        <input type="hidden" name="dnd_discord_redirect_page" value="<?php echo esc_attr(get_option('dnd_discord_redirect_page')); ?>" />
                        <input type="hidden" name="dnd_discord_redirect_page_full" value="<?php echo esc_attr(get_option('dnd_discord_redirect_page_full')); ?>" />
                        <input type="hidden" name="dnd_discord_generated_url" value="<?php echo esc_attr(get_option('dnd_discord_generated_url')); ?>" />
                        <input type="hidden" name="dnd_discord_admin_redirect_url" value="<?php echo esc_attr(get_option('dnd_discord_admin_redirect_url')); ?>" />
                        <input type="hidden" name="dnd_discord_bot_token" value="<?php echo esc_attr(get_option('dnd_discord_bot_token')); ?>" />
                        <input type="hidden" name="dnd_discord_server_id" value="<?php echo esc_attr(get_option('dnd_discord_server_id')); ?>" />
                        <input type="hidden" name="dnd_discord_connect_to_bot" value="<?php echo esc_attr(get_option('dnd_discord_connect_to_bot')); ?>" />
                        
                        <div class="form-field" style="display: flex; align-items: center; margin-bottom: 10px; margin-top: 10px;">
                            <label for="dnd_discord_webhook" style="width: 150px; font-weight: bold;">Discord Webhook URL</label>
                            <input type="url" id="dnd_discord_webhook" name="dnd_discord_webhook" value="<?php echo esc_attr(get_option('dnd_discord_webhook')); ?>" style="max-width: 400px; width: 400px;" />
                        </div>
                        <p class="description" style="margin-left: 160px; margin-top: -10px;">Webhook URL for all Discord integrations. The system will send different 'action' values: 'online' (teacher goes online), 'offline' (teacher goes offline), 'student_start_now' (student starts a session).</p>
                        <?php
                    } elseif ($active_sub_tab == 'advanced') {
                        ?>
                        <!-- Hidden fields to preserve other settings -->
                        <input type="hidden" name="dnd_discord_client_id" value="<?php echo esc_attr(get_option('dnd_discord_client_id')); ?>" />
                        <input type="hidden" name="dnd_discord_client_secret" value="<?php echo esc_attr(get_option('dnd_discord_client_secret')); ?>" />
                        <input type="hidden" name="dnd_discord_redirect_page" value="<?php echo esc_attr(get_option('dnd_discord_redirect_page')); ?>" />
                        <input type="hidden" name="dnd_discord_redirect_page_full" value="<?php echo esc_attr(get_option('dnd_discord_redirect_page_full')); ?>" />
                        <input type="hidden" name="dnd_discord_generated_url" value="<?php echo esc_attr(get_option('dnd_discord_generated_url')); ?>" />
                        <input type="hidden" name="dnd_discord_admin_redirect_url" value="<?php echo esc_attr(get_option('dnd_discord_admin_redirect_url')); ?>" />
                        <input type="hidden" name="dnd_discord_bot_token" value="<?php echo esc_attr(get_option('dnd_discord_bot_token')); ?>" />
                        <input type="hidden" name="dnd_discord_server_id" value="<?php echo esc_attr(get_option('dnd_discord_server_id')); ?>" />
                        <input type="hidden" name="dnd_discord_connect_to_bot" value="<?php echo esc_attr(get_option('dnd_discord_connect_to_bot')); ?>" />
                        <input type="hidden" name="dnd_discord_webhook" value="<?php echo esc_attr(get_option('dnd_discord_webhook')); ?>" />
                        
                        <?php
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
        register_setting('dnd_speaking_settings', 'dnd_auto_assign_lessons');
        register_setting('dnd_speaking_settings', 'dnd_discord_client_id');
        register_setting('dnd_speaking_settings', 'dnd_discord_client_secret');
        register_setting('dnd_speaking_settings', 'dnd_discord_bot_token');

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
            'auto_assign_lessons',
            'Auto-Assign Lessons to New Users',
            [$this, 'auto_assign_lessons_field'],
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
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_redirect_page');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_redirect_page_full');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_admin_redirect_url');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_bot_token');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_server_id');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_connect_to_bot');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_generated_url');
        register_setting('dnd_speaking_discord_settings', 'dnd_discord_webhook');

        add_settings_section(
            'dnd_speaking_discord_app_details',
            'Application Details',
            null,
            'dnd_speaking_discord_settings'
        );

        add_settings_section(
            'dnd_speaking_discord_webhook_integration',
            'Webhook Integration',
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

    public function enqueue_admin_scripts($hook) {
        // Only load on DND Speaking settings pages
        if (strpos($hook, 'dnd-speaking') === false) {
            return;
        }
        
        // Enqueue CSS for teacher details page
        if (strpos($hook, 'dnd-speaking-teachers') !== false) {
            wp_enqueue_style('dnd-admin-teachers', plugins_url('../assets/css/admin-teachers.css', __FILE__), [], '1.0.0');
        }
        
        // Enqueue Select2 for students page
        if (strpos($hook, 'dnd-speaking-students') !== false) {
            wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], '4.1.0');
            wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
            
            // Add inline script to initialize Select2
            $inline_script = "
            jQuery(document).ready(function($) {
                $('#user_id').select2({
                    placeholder: 'Choose a student...',
                    allowClear: true,
                    width: '100%'
                });
            });
            ";
            wp_add_inline_script('select2', $inline_script);
        }
        
        wp_enqueue_script('discord-settings', plugins_url('../assets/js/discord-settings.js', __FILE__), array('jquery'), null, true);
        wp_localize_script('discord-settings', 'dndSettings', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'savedRedirectUrl' => get_option('dnd_discord_redirect_page_full')
        ));
    }

    // AJAX handler to get pages
    public function get_pages() {
        $pages = get_pages();
        $page_list = [];
        foreach ($pages as $page) {
            $page_list[] = [
                'title' => $page->post_title,
                'url' => get_permalink($page->ID)
            ];
        }
        wp_send_json($page_list);
    }

    public function session_duration_field() {
        $value = get_option('dnd_session_duration', 25);
        echo '<input type="number" name="dnd_session_duration" value="' . esc_attr($value) . '" />';
    }

    public function default_credits_field() {
        $value = get_option('dnd_default_credits', 0);
        echo '<input type="number" name="dnd_default_credits" value="' . esc_attr($value) . '" />';
    }

    public function auto_assign_lessons_field() {
        $value = get_option('dnd_auto_assign_lessons', 0);
        echo '<input type="number" name="dnd_auto_assign_lessons" value="' . esc_attr($value) . '" min="0" />';
        echo '<p class="description">Số buổi học được tự động cấp cho user mới khi đăng ký. Đặt 0 để tắt tính năng này.</p>';
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
        $redirect_url = get_site_url() . '/wp-json/dnd-speaking/v1/discord/callback';
        echo '<div style="display: flex; align-items: center; gap: 10px;">';
        echo '<input type="text" value="' . esc_attr($redirect_url) . '" class="regular-text" readonly style="background-color: #f5f5f5;" />';
        echo '<span class="dashicons dashicons-editor-help dnd-discord-help" style="cursor: pointer; color: #007cba;" title="Click to copy this URL to clipboard"></span>';
        echo '</div>';
        echo '<p class="description">This is the required OAuth2 redirect URI for Discord authentication. Click the help icon to copy it, then paste into your Discord app\'s OAuth2 Redirect URIs.</p>';
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

    public function handle_bulk_add_lessons() {
        // Check nonce for security
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk_lessons_nonce')) {
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $apply_to_all = isset($_POST['apply_to_all']) && $_POST['apply_to_all'] === '1';
        $credits = isset($_POST['credits']) ? intval($_POST['credits']) : 0;

        // Validate credits
        if ($credits <= 0) {
            wp_die('Invalid input: Please specify positive number of lessons');
        }

        // Get user IDs
        if ($apply_to_all) {
            // Get all users except administrators
            $all_users = get_users([
                'role__not_in' => ['administrator'],
                'fields' => ['ID']
            ]);
            $user_ids = array_map(function($user) { return $user->ID; }, $all_users);
        } else {
            // Get from hidden field (comma-separated IDs)
            $user_ids_string = isset($_POST['user_ids_hidden']) ? $_POST['user_ids_hidden'] : '';
            if (empty($user_ids_string)) {
                wp_die('Invalid input: Please select students or check "Apply to all"');
            }
            $user_ids = array_map('intval', explode(',', $user_ids_string));
        }

        // Bulk add lessons
        $results = DND_Speaking_Helpers::bulk_add_lessons($user_ids, $credits);
        $success_count = count(array_filter($results));

        // Log the action
        $log_detail = $apply_to_all ? "Added $credits lessons to ALL students ($success_count total)" : "Added $credits lessons to $success_count selected students";
        DND_Speaking_Helpers::log_action(get_current_user_id(), 'bulk_add_lessons', $log_detail);

        // Redirect back with success message
        wp_redirect(admin_url('admin.php?page=dnd-speaking-students&bulk_added=' . $success_count));
        exit;
    }

    public function handle_bulk_remove_lessons() {
        // Debug logging
        error_log('handle_bulk_remove_lessons called');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Check nonce for security
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk_lessons_nonce')) {
            error_log('Nonce check failed');
            wp_die('Security check failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            error_log('User lacks manage_options capability');
            wp_die('Unauthorized');
        }

        $apply_to_all = isset($_POST['apply_to_all']) && $_POST['apply_to_all'] === '1';
        $credits = isset($_POST['credits']) ? intval($_POST['credits']) : 0;
        
        error_log('Apply to all: ' . ($apply_to_all ? 'yes' : 'no'));
        error_log('Credits: ' . $credits);

        // Validate credits
        if ($credits <= 0) {
            error_log('Invalid credits amount: ' . $credits);
            wp_die('Invalid input: Please specify positive number of lessons');
        }

        // Get user IDs
        if ($apply_to_all) {
            // Get all users except administrators
            $all_users = get_users([
                'role__not_in' => ['administrator'],
                'fields' => ['ID']
            ]);
            $user_ids = array_map(function($user) { return $user->ID; }, $all_users);
            error_log('Apply to all: ' . count($user_ids) . ' users');
        } else {
            // Get from hidden field (comma-separated IDs)
            $user_ids_string = isset($_POST['user_ids_hidden']) ? $_POST['user_ids_hidden'] : '';
            error_log('user_ids_hidden: ' . $user_ids_string);
            
            if (empty($user_ids_string)) {
                error_log('No user IDs provided');
                wp_die('Invalid input: Please select students or check "Apply to all"');
            }
            $user_ids = array_map('intval', explode(',', $user_ids_string));
            error_log('Selected users: ' . count($user_ids));
        }

        // Bulk remove lessons
        error_log('Calling bulk_remove_lessons for ' . count($user_ids) . ' users');
        $results = DND_Speaking_Helpers::bulk_remove_lessons($user_ids, $credits);
        $success_count = count(array_filter($results));
        error_log('Success count: ' . $success_count);

        // Log the action
        $log_detail = $apply_to_all ? "Removed $credits lessons from ALL students ($success_count succeeded)" : "Removed $credits lessons from $success_count selected students";
        DND_Speaking_Helpers::log_action(get_current_user_id(), 'bulk_remove_lessons', $log_detail);

        // Redirect back with success message
        error_log('Redirecting with success count: ' . $success_count);
        wp_redirect(admin_url('admin.php?page=dnd-speaking-students&bulk_removed=' . $success_count));
        exit;
    }

    public function ajax_load_students_list() {
        check_ajax_referer('dnd_students_list_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'dnd_speaking_credits';
        
        // Get pagination parameters
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        $per_page = in_array($per_page, [1, 3, 5, 10, 20, 50, 100]) ? $per_page : 10;
        $current_page = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Get total count
        $total_students = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $total_pages = ceil($total_students / $per_page);
        
        // Get students with pagination
        $students = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY credits DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        // Generate HTML for students table
        ob_start();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Tên Học Viên</th>
                    <th style="width: 120px;">Buổi Học Còn Lại</th>
                    <th style="width: 150px;">Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px; color: #999;">
                            Không có học viên nào
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <?php $user = get_userdata($student->user_id); ?>
                        <?php if ($user): ?>
                            <tr>
                                <td><?php echo $student->user_id; ?></td>
                                <td><?php echo esc_html($user->display_name); ?></td>
                                <td><strong><?php echo $student->credits; ?></strong></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=dnd-speaking-students&student_id=' . $student->user_id); ?>" class="button button-primary">
                                        Xem Chi Tiết
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        $table_html = ob_get_clean();
        
        // Generate pagination HTML
        ob_start();
        if ($total_pages > 1):
            $base_url = admin_url('admin.php?page=dnd-speaking-students&per_page=' . $per_page);
        ?>
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo $total_students; ?> mục</span>
            <span class="pagination-links">
                <?php
                // First page
                if ($current_page > 1) {
                    echo '<a class="first-page button" href="#" data-page="1"><span aria-hidden="true">«</span></a>';
                    echo '<a class="prev-page button" href="#" data-page="' . ($current_page - 1) . '"><span aria-hidden="true">‹</span></a>';
                } else {
                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
                }
                
                // Current page
                echo '<span class="paging-input">';
                echo '<label for="current-page-selector" class="screen-reader-text">Trang hiện tại</label>';
                echo '<input class="current-page" id="current-page-selector" type="text" name="paged" value="' . $current_page . '" size="2" aria-describedby="table-paging">';
                echo '<span class="tablenav-paging-text"> / <span class="total-pages">' . $total_pages . '</span></span>';
                echo '</span>';
                
                // Last page
                if ($current_page < $total_pages) {
                    echo '<a class="next-page button" href="#" data-page="' . ($current_page + 1) . '"><span aria-hidden="true">›</span></a>';
                    echo '<a class="last-page button" href="#" data-page="' . $total_pages . '"><span aria-hidden="true">»</span></a>';
                } else {
                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
                }
                ?>
            </span>
        </div>
        <?php
        endif;
        $pagination_html = ob_get_clean();
        
        wp_send_json_success([
            'table_html' => $table_html,
            'pagination_html' => $pagination_html,
            'total_students' => $total_students,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'per_page' => $per_page
        ]);
    }

    public function auto_assign_lessons_to_new_user($user_id) {
        $auto_lessons = get_option('dnd_auto_assign_lessons', 0);
        
        if ($auto_lessons > 0) {
            DND_Speaking_Helpers::add_user_lessons($user_id, $auto_lessons);
            DND_Speaking_Helpers::log_action($user_id, 'auto_assign_lessons', "Auto-assigned $auto_lessons lessons to new user");
        }
    }

    public function student_details_page($student_id) {
        global $wpdb;
        
        // Get student info
        $student = get_user_by('id', $student_id);
        if (!$student) {
            echo '<div class="wrap"><h1>Học viên không tồn tại</h1></div>';
            return;
        }

        // Get current lessons
        $current_lessons = DND_Speaking_Helpers::get_user_lessons($student_id);

        // Get sessions history
        $table_sessions = $wpdb->prefix . 'dnd_speaking_sessions';
        $sessions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, 
                    t.display_name as teacher_name 
             FROM $table_sessions s
             LEFT JOIN {$wpdb->users} t ON s.teacher_id = t.ID
             WHERE s.student_id = %d
             ORDER BY s.start_time DESC
             LIMIT 50",
            $student_id
        ));

        // Get logs
        $table_logs = $wpdb->prefix . 'dnd_speaking_logs';
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_logs 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT 30",
            $student_id
        ));

        // Calculate statistics
        $total_sessions = count($sessions);
        $completed_sessions = count(array_filter($sessions, function($s) { return $s->status === 'completed'; }));
        $cancelled_sessions = count(array_filter($sessions, function($s) { return $s->status === 'cancelled'; }));

        ?>
        <div class="wrap">
            <h1>Chi Tiết Học Viên: <?php echo esc_html($student->display_name); ?></h1>
            <p>
                <a href="<?php echo admin_url('admin.php?page=dnd-speaking-students'); ?>" class="button">
                    ← Quay lại danh sách
                </a>
            </p>

            <hr>

            <!-- Student Info Card -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px;">
                <h2>Thông Tin Cơ Bản</h2>
                <table class="form-table">
                    <tr>
                        <th style="width: 200px;">ID:</th>
                        <td><?php echo $student_id; ?></td>
                    </tr>
                    <tr>
                        <th>Tên:</th>
                        <td><?php echo esc_html($student->display_name); ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo esc_html($student->user_email); ?></td>
                    </tr>
                    <tr>
                        <th>Username:</th>
                        <td><?php echo esc_html($student->user_login); ?></td>
                    </tr>
                    <tr>
                        <th>Số Buổi Học Còn Lại:</th>
                        <td><strong style="font-size: 18px; color: #007cba;"><?php echo $current_lessons; ?></strong></td>
                    </tr>
                    <tr>
                        <th>Ngày Đăng Ký:</th>
                        <td><?php echo date_i18n('d/m/Y H:i', strtotime($student->user_registered)); ?></td>
                    </tr>
                </table>
            </div>

            <!-- Statistics Card -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px;">
                <h2>Thống Kê</h2>
                <table class="form-table">
                    <tr>
                        <th style="width: 200px;">Tổng Số Buổi Học:</th>
                        <td><strong><?php echo $total_sessions; ?></strong></td>
                    </tr>
                    <tr>
                        <th>Buổi Học Hoàn Thành:</th>
                        <td><span style="color: green;"><strong><?php echo $completed_sessions; ?></strong></span></td>
                    </tr>
                    <tr>
                        <th>Buổi Học Đã Hủy:</th>
                        <td><span style="color: red;"><strong><?php echo $cancelled_sessions; ?></strong></span></td>
                    </tr>
                </table>
            </div>

            <!-- Sessions History -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px;">
                <h2>Lịch Sử Buổi Học (50 buổi gần nhất)</h2>
                <?php if (empty($sessions)): ?>
                    <p>Chưa có buổi học nào.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Giáo Viên</th>
                                <th>Thời Gian</th>
                                <th style="width: 120px;">Trạng Thái</th>
                                <th style="width: 100px;">Thời Lượng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): 
                                $status_color = [
                                    'completed' => 'green',
                                    'cancelled' => 'red',
                                    'active' => 'blue',
                                    'pending' => 'orange',
                                    'confirmed' => 'purple'
                                ];
                                $color = $status_color[$session->status] ?? 'gray';
                                
                                $status_text = [
                                    'completed' => 'Hoàn thành',
                                    'cancelled' => 'Đã hủy',
                                    'active' => 'Đang học',
                                    'pending' => 'Chờ xác nhận',
                                    'confirmed' => 'Đã xác nhận'
                                ];
                                $status = $status_text[$session->status] ?? $session->status;
                            ?>
                                <tr>
                                    <td><?php echo $session->id; ?></td>
                                    <td><?php echo esc_html($session->teacher_name ?: 'N/A'); ?></td>
                                    <td><?php echo $session->start_time ? date_i18n('d/m/Y H:i', strtotime($session->start_time)) : 'N/A'; ?></td>
                                    <td><span style="color: <?php echo $color; ?>; font-weight: bold;"><?php echo $status; ?></span></td>
                                    <td><?php echo $session->duration ? $session->duration . ' phút' : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Activity Logs -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                <h2>Nhật Ký Hoạt Động (30 hoạt động gần nhất)</h2>
                <?php if (empty($logs)): ?>
                    <p>Chưa có hoạt động nào.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 150px;">Thời Gian</th>
                                <th style="width: 200px;">Hành Động</th>
                                <th>Chi Tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date_i18n('d/m/Y H:i:s', strtotime($log->created_at)); ?></td>
                                    <td><code><?php echo esc_html($log->action); ?></code></td>
                                    <td><?php echo esc_html($log->details); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
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

        if ($available == 1) {
            // Check if teacher is connected to Discord
            $discord_connected = get_user_meta($user_id, 'discord_connected', true);
            if (!$discord_connected) {
                wp_send_json_error(['message' => 'Bạn chưa kết nối với tài khoản Discord. Vui lòng kết nối để có thể nhận học viên.', 'need_discord' => true]);
                return;
            }

            // Send webhook to get room link
            $webhook_url = get_option('dnd_discord_webhook');
            if (!$webhook_url) {
                wp_send_json_error(['message' => 'Webhook URL chưa được cấu hình.']);
                return;
            }

            $user = get_userdata($user_id);
            $webhook_response = wp_remote_post($webhook_url, [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'action' => 'online',
                    'discord_user_id' => get_user_meta($user_id, 'discord_user_id', true),
                    'discord_global_name' => get_user_meta($user_id, 'discord_global_name', true),
                    'server_id' => get_option('dnd_discord_server_id')
                ]),
                'timeout' => 30
            ]);

            if (is_wp_error($webhook_response)) {
                wp_send_json_error(['message' => 'Không thể kết nối đến server Discord. Vui lòng thử lại sau.']);
                return;
            }

            $response_code = wp_remote_retrieve_response_code($webhook_response);
            if ($response_code !== 200) {
                wp_send_json_error(['message' => 'Server Discord trả về lỗi (Code: ' . $response_code . '). Vui lòng thử lại sau.']);
                return;
            }

            $body = json_decode(wp_remote_retrieve_body($webhook_response), true);
            if (isset($body['channelId'])) {
                $server_id = get_option('dnd_discord_server_id');
                $room_link = 'https://discord.com/channels/' . $server_id . '/' . $body['channelId'];
                
                update_user_meta($user_id, 'dnd_available', $available);
                update_user_meta($user_id, 'discord_voice_channel_id', $body['channelId']);
                update_user_meta($user_id, 'discord_voice_channel_invite', $room_link);
                
                wp_send_json_success(['available' => $available, 'invite_link' => $room_link]);
                return;
            } else {
                $error_msg = isset($body['error']) ? $body['error'] : 'Không thể nhận channelId từ webhook.';
                wp_send_json_error(['message' => $error_msg]);
                return;
            }
        } else {
            // Send webhook for offline status
            $webhook_url = get_option('dnd_discord_webhook');
            $channel_id = get_user_meta($user_id, 'discord_voice_channel_id', true);
            
            if ($webhook_url && $channel_id) {
                $user = get_userdata($user_id);
                $webhook_response = wp_remote_post($webhook_url, [
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode([
                        'action' => 'offline',
                        'discord_user_id' => get_user_meta($user_id, 'discord_user_id', true),
                        'discord_global_name' => get_user_meta($user_id, 'discord_global_name', true),
                        'server_id' => get_option('dnd_discord_server_id'),
                        'channelId' => $channel_id
                    ]),
                    'timeout' => 30
                ]);
                
                // Wait for webhook response
                if (is_wp_error($webhook_response)) {
                    wp_send_json_error(['message' => 'Không thể kết nối đến server Discord để xóa phòng.']);
                    return;
                }
                
                $response_code = wp_remote_retrieve_response_code($webhook_response);
                if ($response_code !== 200) {
                    wp_send_json_error(['message' => 'Server Discord trả về lỗi khi xóa phòng (Code: ' . $response_code . ').']);
                    return;
                }
            }

            // Clean up metadata after successful webhook response
            if ($channel_id) {
                // Clean up meta
                delete_user_meta($user_id, 'discord_voice_channel_id');
                delete_user_meta($user_id, 'discord_voice_channel_invite');
            }
        }

        update_user_meta($user_id, 'dnd_available', $available);
        wp_send_json_success(['available' => $available]);
    }

    public function handle_teacher_request() {
        error_log("=== HANDLE TEACHER REQUEST CALLED ===");
        
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'teacher_requests_nonce')) {
            error_log("Nonce check failed");
            wp_send_json_error('Security check failed');
        }

        $session_id = intval($_POST['session_id']);
        $action = sanitize_text_field($_POST['request_action']);
        $teacher_id = get_current_user_id();
        
        error_log("Session ID: {$session_id}, Action: {$action}, Teacher ID: {$teacher_id}");

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
            error_log("Session not found or already processed");
            wp_send_json_error('Session not found or already processed');
        }
        
        error_log("Session found: " . print_r($session, true));

        // Check if session time has passed or is within 5 minutes
        if (!empty($session->start_time)) {
            // Get current time in WordPress timezone (should be Asia/Ho_Chi_Minh)
            $current_timestamp = current_time('timestamp');
            
            // Convert session start_time from database (UTC) to timestamp
            $session_timestamp = strtotime(get_date_from_gmt($session->start_time));
            
            $time_until_session = $session_timestamp - $current_timestamp;
            
            // Debug logging with more details
            $current_time_formatted = date('Y-m-d H:i:s', $current_timestamp);
            $session_time_formatted = date('Y-m-d H:i:s', $session_timestamp);
            $utc_time = gmdate('Y-m-d H:i:s');
            error_log("=== TIME CHECK DEBUG ===");
            error_log("UTC Now: {$utc_time}");
            error_log("Current time (local): {$current_time_formatted}");
            error_log("Session start_time (UTC from DB): {$session->start_time}");
            error_log("Session start_time (converted to local): {$session_time_formatted}");
            error_log("Time difference (seconds): {$time_until_session}");
            error_log("Time difference (minutes): " . ($time_until_session / 60));
            error_log("Threshold: 300 seconds (5 minutes)");
            error_log("Will cancel?: " . ($time_until_session <= 300 ? 'YES' : 'NO'));
            
            // If session is in the past or within 5 minutes (300 seconds)
            if ($time_until_session <= 300) {
                error_log("CANCELLING SESSION - Time until session ({$time_until_session}s) is <= 300s");
                
                // Auto-cancel and refund
                $wpdb->update(
                    $sessions_table,
                    [
                        'status' => 'cancelled',
                        'cancelled_at' => current_time('mysql'),
                        'cancelled_by' => 0 // 0 means auto-cancelled by system
                    ],
                    ['id' => $session_id],
                    ['%s', '%s', '%d'],
                    ['%d']
                );
                
                // Refund the student's credit
                $student_id = $session->student_id;
                DND_Speaking_Helpers::add_user_credits($student_id, 1);
                
                // Determine error message
                if ($time_until_session < 0) {
                    $message = 'Không thể xác nhận vì buổi học đã quá giờ. Hệ thống đã tự động hủy và hoàn lại buổi học cho học viên.';
                } else {
                    $message = 'Không thể xác nhận vì sắp đến giờ học (còn dưới 5 phút). Hệ thống đã tự động hủy và hoàn lại buổi học cho học viên.';
                }
                
                error_log("DND Speaking: Auto-cancelled session {$session_id}");
                
                wp_send_json_error($message);
            } else {
                error_log("NOT CANCELLING - Time until session ({$time_until_session}s) is > 300s");
            }
        } else {
            error_log("No start_time found for session");
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

        // Credits are already deducted when student books
        // If accepted, no need to deduct again
        // If declined, refund the credits
        if ($action === 'decline') {
            $student_id = $session->student_id;
            DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher declined session');
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

            // Teacher cancels confirmed session - ALWAYS refund credits to student
            $student_id = $session->student_id;
            DND_Speaking_Helpers::refund_user_credits($student_id, 1, 'Teacher cancelled confirmed session');
            error_log('TEACHER CANCEL CONFIRMED SESSION (via handle_upcoming_session) - Refunded 1 credit to student: ' . $student_id);

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
                            // Special case: if end_time is 00:00, it means midnight of next day
                            $start_timestamp = strtotime($start_time);
                            $end_timestamp = strtotime($end_time);
                            
                            // If end time is 00:00 (midnight), treat it as next day (add 24 hours)
                            if ($end_time === '00:00') {
                                $end_timestamp = strtotime('+1 day', $end_timestamp);
                            }
                            
                            if ($start_timestamp >= $end_timestamp) {
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

    public function save_teacher_youtube_url() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'save_teacher_youtube_url')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $teacher_id = intval($_POST['teacher_id']);
        $youtube_url = sanitize_text_field($_POST['youtube_url']);

        // Validate YouTube URL format
        if (!empty($youtube_url)) {
            $valid_patterns = [
                '/^https?:\/\/(www\.)?youtube\.com\/watch\?v=[\w-]+/',
                '/^https?:\/\/youtu\.be\/[\w-]+/'
            ];
            
            $is_valid = false;
            foreach ($valid_patterns as $pattern) {
                if (preg_match($pattern, $youtube_url)) {
                    $is_valid = true;
                    break;
                }
            }
            
            if (!$is_valid) {
                wp_send_json_error('Invalid YouTube URL format. Please use: https://www.youtube.com/watch?v=... or https://youtu.be/...');
                return;
            }
        }

        // Update user meta
        $result = update_user_meta($teacher_id, 'dnd_youtube_url', $youtube_url);

        if ($result === false && get_user_meta($teacher_id, 'dnd_youtube_url', true) !== $youtube_url) {
            wp_send_json_error('Failed to save YouTube URL');
            return;
        }

        wp_send_json_success('YouTube URL saved successfully');
    }
}
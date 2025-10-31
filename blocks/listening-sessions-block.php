<?php
/**
 * Gutenberg block for displaying listening sessions (YouTube videos)
 */

class DND_Speaking_Listening_Sessions_Block {

    public function __construct() {
        add_action('init', [$this, 'register_block']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
        add_action('enqueue_block_assets', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    public function register_block() {
        register_block_type('dnd-speaking/listening-sessions', [
            'render_callback' => [$this, 'render_block'],
            'attributes' => [
                'title' => [
                    'type' => 'string',
                    'default' => 'Nghe Buổi Học'
                ],
            ],
        ]);
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'dnd-speaking-listening-sessions-block-editor',
            plugin_dir_url(__FILE__) . 'listening-sessions-block.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'],
            '1.0.0'
        );

        wp_enqueue_style(
            'dnd-speaking-listening-sessions-block-editor-style',
            plugin_dir_url(__FILE__) . 'listening-sessions-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'dnd-speaking-listening-sessions-block-style',
            plugin_dir_url(__FILE__) . 'listening-sessions-block.css',
            [],
            '1.0.0'
        );
    }

    public function enqueue_frontend_scripts() {
        if (has_block('dnd-speaking/listening-sessions')) {
            wp_enqueue_script(
                'dnd-speaking-listening-sessions-block',
                plugin_dir_url(__FILE__) . 'listening-sessions-block-frontend.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dnd-speaking-listening-sessions-block', 'dnd_listening_data', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dnd_listening_nonce'),
                'is_admin' => current_user_can('manage_options'),
            ]);
        }
    }

    public function render_block($attributes) {
        $title = isset($attributes['title']) ? esc_html($attributes['title']) : 'Nghe Buổi Học';
        
        $output = '<div class="dnd-listening-sessions-block">';
        $output .= '<h2 class="dnd-listening-title">' . $title . '</h2>';
        
        // Admin link to manage videos
        if (current_user_can('manage_options')) {
            $output .= '<div class="dnd-listening-admin-link">';
            $output .= '<a href="' . admin_url('admin.php?page=dnd-speaking-listening-sessions') . '" class="dnd-btn dnd-btn-admin">';
            $output .= '⚙️ Quản lý Listening Sessions';
            $output .= '</a>';
            $output .= '</div>';
        }
        
        // Video list container
        $output .= '<div class="dnd-listening-videos-container" id="dnd-listening-videos">';
        $output .= $this->get_videos_html();
        $output .= '</div>';
        
        $output .= '</div>';

        return $output;
    }

    private function get_videos_html() {
        $sessions = get_option('dnd_listening_sessions', []);
        
        if (empty($sessions)) {
            return '<p class="dnd-no-videos">Chưa có video nào được thêm.</p>';
        }

        $html = '<div class="dnd-videos-grid">';
        
        foreach ($sessions as $session) {
            $video_id = $this->extract_youtube_id($session['url']);
            
            // Get teacher and student info (support both old and new format)
            $teacher_ids = isset($session['teacher_ids']) ? $session['teacher_ids'] : (isset($session['teacher_id']) ? [$session['teacher_id']] : []);
            $teachers = [];
            foreach ($teacher_ids as $tid) {
                $teacher = get_userdata($tid);
                if ($teacher) $teachers[] = $teacher;
            }
            
            $student_ids = isset($session['student_ids']) ? $session['student_ids'] : (isset($session['student_id']) ? [$session['student_id']] : []);
            $students = [];
            foreach ($student_ids as $sid) {
                $student = get_userdata($sid);
                if ($student) $students[] = $student;
            }
            
            $html .= '<div class="dnd-video-card" data-video-id="' . esc_attr($session['id']) . '">';
            
            // Embedded YouTube iframe - First element, full width
            $html .= '<div class="dnd-video-embed">';
            $html .= '<iframe src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" ';
            $html .= 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ';
            $html .= 'allowfullscreen></iframe>';
            $html .= '</div>';
            
            // Video info below video
            $html .= '<div class="dnd-video-info">';
            
            // Title
            $html .= '<h3 class="dnd-video-title">' . esc_html($session['title']) . '</h3>';
            
            // Teachers
            if (!empty($teachers)) {
                $html .= '<div class="dnd-video-meta-line">';
                $html .= '<span class="dnd-meta-label">Giáo viên:</span> ';
                $teacher_links = [];
                foreach ($teachers as $teacher) {
                    $teacher_links[] = '<a href="#" class="dnd-profile-link" data-user-id="' . esc_attr($teacher->ID) . '">' . esc_html($teacher->display_name) . '</a>';
                }
                $html .= implode(', ', $teacher_links);
                $html .= '</div>';
            }
            
            // Students
            if (!empty($students)) {
                $html .= '<div class="dnd-video-meta-line">';
                $html .= '<span class="dnd-meta-label">Học viên:</span> ';
                $student_links = [];
                foreach ($students as $student) {
                    $student_links[] = '<a href="#" class="dnd-profile-link" data-user-id="' . esc_attr($student->ID) . '">' . esc_html($student->display_name) . '</a>';
                }
                $html .= implode(', ', $student_links);
                $html .= '</div>';
            }
            
            $html .= '</div>'; // End video-info
            
            $html .= '</div>'; // End video-card
        }
        
        $html .= '</div>';
        
        return $html;
    }

    private function extract_youtube_id($url) {
        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i', $url, $matches);
        return isset($matches[1]) ? $matches[1] : '';
    }
}

// Initialize the block
new DND_Speaking_Listening_Sessions_Block();

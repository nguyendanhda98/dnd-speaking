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
            $thumbnail = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";
            
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
            
            // Video header with title and metadata
            $html .= '<div class="dnd-video-header">';
            $html .= '<h3 class="dnd-video-title">' . esc_html($session['title']) . '</h3>';
            
            // Metadata row
            $html .= '<div class="dnd-video-metadata">';
            if (!empty($teachers)) {
                $teacher_names = array_map(function($t) { return esc_html($t->display_name); }, $teachers);
                $html .= '<span class="dnd-meta-item">👨‍🏫 ' . implode(', ', $teacher_names) . '</span>';
            }
            if (!empty($students)) {
                $student_names = array_map(function($s) { return esc_html($s->display_name); }, $students);
                $html .= '<span class="dnd-meta-item">👨‍🎓 ' . implode(', ', $student_names) . '</span>';
            }
            if (!empty($session['lesson_topic'])) {
                $html .= '<span class="dnd-meta-item">📚 ' . esc_html($session['lesson_topic']) . '</span>';
            }
            if (!empty($session['video_duration'])) {
                $html .= '<span class="dnd-meta-item">⏱️ ' . esc_html($session['video_duration']) . ' phút</span>';
            }
            $html .= '</div>';
            
            if (!empty($session['description'])) {
                $html .= '<p class="dnd-video-description">' . esc_html($session['description']) . '</p>';
            }
            $html .= '</div>';
            
            // Embedded YouTube iframe
            $html .= '<div class="dnd-video-embed">';
            $html .= '<iframe width="100%" height="100%" src="https://www.youtube.com/embed/' . esc_attr($video_id) . '" ';
            $html .= 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ';
            $html .= 'allowfullscreen></iframe>';
            $html .= '</div>';
            
            $html .= '</div>';
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

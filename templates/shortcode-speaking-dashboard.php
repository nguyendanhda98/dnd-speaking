<?php
/*
Template for shortcode speaking dashboard
*/
$user = wp_get_current_user();
if (in_array('student', $user->roles)) {
    echo do_shortcode('[dnd_student_dashboard]');
} elseif (in_array('teacher', $user->roles)) {
    include plugin_dir_path(__FILE__) . '../public/partials/dashboard-teacher.php';
} else {
    echo '<p>Please log in as student or teacher.</p>';
}

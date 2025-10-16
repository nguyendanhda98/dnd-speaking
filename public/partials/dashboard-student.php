<?php
$user_id = bp_displayed_user_id();
$credits = DND_Speaking_Helpers::get_user_credits($user_id);
?>
<div class="dnd-student-dashboard">
    <h2>Your Speaking Sessions</h2>
    <p>Remaining Sessions: <strong><?php echo $credits; ?></strong></p>

    <h3>Recent Sessions</h3>
    <?php
    global $wpdb;
    $table_sessions = $wpdb->prefix . 'dnd_speaking_sessions';
    $sessions = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_sessions WHERE student_id = %d ORDER BY start_time DESC LIMIT 10", $user_id));

    if ($sessions) {
        echo '<ul>';
        foreach ($sessions as $session) {
            $teacher = get_user_by('id', $session->teacher_id);
            echo '<li>' . $session->start_time . ' - ' . $teacher->display_name . ' - ' . $session->status . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No sessions yet.</p>';
    }
    ?>

    <a href="<?php echo site_url('/teacher-list/'); ?>" class="button">Book a New Session</a>
</div>

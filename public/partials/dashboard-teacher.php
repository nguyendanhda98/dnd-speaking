<?php
$user_id = get_current_user_id(); // Use current user
$available = DND_Speaking_Helpers::is_teacher_available($user_id);
?>
<div class="dnd-teacher-dashboard">
    <h2>Teacher Dashboard</h2>

    <div class="availability-toggle">
        <label>Available for sessions:</label>
        <input type="checkbox" id="availability" <?php checked($available); ?>>
    </div>

    <p>Bookings management will be added later.</p>
</div>

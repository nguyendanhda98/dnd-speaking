<?php
$user_id = bp_displayed_user_id();
$available = DND_Speaking_Helpers::is_teacher_available($user_id);
?>
<div class="dnd-teacher-dashboard">
    <h2>Teacher Dashboard</h2>

    <div class="availability-toggle">
        <label>Available for sessions:</label>
        <input type="checkbox" id="availability" <?php checked($available); ?>>
    </div>

    <h3>Pending Bookings</h3>
    <?php
    global $wpdb;
    $table = $wpdb->prefix . 'dnd_speaking_bookings';
    $bookings = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE teacher_id = %d AND status = 'pending'", $user_id));

    if ($bookings) {
        echo '<ul>';
        foreach ($bookings as $booking) {
            $student = get_user_by('id', $booking->student_id);
            echo '<li>' . $student->display_name . ' requested at ' . $booking->requested_time;
            echo ' <button class="approve-booking" data-booking-id="' . $booking->id . '">Approve</button>';
            echo ' <button class="reject-booking" data-booking-id="' . $booking->id . '">Reject</button></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No pending bookings.</p>';
    }
    ?>

    <script>
    jQuery(document).ready(function($) {
        $('#availability').on('change', function() {
            var available = $(this).is(':checked') ? 1 : 0;
            $.post(ajaxurl, {
                action: 'toggle_availability',
                available: available
            }, function(response) {
                alert(response);
            });
        });

        $('.approve-booking').on('click', function() {
            var bookingId = $(this).data('booking-id');
            // Implement approve logic, create session, etc.
            alert('Approved booking ' + bookingId);
        });

        $('.reject-booking').on('click', function() {
            var bookingId = $(this).data('booking-id');
            // Implement reject logic
            alert('Rejected booking ' + bookingId);
        });
    });
    </script>
</div>

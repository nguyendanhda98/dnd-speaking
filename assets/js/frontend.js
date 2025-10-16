jQuery(document).ready(function($) {
    $('.book-now').on('click', function() {
        var teacherId = $(this).data('teacher-id');
        alert('Booking request sent for teacher ' + teacherId);
        // Implement booking logic
    });

    $('.start-now').on('click', function() {
        var teacherId = $(this).data('teacher-id');
        alert('Starting session with teacher ' + teacherId);
        // Implement start session logic
    });
});
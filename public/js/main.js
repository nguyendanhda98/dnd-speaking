jQuery(document).ready(function($) {
    $('.book-teacher').on('click', function() {
        var teacherId = $(this).data('teacher-id');
        $.post(dnd_ajax.ajaxurl, {
            action: 'request_booking',
            teacher_id: teacherId,
            nonce: dnd_ajax.nonce
        }, function(response) {
            alert(response);
        });
    });
});

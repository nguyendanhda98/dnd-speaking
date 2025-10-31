jQuery(document).ready(function($) {
    'use strict';
    
    // Handle profile link clicks - temporarily do nothing
    $('.dnd-profile-link').on('click', function(e) {
        e.preventDefault();
        // TODO: Navigate to user profile page
        // var userId = $(this).data('user-id');
        // console.log('Profile link clicked for user ID:', userId);
    });
});

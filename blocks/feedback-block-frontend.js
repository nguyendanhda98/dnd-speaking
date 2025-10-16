/**
 * Feedback Block - Frontend JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Optional: Add smooth animations for feedback items
    $('.dnd-feedback-item').each(function(index) {
        const item = $(this);
        setTimeout(function() {
            item.addClass('animate-in');
        }, index * 100);
    });

    // Optional: Add hover effects for ratings
    $('.dnd-feedback-rating').hover(
        function() {
            $(this).addClass('rating-hover');
        },
        function() {
            $(this).removeClass('rating-hover');
        }
    );

    console.log('Feedback Block loaded');
});
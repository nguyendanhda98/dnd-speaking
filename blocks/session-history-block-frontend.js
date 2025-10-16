/**
 * Session History Block - Frontend JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Handle pagination links with AJAX loading (optional enhancement)
    $('.dnd-session-history').on('click', '.dnd-page-link', function(e) {
        e.preventDefault();

        const link = $(this);
        const url = new URL(link.attr('href'), window.location.origin);
        const page = url.searchParams.get('history_page');

        if (!page) return;

        // Show loading state
        const historyContainer = $('.dnd-session-history');
        const originalContent = historyContainer.html();

        historyContainer.html('<div style="text-align: center; padding: 40px;"><div>Loading...</div></div>');

        // In a real implementation, you might want to make an AJAX call here
        // For now, we'll just navigate to the new page
        window.location.href = link.attr('href');
    });

    // Optional: Add filter functionality (could be expanded)
    // This is a placeholder for potential future enhancements

    console.log('Session History Block loaded');
});
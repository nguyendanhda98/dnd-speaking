/**
 * Student Session History Block - Frontend JavaScript with AJAX
 */

jQuery(document).ready(function($) {
    'use strict';

    var $historyContainer = $('.dnd-student-session-history');
    var $filterForm = $('#dnd-student-history-filter-form');
    var currentPage = 1;
    var currentStatusFilter = 'all';
    var currentPerPage = 10;

    // Initialize current values from URL or defaults
    var urlParams = new URLSearchParams(window.location.search);
    currentPage = parseInt(urlParams.get('student_history_page')) || 1;
    currentStatusFilter = urlParams.get('student_status_filter') || 'all';
    currentPerPage = parseInt(urlParams.get('student_per_page')) || 10;

    // Load initial content
    loadSessionHistory();

    // Handle filter form submission
    $filterForm.on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        currentStatusFilter = formData.get('student_status_filter');
        currentPerPage = parseInt(formData.get('student_per_page'));
        currentPage = 1; // Reset to first page when filtering

        loadSessionHistory();
        updateURL();
    });

    // Handle pagination clicks
    $historyContainer.on('click', '.dnd-page-link', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        if (page) {
            currentPage = parseInt(page);
            loadSessionHistory();
            updateURL();
        }
    });

    function loadSessionHistory() {
        // Show loading state
        var $contentArea = $historyContainer.find('.dnd-history-content');
        $contentArea.html('<div class="dnd-loading" style="text-align: center; padding: 40px;">Loading...</div>');

        $.ajax({
            url: dnd_student_session_history_data.ajax_url,
            type: 'POST',
            data: {
                action: 'get_student_session_history',
                page: currentPage,
                per_page: currentPerPage,
                status_filter: currentStatusFilter,
                nonce: dnd_student_session_history_data.nonce
            },
            beforeSend: function(xhr) {
                console.log('AJAX Request - Action:', 'get_student_session_history');
                console.log('AJAX Request - Page:', currentPage);
                console.log('AJAX Request - Per Page:', currentPerPage);
                console.log('AJAX Request - Filter:', currentStatusFilter);
                console.log('AJAX Request - Nonce:', dnd_student_session_history_data.nonce);
                console.log('AJAX Request - User ID:', dnd_student_session_history_data.user_id);
            },
            success: function(response) {
                if (response.success) {
                    // Replace the content area with new HTML
                    $contentArea.html(response.html);
                } else {
                    console.error('Error loading student session history:', response);
                    $contentArea.html('<div class="dnd-error" style="text-align: center; padding: 40px; color: #dc3545;">Error loading data. Please try again.</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                if (xhr.status === 401 || xhr.status === 403) {
                    $contentArea.html('<div class="dnd-error" style="text-align: center; padding: 40px; color: #dc3545;">Please log in to view your session history.</div>');
                } else {
                    $contentArea.html('<div class="dnd-error" style="text-align: center; padding: 40px; color: #dc3545;">Error loading data. Please try again.</div>');
                }
            }
        });
    }

    function updateURL() {
        var url = new URL(window.location);
        url.searchParams.set('student_history_page', currentPage);
        if (currentStatusFilter !== 'all') {
            url.searchParams.set('student_status_filter', currentStatusFilter);
        } else {
            url.searchParams.delete('student_status_filter');
        }
        url.searchParams.set('student_per_page', currentPerPage);

        // Update URL without reloading page
        window.history.pushState({}, '', url);
    }

    // Handle browser back/forward buttons
    window.addEventListener('popstate', function() {
        var urlParams = new URLSearchParams(window.location.search);
        currentPage = parseInt(urlParams.get('student_history_page')) || 1;
        currentStatusFilter = urlParams.get('student_status_filter') || 'all';
        currentPerPage = parseInt(urlParams.get('student_per_page')) || 10;

        // Update form values
        $filterForm.find('select[name="student_status_filter"]').val(currentStatusFilter);
        $filterForm.find('select[name="student_per_page"]').val(currentPerPage);

        loadSessionHistory();
    });

    console.log('Student Session History Block with AJAX loaded');
});
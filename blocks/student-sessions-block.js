wp.blocks.registerBlockType('dnd-speaking/student-sessions', {
    title: 'DND Student Sessions',
    icon: 'calendar',
    category: 'widgets',
    edit: function(props) {
        return wp.element.createElement('div', { className: 'dnd-student-sessions-preview' },
            wp.element.createElement('h3', null, 'Student Sessions'),
            wp.element.createElement('div', { className: 'dnd-total-hours' }, 'Số giờ đã học: 6.5h'),
            wp.element.createElement('div', { className: 'dnd-sessions-filters' },
                wp.element.createElement('button', { className: 'dnd-filter-btn active' }, 'Tất cả (7)'),
                wp.element.createElement('button', { className: 'dnd-filter-btn' }, 'Chờ xác nhận (1)'),
                wp.element.createElement('button', { className: 'dnd-filter-btn' }, 'Đã xác nhận (2)'),
                wp.element.createElement('button', { className: 'dnd-filter-btn' }, 'Hoàn thành (5)'),
                wp.element.createElement('button', { className: 'dnd-filter-btn' }, 'Đã huỷ (0)')
            ),
            wp.element.createElement('div', { className: 'dnd-per-page-filter' },
                wp.element.createElement('label', null, 'Hiển thị:'),
                wp.element.createElement('select', null,
                    wp.element.createElement('option', null, '10'),
                    wp.element.createElement('option', null, '5'),
                    wp.element.createElement('option', null, '3'),
                    wp.element.createElement('option', null, '1')
                )
            ),
            wp.element.createElement('div', { className: 'dnd-session-sample' },
                wp.element.createElement('div', { className: 'dnd-session-teacher' }, 'Giáo viên: Teacher 1'),
                wp.element.createElement('div', { className: 'dnd-session-status pending' }, 'Trạng thái: Chờ xác nhận'),
                wp.element.createElement('div', { className: 'dnd-session-time' }, 'Thời gian: 20/10/2025 14:00'),
                wp.element.createElement('div', { className: 'dnd-session-actions' },
                    wp.element.createElement('button', { className: 'dnd-btn dnd-btn-cancel' }, 'Hủy')
                )
            )
        );
    },
    save: function() {
        return null; // Dynamic block
    },
});
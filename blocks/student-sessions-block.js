wp.blocks.registerBlockType('dnd-speaking/student-sessions', {
    title: 'DND Student Sessions',
    icon: 'calendar',
    category: 'widgets',
    edit: function(props) {
        return wp.element.createElement('div', { className: 'dnd-student-sessions-preview' },
            wp.element.createElement('h3', null, 'DND Student Sessions'),
            wp.element.createElement('p', null, 'Preview: Lịch học sắp tới của học viên sẽ hiển thị ở đây'),
            wp.element.createElement('div', { className: 'dnd-session-sample' },
                wp.element.createElement('div', { className: 'dnd-session-teacher' }, 'Giáo viên: Teacher 1'),
                wp.element.createElement('div', { className: 'dnd-session-status pending' }, 'Trạng thái: Chờ xác nhận'),
                wp.element.createElement('div', { className: 'dnd-session-time' }, 'Thời gian: 2025-10-20 14:00'),
                wp.element.createElement('button', { className: 'dnd-btn dnd-btn-cancel' }, 'Hủy buổi học')
            )
        );
    },
    save: function() {
        return null; // Dynamic block
    },
});
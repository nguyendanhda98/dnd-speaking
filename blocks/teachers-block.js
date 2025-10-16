wp.blocks.registerBlockType('dnd-speaking/teachers-list', {
    title: 'DND Teachers List',
    icon: 'groups',
    category: 'widgets',
    edit: function(props) {
        return wp.element.createElement('div', { className: 'dnd-teachers-preview' },
            wp.element.createElement('h3', null, 'DND Teachers List'),
            wp.element.createElement('p', null, 'Preview: Danh sách giáo viên sẽ hiển thị ở đây'),
            wp.element.createElement('div', { className: 'dnd-teacher-sample' },
                wp.element.createElement('div', { className: 'dnd-teacher-name' }, 'Teacher 1'),
                wp.element.createElement('div', { className: 'dnd-teacher-status online' }, 'Online'),
                wp.element.createElement('div', { className: 'dnd-teacher-buttons' },
                    wp.element.createElement('button', { className: 'dnd-btn dnd-btn-book' }, '📅 Book Now'),
                    wp.element.createElement('button', { className: 'dnd-btn dnd-btn-start' }, '🎤 Start Now')
                )
            )
        );
    },
    save: function() {
        return null; // Dynamic block
    },
});
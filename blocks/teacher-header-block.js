wp.blocks.registerBlockType('dnd-speaking/teacher-status', {
    title: 'Teacher Status',
    icon: 'dashboard',
    category: 'widgets',
    edit: function(props) {
        return wp.element.createElement('div', { className: 'dnd-teacher-status' },
            wp.element.createElement('div', { className: 'status-section' },
                wp.element.createElement('span', { className: 'status-label' }, 'Trạng thái:'),
                wp.element.createElement('div', { className: 'status-toggle-container' },
                    wp.element.createElement('span', { className: 'status-text offline' }, 'Offline'),
                    wp.element.createElement('label', { className: 'status-toggle-label' },
                        wp.element.createElement('input', { type: 'checkbox', defaultChecked: true }),
                        wp.element.createElement('span', { className: 'status-toggle-slider' })
                    ),
                    wp.element.createElement('span', { className: 'status-text online' }, 'Online')
                )
            ),
            wp.element.createElement('div', { className: 'room-section' },
                wp.element.createElement('span', { className: 'room-label' }, 'Room:'),
                wp.element.createElement('a', { href: '#', className: 'room-link' }, 'Link room')
            )
        );
    },
    save: function() {
        return null; // Dynamic block
    },
});
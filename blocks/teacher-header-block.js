wp.blocks.registerBlockType('dnd-speaking/teacher-header', {
    title: 'Teacher Dashboard Header',
    icon: 'dashboard',
    category: 'widgets',
    edit: function(props) {
        return wp.element.createElement('div', { className: 'dnd-teacher-header' },
            wp.element.createElement('div', { className: 'dnd-teacher-header-content' },
                wp.element.createElement('div', { className: 'dnd-availability-toggle' },
                    wp.element.createElement('label', { className: 'dnd-toggle-label' },
                        wp.element.createElement('input', { type: 'checkbox', defaultChecked: true }),
                        wp.element.createElement('span', { className: 'dnd-toggle-slider' })
                    ),
                    wp.element.createElement('span', { className: 'dnd-toggle-text' }, "I'm available")
                ),
                wp.element.createElement('div', { className: 'dnd-teacher-stats' },
                    wp.element.createElement('div', { className: 'dnd-stat-item' },
                        wp.element.createElement('span', { className: 'dnd-stat-number' }, '25'),
                        wp.element.createElement('span', { className: 'dnd-stat-label' }, 'Total Sessions')
                    ),
                    wp.element.createElement('div', { className: 'dnd-stat-item' },
                        wp.element.createElement('span', { className: 'dnd-stat-number' }, '3'),
                        wp.element.createElement('span', { className: 'dnd-stat-label' }, 'Upcoming')
                    )
                )
            )
        );
    },
    save: function() {
        return null; // Dynamic block
    },
});
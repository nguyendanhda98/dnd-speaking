(function(blocks, element) {
    var el = element.createElement;

    blocks.registerBlockType('dnd-speaking/student-session-history', {
        title: 'Student Session History',
        icon: 'list-view',
        category: 'dnd-speaking',
        edit: function(props) {
            return el('div', { className: 'dnd-student-session-history-editor' },
                el('h3', {}, 'Student Session History'),
                el('p', {}, 'This block displays the session history for the current logged-in student.')
            );
        },
        save: function() {
            return null; // Dynamic block
        }
    });
})(window.wp.blocks, window.wp.element);
wp.blocks.registerBlockType('dnd-speaking/discord-connect', {
    title: 'Discord Connect',
    icon: 'admin-links',
    category: 'widgets',
    edit: function(props) {
        return wp.element.createElement('div', { className: 'dnd-discord-connect-preview' },
            wp.element.createElement('h3', null, 'Discord Connect Block'),
            wp.element.createElement('p', null, 'Preview: Nút kết nối Discord sẽ hiển thị ở đây'),
            wp.element.createElement('button', { className: 'dnd-btn dnd-btn-discord' }, '🔗 Connect to Discord')
        );
    },
    save: function() {
        return null; // Dynamic block
    },
});
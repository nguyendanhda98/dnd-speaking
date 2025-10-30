wp.blocks.registerBlockType('dnd-speaking/listening-sessions', {
    title: 'DND Listening Sessions',
    icon: 'video-alt3',
    category: 'widgets',
    attributes: {
        title: {
            type: 'string',
            default: 'Nghe Buổi Học'
        }
    },
    edit: function(props) {
        const { attributes, setAttributes } = props;
        const { RichText } = wp.blockEditor;
        const { InspectorControls } = wp.blockEditor;
        const { PanelBody, TextControl } = wp.components;

        return wp.element.createElement('div', null,
            // Sidebar settings
            wp.element.createElement(InspectorControls, null,
                wp.element.createElement(PanelBody, { title: 'Cài đặt Block', initialOpen: true },
                    wp.element.createElement(TextControl, {
                        label: 'Tiêu đề',
                        value: attributes.title,
                        onChange: function(newTitle) {
                            setAttributes({ title: newTitle });
                        }
                    })
                )
            ),
            // Block preview
            wp.element.createElement('div', { className: 'dnd-listening-sessions-preview' },
                wp.element.createElement('h2', { className: 'dnd-listening-title' }, attributes.title),
                wp.element.createElement('div', { className: 'dnd-listening-info' },
                    wp.element.createElement('p', null, '📹 Block hiển thị video YouTube cho học viên'),
                    wp.element.createElement('p', null, '✏️ Quản trị viên có thể thêm/xóa video'),
                    wp.element.createElement('p', { style: { marginTop: '10px', fontSize: '12px', color: '#666' } },
                        'Preview: Video sẽ hiển thị dưới dạng lưới với thumbnail'
                    )
                ),
                wp.element.createElement('div', { className: 'dnd-video-sample' },
                    wp.element.createElement('div', { className: 'dnd-video-card-preview' },
                        wp.element.createElement('div', { className: 'dnd-video-thumbnail-preview' },
                            wp.element.createElement('div', { style: { 
                                backgroundColor: '#f0f0f0', 
                                height: '150px', 
                                display: 'flex', 
                                alignItems: 'center', 
                                justifyContent: 'center',
                                fontSize: '48px'
                            }}, '▶'),
                        ),
                        wp.element.createElement('div', { className: 'dnd-video-info' },
                            wp.element.createElement('h3', null, 'Video Title'),
                            wp.element.createElement('p', { style: { fontSize: '12px' } }, 'Video description...'),
                            wp.element.createElement('button', { className: 'dnd-btn-preview' }, 'Xem Video')
                        )
                    )
                )
            )
        );
    },
    save: function() {
        return null; // Dynamic block
    },
});

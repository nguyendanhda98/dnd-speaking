const { registerBlockType } = wp.blocks;
const { useBlockProps } = wp.blockEditor;

registerBlockType('dnd-speaking/credits-display', {
    title: 'DND Lessons Display',
    icon: 'money',
    category: 'widgets',
    edit: function(props) {
        const blockProps = useBlockProps();

        return wp.element.createElement(
            'div',
            blockProps,
            wp.element.createElement('div', { className: 'dnd-credits-display' }, 'Số buổi học hiện có: Preview')
        );
    },
    save: function() {
        return null; // Dynamic block
    },
});
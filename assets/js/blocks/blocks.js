const { registerBlockType } = wp.blocks;
const { __ } = wp.i18n;
const { useBlockProps } = wp.blockEditor;
const { useState, useEffect } = wp.element;
const apiFetch = wp.apiFetch;

// Block for Student Credits
registerBlockType('dnd-speaking/student-credits', {
    title: __('Student Credits', 'dnd-speaking'),
    icon: 'money',
    category: 'widgets',
    edit: function() {
        const blockProps = useBlockProps();
        return wp.element.createElement('div', blockProps, __('Student Credits Block', 'dnd-speaking'));
    },
    save: function() {
        return wp.element.createElement('div', useBlockProps.save(), __('Loading credits...', 'dnd-speaking'));
    },
});

// Block for Teachers List
registerBlockType('dnd-speaking/teachers-list', {
    title: __('Teachers List', 'dnd-speaking'),
    icon: 'groups',
    category: 'widgets',
    edit: function() {
        const blockProps = useBlockProps();
        return wp.element.createElement('div', blockProps, __('Teachers List Block', 'dnd-speaking'));
    },
    save: function() {
        return wp.element.createElement('div', useBlockProps.save(), __('Teachers list will load here.', 'dnd-speaking'));
    },
});
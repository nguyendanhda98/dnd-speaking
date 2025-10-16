/**
 * Teacher Requests Block - Editor Preview
 */

(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el } = wp.element;

    registerBlockType('dnd-speaking/teacher-requests', {
        title: 'Teacher Requests',
        icon: 'list-view',
        category: 'dnd-speaking-blocks',
        description: 'Display pending session requests for teachers',

        edit: function(props) {
            return el('div', {
                className: 'dnd-teacher-requests-editor-preview'
            }, [
                el('h3', { key: 'title' }, 'Teacher Requests'),
                el('div', {
                    key: 'preview',
                    className: 'dnd-requests-preview'
                }, [
                    el('div', {
                        key: 'sample-request',
                        className: 'dnd-request-item'
                    }, [
                        el('div', {
                            key: 'info',
                            className: 'dnd-request-info'
                        }, [
                            el('div', {
                                key: 'student',
                                className: 'dnd-student-name'
                            }, 'Sample Student'),
                            el('div', {
                                key: 'time',
                                className: 'dnd-request-time'
                            }, 'Requested: Dec 15, 2023 2:30 PM')
                        ]),
                        el('div', {
                            key: 'actions',
                            className: 'dnd-request-actions'
                        }, [
                            el('button', {
                                key: 'accept',
                                className: 'dnd-btn dnd-btn-accept'
                            }, 'Accept'),
                            el('button', {
                                key: 'decline',
                                className: 'dnd-btn dnd-btn-decline'
                            }, 'Decline')
                        ])
                    ]),
                    el('div', {
                        key: 'sample-request-2',
                        className: 'dnd-request-item'
                    }, [
                        el('div', {
                            key: 'info',
                            className: 'dnd-request-info'
                        }, [
                            el('div', {
                                key: 'student',
                                className: 'dnd-student-name'
                            }, 'Another Student'),
                            el('div', {
                                key: 'time',
                                className: 'dnd-request-time'
                            }, 'Requested: Dec 14, 2023 4:00 PM')
                        ]),
                        el('div', {
                            key: 'actions',
                            className: 'dnd-request-actions'
                        }, [
                            el('button', {
                                key: 'accept',
                                className: 'dnd-btn dnd-btn-accept'
                            }, 'Accept'),
                            el('button', {
                                key: 'decline',
                                className: 'dnd-btn dnd-btn-decline'
                            }, 'Decline')
                        ])
                    ])
                ])
            ]);
        },

        save: function() {
            // Dynamic block, render callback handles output
            return null;
        }
    });
})(window.wp);
/**
 * Upcoming Sessions Block - Editor Preview
 */

(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el } = wp.element;

    registerBlockType('dnd-speaking/upcoming-sessions', {
        title: 'Upcoming Sessions',
        icon: 'calendar-alt',
        category: 'dnd-speaking-blocks',
        description: 'Display confirmed upcoming sessions for teachers',

        edit: function(props) {
            return el('div', {
                className: 'dnd-upcoming-sessions-editor-preview'
            }, [
                el('h3', { key: 'title' }, 'Upcoming Sessions'),
                el('div', {
                    key: 'preview',
                    className: 'dnd-sessions-preview'
                }, [
                    el('div', {
                        key: 'sample-session',
                        className: 'dnd-session-item'
                    }, [
                        el('div', {
                            key: 'info',
                            className: 'dnd-session-info'
                        }, [
                            el('div', {
                                key: 'student',
                                className: 'dnd-student-name'
                            }, 'Sample Student'),
                            el('div', {
                                key: 'datetime',
                                className: 'dnd-session-datetime'
                            }, 'Dec 20, 2023 at 3:00 PM')
                        ]),
                        el('div', {
                            key: 'actions',
                            className: 'dnd-session-actions'
                        }, [
                            el('button', {
                                key: 'start',
                                className: 'dnd-btn dnd-btn-start'
                            }, 'Start Session'),
                            el('button', {
                                key: 'cancel',
                                className: 'dnd-btn dnd-btn-cancel'
                            }, 'Cancel')
                        ])
                    ]),
                    el('div', {
                        key: 'sample-session-2',
                        className: 'dnd-session-item'
                    }, [
                        el('div', {
                            key: 'info',
                            className: 'dnd-session-info'
                        }, [
                            el('div', {
                                key: 'student',
                                className: 'dnd-student-name'
                            }, 'Another Student'),
                            el('div', {
                                key: 'datetime',
                                className: 'dnd-session-datetime'
                            }, 'Dec 22, 2023 at 10:00 AM')
                        ]),
                        el('div', {
                            key: 'actions',
                            className: 'dnd-session-actions'
                        }, [
                            el('button', {
                                key: 'start',
                                className: 'dnd-btn dnd-btn-start'
                            }, 'Start Session'),
                            el('button', {
                                key: 'cancel',
                                className: 'dnd-btn dnd-btn-cancel'
                            }, 'Cancel')
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
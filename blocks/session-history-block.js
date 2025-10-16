/**
 * Session History Block - Editor Preview
 */

(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el } = wp.element;

    registerBlockType('dnd-speaking/session-history', {
        title: 'Session History',
        icon: 'backup',
        category: 'dnd-speaking-blocks',
        description: 'Display completed and cancelled session history for teachers',

        edit: function(props) {
            return el('div', {
                className: 'dnd-session-history-editor-preview'
            }, [
                el('h3', { key: 'title' }, 'Session History'),
                el('div', {
                    key: 'preview',
                    className: 'dnd-history-preview'
                }, [
                    el('div', {
                        key: 'session-1',
                        className: 'dnd-history-item completed'
                    }, [
                        el('div', {
                            key: 'header',
                            className: 'dnd-history-header'
                        }, [
                            el('div', {
                                key: 'student',
                                className: 'dnd-student-name'
                            }, 'Sample Student'),
                            el('div', {
                                key: 'status',
                                className: 'dnd-session-status completed'
                            }, 'Completed')
                        ]),
                        el('div', {
                            key: 'details',
                            className: 'dnd-history-details'
                        }, [
                            el('div', {
                                key: 'datetime',
                                className: 'dnd-session-datetime'
                            }, 'Dec 15, 2023 at 2:30 PM'),
                            el('div', {
                                key: 'duration',
                                className: 'dnd-session-duration'
                            }, 'Duration: 25 min')
                        ]),
                        el('div', {
                            key: 'feedback',
                            className: 'dnd-session-feedback'
                        }, [
                            el('strong', { key: 'label' }, 'Feedback: '),
                            'Great session! Student showed excellent progress.'
                        ])
                    ]),
                    el('div', {
                        key: 'session-2',
                        className: 'dnd-history-item cancelled'
                    }, [
                        el('div', {
                            key: 'header',
                            className: 'dnd-history-header'
                        }, [
                            el('div', {
                                key: 'student',
                                className: 'dnd-student-name'
                            }, 'Another Student'),
                            el('div', {
                                key: 'status',
                                className: 'dnd-session-status cancelled'
                            }, 'Cancelled')
                        ]),
                        el('div', {
                            key: 'details',
                            className: 'dnd-history-details'
                        }, [
                            el('div', {
                                key: 'datetime',
                                className: 'dnd-session-datetime'
                            }, 'Dec 14, 2023 at 4:00 PM'),
                            el('div', {
                                key: 'duration',
                                className: 'dnd-session-duration'
                            }, 'Duration: N/A')
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
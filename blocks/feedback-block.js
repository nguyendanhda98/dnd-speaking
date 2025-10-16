/**
 * Feedback Block - Editor Preview
 */

(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el } = wp.element;

    registerBlockType('dnd-speaking/feedback', {
        title: 'Teacher Feedback',
        icon: 'star-filled',
        category: 'dnd-speaking-blocks',
        description: 'Display student feedback and ratings for completed sessions',

        edit: function(props) {
            return el('div', {
                className: 'dnd-feedback-editor-preview'
            }, [
                el('h3', { key: 'title' }, 'Student Feedback'),
                el('div', {
                    key: 'summary',
                    className: 'dnd-feedback-summary'
                }, [
                    el('div', {
                        key: 'rating',
                        className: 'dnd-average-rating'
                    }, [
                        el('span', {
                            key: 'number',
                            className: 'dnd-rating-number'
                        }, '4.5'),
                        el('span', {
                            key: 'stars',
                            className: 'dnd-rating-stars'
                        }, '★★★★☆'),
                        el('span', {
                            key: 'count',
                            className: 'dnd-rating-count'
                        }, '(12 reviews)')
                    ])
                ]),
                el('div', {
                    key: 'preview',
                    className: 'dnd-feedback-preview'
                }, [
                    el('div', {
                        key: 'feedback-1',
                        className: 'dnd-feedback-item'
                    }, [
                        el('div', {
                            key: 'header',
                            className: 'dnd-feedback-header'
                        }, [
                            el('div', {
                                key: 'student',
                                className: 'dnd-student-name'
                            }, 'Sample Student'),
                            el('div', {
                                key: 'date',
                                className: 'dnd-feedback-date'
                            }, 'Dec 15, 2023')
                        ]),
                        el('div', {
                            key: 'rating',
                            className: 'dnd-feedback-rating'
                        }, [
                            '★★★★★',
                            el('span', {
                                key: 'text',
                                className: 'dnd-rating-text'
                            }, '5/5')
                        ]),
                        el('div', {
                            key: 'content',
                            className: 'dnd-feedback-content'
                        }, [
                            el('p', { key: 'text' }, 'Excellent teacher! Very patient and helpful. I learned a lot in this session.')
                        ])
                    ]),
                    el('div', {
                        key: 'feedback-2',
                        className: 'dnd-feedback-item'
                    }, [
                        el('div', {
                            key: 'header',
                            className: 'dnd-feedback-header'
                        }, [
                            el('div', {
                                key: 'student',
                                className: 'dnd-student-name'
                            }, 'Another Student'),
                            el('div', {
                                key: 'date',
                                className: 'dnd-feedback-date'
                            }, 'Dec 14, 2023')
                        ]),
                        el('div', {
                            key: 'rating',
                            className: 'dnd-feedback-rating'
                        }, [
                            '★★★★☆',
                            el('span', {
                                key: 'text',
                                className: 'dnd-rating-text'
                            }, '4/5')
                        ]),
                        el('div', {
                            key: 'content',
                            className: 'dnd-feedback-content'
                        }, [
                            el('p', { key: 'text' }, 'Good session overall. Could focus more on pronunciation practice next time.')
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
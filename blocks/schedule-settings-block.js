/**
 * Schedule Settings Block - Editor Preview
 */

(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement: el } = wp.element;

    registerBlockType('dnd-speaking/schedule-settings', {
        title: 'Schedule Settings',
        icon: 'clock',
        category: 'dnd-speaking-blocks',
        description: 'Allow teachers to configure their weekly availability schedule',

        edit: function(props) {
            return el('div', {
                className: 'dnd-schedule-settings-editor-preview'
            }, [
                el('h3', { key: 'title' }, 'Schedule Settings'),
                el('div', {
                    key: 'preview',
                    className: 'dnd-schedule-preview'
                }, [
                    el('div', {
                        key: 'monday',
                        className: 'dnd-day-setting'
                    }, [
                        el('label', {
                            key: 'toggle',
                            className: 'dnd-day-toggle'
                        }, [
                            el('input', {
                                key: 'checkbox',
                                type: 'checkbox',
                                defaultChecked: true
                            }),
                            el('span', {
                                key: 'slider',
                                className: 'dnd-toggle-slider'
                            }),
                            el('span', {
                                key: 'name',
                                className: 'dnd-day-name'
                            }, 'Monday')
                        ]),
                        el('div', {
                            key: 'times',
                            className: 'dnd-time-settings'
                        }, [
                            el('div', {
                                key: 'inputs',
                                className: 'dnd-time-inputs'
                            }, [
                                el('label', { key: 'start' }, [
                                    'Start: ',
                                    el('input', {
                                        key: 'start-input',
                                        type: 'time',
                                        defaultValue: '09:00'
                                    })
                                ]),
                                el('label', { key: 'end' }, [
                                    'End: ',
                                    el('input', {
                                        key: 'end-input',
                                        type: 'time',
                                        defaultValue: '17:00'
                                    })
                                ])
                            ])
                        ])
                    ]),
                    el('div', {
                        key: 'tuesday',
                        className: 'dnd-day-setting'
                    }, [
                        el('label', {
                            key: 'toggle',
                            className: 'dnd-day-toggle'
                        }, [
                            el('input', {
                                key: 'checkbox',
                                type: 'checkbox',
                                defaultChecked: false
                            }),
                            el('span', {
                                key: 'slider',
                                className: 'dnd-toggle-slider'
                            }),
                            el('span', {
                                key: 'name',
                                className: 'dnd-day-name'
                            }, 'Tuesday')
                        ]),
                        el('div', {
                            key: 'times',
                            className: 'dnd-time-settings',
                            style: { display: 'none' }
                        }, [
                            el('div', {
                                key: 'inputs',
                                className: 'dnd-time-inputs'
                            }, [
                                el('label', { key: 'start' }, [
                                    'Start: ',
                                    el('input', {
                                        key: 'start-input',
                                        type: 'time',
                                        defaultValue: '09:00'
                                    })
                                ]),
                                el('label', { key: 'end' }, [
                                    'End: ',
                                    el('input', {
                                        key: 'end-input',
                                        type: 'time',
                                        defaultValue: '17:00'
                                    })
                                ])
                            ])
                        ])
                    ]),
                    el('div', {
                        key: 'actions',
                        className: 'dnd-form-actions'
                    }, [
                        el('button', {
                            key: 'save',
                            className: 'dnd-btn dnd-btn-save'
                        }, 'Save Schedule')
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
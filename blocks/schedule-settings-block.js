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
                                key: 'slots',
                                className: 'dnd-time-slots'
                            }, [
                                el('div', {
                                    key: 'slot1',
                                    className: 'dnd-time-slot'
                                }, [
                                    el('div', {
                                        key: 'inputs1',
                                        className: 'dnd-time-inputs'
                                    }, [
                                        el('label', { key: 'start1' }, [
                                            'Start: ',
                                            el('input', {
                                                key: 'start-input1',
                                                type: 'time',
                                                defaultValue: '09:00'
                                            })
                                        ]),
                                        el('label', { key: 'end1' }, [
                                            'End: ',
                                            el('input', {
                                                key: 'end-input1',
                                                type: 'time',
                                                defaultValue: '12:00'
                                            })
                                        ])
                                    ])
                                ]),
                                el('div', {
                                    key: 'slot2',
                                    className: 'dnd-time-slot'
                                }, [
                                    el('div', {
                                        key: 'inputs2',
                                        className: 'dnd-time-inputs'
                                    }, [
                                        el('label', { key: 'start2' }, [
                                            'Start: ',
                                            el('input', {
                                                key: 'start-input2',
                                                type: 'time',
                                                defaultValue: '14:00'
                                            })
                                        ]),
                                        el('label', { key: 'end2' }, [
                                            'End: ',
                                            el('input', {
                                                key: 'end-input2',
                                                type: 'time',
                                                defaultValue: '18:00'
                                            })
                                        ])
                                    ])
                                ])
                            ]),
                            el('button', {
                                key: 'add-slot',
                                className: 'dnd-add-slot',
                                type: 'button'
                            }, 'Add Time Slot')
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
                                key: 'slots',
                                className: 'dnd-time-slots'
                            }, [
                                el('div', {
                                    key: 'slot1',
                                    className: 'dnd-time-slot'
                                }, [
                                    el('div', {
                                        key: 'inputs1',
                                        className: 'dnd-time-inputs'
                                    }, [
                                        el('label', { key: 'start1' }, [
                                            'Start: ',
                                            el('input', {
                                                key: 'start-input1',
                                                type: 'time',
                                                defaultValue: '09:00'
                                            })
                                        ]),
                                        el('label', { key: 'end1' }, [
                                            'End: ',
                                            el('input', {
                                                key: 'end-input1',
                                                type: 'time',
                                                defaultValue: '17:00'
                                            })
                                        ])
                                    ])
                                ])
                            ]),
                            el('button', {
                                key: 'add-slot',
                                className: 'dnd-add-slot',
                                type: 'button'
                            }, 'Add Time Slot')
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
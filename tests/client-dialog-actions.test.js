const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

function getHandler(source, startMarker, endMarker, context) {
    const start = source.indexOf(startMarker);
    const end = source.indexOf(endMarker, start);
    assert.ok(start >= 0 && end > start, `handler should exist: ${startMarker}`);
    vm.runInNewContext(source.slice(start, end + endMarker.length), context);
}

function createEnterHandler(targetType) {
    const document = {};
    const target = { type: targetType, insideDialog: true, isConfirm: true };
    const dialogElement = {};
    let clicks = 0;
    let handler;

    const confirmButton = {
        length: 1,
        last() {
            return this;
        },
        is(selector) {
            return selector === ':disabled' ? false : true;
        },
        trigger(action) {
            if (action === 'click') clicks++;
            return this;
        }
    };
    const dialog = {
        length: 1,
        filter(predicate) {
            return predicate.call(dialogElement) ? this : { length: 0 };
        },
        last() {
            return this;
        },
        find() {
            return confirmButton;
        }
    };
    const targetWrapper = {
        is(selector) {
            if (selector === 'textarea, [contenteditable=\'true\'], #input-chat input, .private-chat-input') {
                return false;
            }
            if (selector === 'input, select, button, a') {
                return target.type === 'button' || target.type === 'input';
            }
            if (selector === 'button, a') return target.type === 'button';
            if (selector === ':disabled') return false;
            return false;
        },
        closest() {
            return { length: target.insideDialog ? 1 : 0 };
        },
        hasClass(name) {
            return name === 'enter' && target.isConfirm;
        },
        trigger(action) {
            if (action === 'click') clicks++;
            return this;
        }
    };

    function $(value) {
        if (value === document) {
            return {
                on(eventName, callback) {
                    if (eventName === 'keydown') handler = callback;
                    return this;
                }
            };
        }
        if (value === dialogElement) {
            return { find: () => confirmButton };
        }
        if (value === target) return targetWrapper;
        if (typeof value === 'string') return dialog;
        return { length: 0 };
    }

    getHandler(
        clientSource,
        '$(document).on("keydown", function(event) {',
        '\n});',
        { document, $ }
    );

    return {
        trigger() {
            const event = {
                key: 'Enter',
                keyCode: 13,
                target,
                preventDefault() {},
                stopPropagation() {}
            };
            handler(event);
            return clicks;
        }
    };
}

test('Enter activates the focused dialog confirmation button', () => {
    assert.equal(createEnterHandler('button').trigger(), 1);
});

test('Enter from the dialog body activates its confirmation button', () => {
    assert.equal(createEnterHandler('body').trigger(), 1);
});

test('material-use confirmation focuses its Enter action', () => {
    assert.match(clientSource, /#over_lay_alert #useItemButton"\)\.trigger\("focus"\)/);
});

test('material dialog cancel closes only its alert overlay', () => {
    const document = {};
    let handler;
    let removed = false;
    const button = {};
    const event = { preventDefault() {} };
    const context = {
        document,
        $: value => {
            if (value === document) {
                return {
                    on(eventName, selector, callback) {
                        if (eventName === 'click' && selector === '#over_lay_alert .battel-can') {
                            handler = callback;
                        }
                        return this;
                    }
                };
            }
            if (value === button) {
                return {
                    closest(selector) {
                        assert.equal(selector, '#over_lay_alert');
                        return { remove() { removed = true; } };
                    }
                };
            }
        }
    };

    getHandler(
        clientSource,
        '$(document).on("click", "#over_lay_alert .battel-can", function(event) {',
        '\n});',
        context
    );
    handler.call(button, event);
    assert.equal(removed, true);
});

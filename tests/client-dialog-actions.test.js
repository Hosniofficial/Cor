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

test('dialog close targets the owning primary or secondary dialog', () => {
    function closeDialog(dialogId) {
        const document = {};
        const button = {};
        let handler;
        let removed = false;
        let animated = false;
        let closeCalls = 0;
        const box = {
            length: 1,
            is(selector) {
                return selector === '#dialg_box' && dialogId === '#dialg_box';
            },
            animate(properties, duration, easing, callback) {
                assert.equal(properties.top, '-250px');
                assert.equal(duration, 200);
                assert.equal(easing, 'linear');
                animated = true;
                callback.call(this);
            }
        };
        const context = {
            document,
            $: value => {
                if (value === document) {
                    return {
                        on(eventName, selector, callback) {
                            if (eventName === 'click' && selector === '.close_dialog') {
                                handler = callback;
                            }
                            return this;
                        }
                    };
                }
                if (value === button) {
                    return {
                        closest(selector) {
                            assert.equal(selector, '#dialg_box, #dialg_box_two');
                            return box;
                        }
                    };
                }
                if (value === box) return { remove() { removed = true; } };
            },
            dialogBoxClose() {
                closeCalls++;
            }
        };

        getHandler(
            clientSource,
            '$(document).on("click", ".close_dialog", function(e) {',
            '\n});',
            context
        );
        handler.call(button, { preventDefault() {} });
        return { animated, removed, closeCalls };
    }

    assert.deepEqual(closeDialog('#dialg_box'), {
        animated: true,
        removed: true,
        closeCalls: 1
    });
    assert.deepEqual(closeDialog('#dialg_box_two'), {
        animated: true,
        removed: true,
        closeCalls: 0
    });
});

test('palace, building, world, and king dialogs place close icons at the top-right', () => {
    const cssFiles = [
        ['lastOfLast.css', /#dialg_box \.nav_bar \.nav_icon img\.close_dialog,[\s\S]*?top:\s*1px;\s*right:\s*1px/],
        ['city.css', /\.box_header \.close_dialog\s*\{[\s\S]*?top:\s*1px;\s*right:\s*1px[\s\S]*?\}/],
        ['world.css', /#unit_review \.header \.close_RB img\s*\{[\s\S]*?top:\s*1px;\s*right:\s*1px[\s\S]*?\}/],
        ['lastOfLast.css', /#select_from \.head_bar > \.close[\s\S]*?top:\s*1px;\s*right:\s*1px[\s\S]*?\}/]
    ];

    for (const [file, pattern] of cssFiles) {
        const css = fs.readFileSync(
            path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', file),
            'utf8'
        ).replace(/\r\n/g, '\n');
        assert.match(css, pattern, `${file} should keep the close control at the top-right`);
    }
});

test('building, army, and study acceleration panels use the exchange panel frame', () => {
    const cityCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'city.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const finishingCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'finishing.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');

    for (const [selector, source] of [
        ['.worker', cityCss],
        ['.current-working', cityCss],
        ['.study_unite', finishingCss]
    ]) {
        const start = source.indexOf(`${selector} {`);
        assert.ok(start >= 0, `${selector} acceleration panel should exist`);
        const end = source.indexOf('}', start);
        const panel = source.slice(start, end + 1);
        assert.match(panel, /background-image:\s*url\(\.\.\/images\/background\/back-shape\/left-content\.png\)/);
        assert.match(panel, /background-size:\s*100%\s+100%/);
        assert.match(panel, /border:\s*6px\s+solid\s+transparent/);
    }
});

test('arrow navigation controls use authentic left and right button assets', () => {
    const baseCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'base.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const uiCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'ui.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');

    assert.match(baseCss, /\.left-btn\s*\{[\s\S]*?url\(\.\.\/images\/btns\/left\.png\)/);
    assert.match(baseCss, /\.right-btn\s*\{[\s\S]*?url\(\.\.\/images\/btns\/right\.png\)/);
    assert.match(baseCss, /\.GO_L_1\s*\{[\s\S]*?url\(\.\.\/images\/btns\/left\.png\)/);
    assert.match(baseCss, /\.GO_R_1\s*\{[\s\S]*?url\(\.\.\/images\/btns\/right\.png\)/);

    assert.match(uiCss, /\.goLeft\s*\{[\s\S]*?url\(\.\.\/images\/btns\/left\.png\)/);
    assert.match(uiCss, /\.goRight\s*\{[\s\S]*?url\(\.\.\/images\/btns\/right\.png\)/);
    assert.match(uiCss, /\.goUp\s*\{[\s\S]*?url\(\.\.\/images\/btns\/left\.png\)/);
    assert.match(uiCss, /\.goDown\s*\{[\s\S]*?url\(\.\.\/images\/btns\/right\.png\)/);
});

test('building upgrade and study requirement containers use the shared panel frame', () => {
    const cityCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'city.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const lastOfLastCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastOfLast.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');

    assert.match(cityCss, /\.req_table_container\s*\{[\s\S]*?url\(\.\.\/images\/background\/roman_panel_frame\.jpg\)/);
    assert.match(lastOfLastCss, /\.upgrade_req_tooltip \.req_table_container[\s\S]*?url\(\.\.\/images\/background\/roman_panel_frame\.jpg\)/);
});

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');
const handlerStart = clientSource.indexOf('$(document).on("mouseenter", ".tooltip_mat", function() {');
const handlerEnd = clientSource.indexOf('/*   REMOVE TOOLTIP  */', handlerStart);
assert.ok(handlerStart >= 0 && handlerEnd > handlerStart, 'item tooltip handler should exist');
const tooltipHandler = clientSource.slice(handlerStart, handlerEnd);

function renderTooltip(itemData) {
    let callback;
    let tooltipHtml;
    const document = {};
    const element = {
        attr() {
            return 'test_item';
        },
        children() {
            return {
                html(value) {
                    tooltipHtml = value;
                }
            };
        }
    };
    const $ = target => target === document
        ? { on(event, selector, handler) { callback = handler; } }
        : target;

    vm.runInNewContext(tooltipHandler, {
        document,
        $,
        Caesar: { BaseData: { Items: { test_item: itemData } } }
    });
    callback.call(element);
    return tooltipHtml;
}

test('hover tooltip omits a misleading zero-gold price', () => {
    const html = renderTooltip({ name: 'Item', gold: 0, desc: '', long_desc: '' });

    assert.match(html, /Item/);
    assert.doesNotMatch(html, /mat_price|gold\.png/);
});

test('hover tooltip still shows positive gold prices', () => {
    const html = renderTooltip({ name: 'Item', gold: 5, desc: '', long_desc: '' });

    assert.match(html, /class="mat_price pull-L"/);
    assert.match(html, /<p class="pull-L">5<\/p>/);
    assert.match(html, /images\/icons\/resource\/gold\.png/);
});

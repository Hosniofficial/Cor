const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

test('exchange pagination filters the selected category and rejects pages past its end', () => {
    const updates = [];
    const items = [
        ...Array.from({ length: 10 }, (_, index) => [
            { id: `event-${index + 1}`, cat: 'trade-event' },
            { id: `daily-${index + 1}`, cat: 'trade-daily' }
        ]).flat(),
        ...Array.from({ length: 5 }, (_, index) => ({ id: `extra-event-${index + 1}`, cat: 'trade-event' }))
    ];
    const context = {
        Caesar: { Item: {} },
        EXCHANGE_ITEM: items,
        Trading: { content_unit: item => `<item>${item.id}</item>` },
        updateDialogPager: (page, count) => updates.push([page, count]),
        $: () => ({ attr() { return this; } })
    };
    const start = clientSource.indexOf('Caesar.Item.ItemExchangeBox = function(Box, pageOffset = 1) {');
    const end = clientSource.indexOf('\n\nCaesar.Item.EquipBox = function', start);
    assert.ok(start >= 0 && end > start, 'exchange page renderer should exist');
    vm.runInNewContext(clientSource.slice(start, end), context);

    const secondPage = context.Caesar.Item.ItemExchangeBox('trade-daily', 2);
    assert.equal(secondPage, '<item>daily-10</item>');
    assert.deepEqual(updates, [[2, 2]]);
    assert.equal(context.Caesar.Item.ItemExchangeBox('trade-daily', 3), '');
    assert.deepEqual(updates, [[2, 2]]);
});

test('dialog page rendering does not replace content when the requested page is out of range', () => {
    const renderedPages = [];
    let replacedContent = null;
    const context = {
        $: selector => ({
            attr: name => {
                if (selector === '#dialg_box .box_content' && name === 'data-page-for')
                    return 'exchange';
                if (selector === '#dialg_box .right-content .total' && name === 'mat_table')
                    return 'trade-daily';
                return undefined;
            },
            html: value => { replacedContent = value; }
        }),
        getDialogPageCount: () => 1,
        Caesar: { Item: { ItemExchangeBox: (category, page) => {
            renderedPages.push([category, page]);
            return '<item>valid</item>';
        } } },
        Trading: {}
    };
    const start = clientSource.indexOf('function renderDialogPage(pageOffset) {');
    const end = clientSource.indexOf('\n\n$(document).on("click", "#nav-item-box-right"', start);
    assert.ok(start >= 0 && end > start, 'dialog page renderer should exist');
    vm.runInNewContext(clientSource.slice(start, end), context);

    context.renderDialogPage(2);
    assert.deepEqual(renderedPages, []);
    assert.equal(replacedContent, null);

    context.renderDialogPage(1);
    assert.deepEqual(renderedPages, [['trade-daily', 1]]);
    assert.equal(replacedContent, '<item>valid</item>');
});

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
);

function createEquipmentList(equipment) {
    const start = clientSource.indexOf("'getEquipList': function(offset, partFilter) {");
    const end = clientSource.indexOf("\n    'HeroEquipUnit':", start);
    assert.ok(start >= 0 && end > start, 'equipment list method should exist');

    const methodSource = clientSource.slice(start, end).trim().replace(/,$/, '');
    const selectors = {};
    const context = {
        Caesar: { DPlayer: { Equip: equipment } },
        Equipment: { getImage: (type, part, level) => `${type}-${part}-${level}` },
        $: selector => ({
            html: value => { selectors[selector] = value; },
            attr: (name, value) => { selectors[`${selector}.${name}`] = value; }
        })
    };
    const getEquipList = vm.runInNewContext(`({${methodSource}}).getEquipList`, context);

    return { getEquipList, selectors };
}

test('equipment list keeps filtering, assignment, and empty-slot behavior', () => {
    const equipment = {
        0: { id_equip: 10, type: 1, part: 'helmet', lvl: 2, id_hero: 0 },
        1: { id_equip: 11, type: 2, part: 'sword', lvl: 1, id_hero: 0 },
        2: { id_equip: 12, type: 3, part: 'helmet', lvl: 4, id_hero: 5 },
        3: { id_equip: 13, type: 4, part: 'helmet', lvl: 5, id_hero: 0 }
    };
    const { getEquipList, selectors } = createEquipmentList(equipment);
    const html = getEquipList(0, 'helmet');
    const renderedCards = [...html.matchAll(/class="unite-eq"/g)];

    assert.equal(renderedCards.length, 24);
    assert.match(html, /id_equip ="10"/);
    assert.match(html, /id_equip ="13"/);
    assert.doesNotMatch(html, /id_equip ="11"/);
    assert.doesNotMatch(html, /id_equip ="12"/);
    assert.match(html, /background-image: url\(images\/icons\/hero\/eq-bg\.png\)/);
    assert.equal(selectors['#navigate-btn.data-offset'], 0);
    assert.equal(selectors['#navigate-btn .lable'], '1/1');
});

test('equipment pagination uses filtered item indexes and ceiling page counts', () => {
    const equipment = Array.from({ length: 50 }, (_, index) => ({
        id_equip: index + 1,
        type: 1,
        part: index % 2 === 0 ? 'helmet' : 'sword',
        lvl: 1,
        id_hero: 0
    }));
    const { getEquipList, selectors } = createEquipmentList(equipment);

    const firstPage = getEquipList(0, 'helmet');
    const firstPageIds = [...firstPage.matchAll(/id_equip ="(\d+)"/g)].map(match => Number(match[1]));
    assert.deepEqual(firstPageIds, Array.from({ length: 24 }, (_, index) => index * 2 + 1));
    assert.match(firstPage, /data-offset="23"/);
    assert.equal(selectors['#navigate-btn .lable'], '1/2');
    assert.equal(selectors['#navigate-btn.data-offset'], 0);

    const secondPage = getEquipList(24, 'helmet');
    const secondPageIds = [...secondPage.matchAll(/id_equip ="(\d+)"/g)].map(match => Number(match[1]));
    assert.deepEqual(secondPageIds, [49]);
    assert.match(secondPage, /data-offset="24"/);
    assert.equal(selectors['#navigate-btn .lable'], '2/2');
    assert.equal(selectors['#navigate-btn.data-offset'], 24);

    const clampedPage = getEquipList(999, 'helmet');
    assert.deepEqual([...clampedPage.matchAll(/id_equip ="(\d+)"/g)].map(match => Number(match[1])), [49]);
    assert.equal(selectors['#navigate-btn.data-offset'], 24);
});

test('equipment page counts handle empty, exact, and partial pages', () => {
    for (const count of [0, 1, 23, 24, 25]) {
        const equipment = Array.from({ length: count }, (_, index) => ({
            id_equip: index + 1,
            type: 1,
            part: 'helmet',
            lvl: 1,
            id_hero: 0
        }));
        const { getEquipList, selectors } = createEquipmentList(equipment);
        const firstPage = getEquipList(0, 'helmet');
        const firstPageIds = [...firstPage.matchAll(/id_equip ="(\d+)"/g)];
        const totalPages = Math.max(Math.ceil(count / 24), 1);

        assert.equal(firstPageIds.length, Math.min(count, 24), `first page for ${count} items`);
        assert.equal(selectors['#navigate-btn .lable'], `1/${totalPages}`, `page label for ${count} items`);

        if (count === 25) {
            const secondPage = getEquipList(24, 'helmet');
            assert.deepEqual([...secondPage.matchAll(/id_equip ="(\d+)"/g)].map(match => Number(match[1])), [25]);
            assert.equal(selectors['#navigate-btn .lable'], '2/2');
        }
    }
});

test('equipment navigation advances by page and stops at either boundary', () => {
    const handlers = {};
    const calls = [];
    const document = {};
    const state = { offset: '0', pageLabel: '1/2', part: 'helmet' };
    const start = clientSource.indexOf('$(document).on("click", "#navigate-btn .go-one-page-eq-left"');
    const end = clientSource.indexOf('/// put equipment on hero', start);
    assert.ok(start >= 0 && end > start, 'equipment navigation handlers should exist');

    const context = {
        document,
        $: selector => {
            if (selector === document) {
                return { on: (event, name, handler) => { handlers[name] = handler; } };
            }
            if (selector === '#navigate-btn') {
                return { attr: () => state.offset };
            }
            if (selector === '#navigate-btn .lable') {
                return { text: () => state.pageLabel };
            }
            if (selector === '#eq-part-select .selected button') {
                return { attr: () => state.part };
            }
            if (selector === '#equip-list-heroDia') {
                return { html: () => {} };
            }
            throw new Error(`Unexpected selector: ${selector}`);
        },
        army: {
            getEquipList: (offset, part) => {
                calls.push([offset, part]);
                return '';
            }
        }
    };
    vm.runInNewContext(clientSource.slice(start, end), context);

    handlers['#navigate-btn .go-one-page-eq-right']();
    assert.deepEqual(calls, [[24, 'helmet']]);

    state.offset = '24';
    state.pageLabel = '2/2';
    handlers['#navigate-btn .go-one-page-eq-right']();
    handlers['#navigate-btn .go-one-page-eq-left']();
    assert.deepEqual(calls, [[24, 'helmet'], [0, 'helmet']]);

    state.offset = '0';
    handlers['#navigate-btn .go-one-page-eq-left']();
    assert.deepEqual(calls, [[24, 'helmet'], [0, 'helmet']]);
});

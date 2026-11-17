const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

test('un-equipping equipment handles API responses and reports raw errors', () => {
    const start = clientSource.indexOf('Caesar.Hero.getEquipOffHero = function(idEquip) {');
    const end = clientSource.indexOf(
        '\n\n$(document).on("hero-equip:remove", function(event, id_equip) {',
        start
    );
    assert.ok(start >= 0 && end > start, 'equipment removal method should exist');

    const units = Object.create(null);
    const errors = [];
    let ajaxRequest;
    let distributionCount = 0;
    const context = {
        Caesar: {
            Config: { AuthToken: 'test-token', idServer: 3 },
            Hero: {},
            LBase: {
                isJson: text => {
                    try {
                        JSON.parse(text);
                        return true;
                    } catch {
                        return false;
                    }
                },
                Error: response => {
                    errors.push(response);
                    return response;
                }
            },
            Equip: {
                getEquipUnit: id => units[id] || (units[id] = {}),
                distributeEquip: () => { distributionCount++; }
            },
            City: { getCityHero: () => {} }
        },
        $: { ajax: options => (ajaxRequest = options) },
        WS_HOST: 'localhost',
        WS_PORT: 9000,
        waitCursor: () => {},
        unwaitCursor: () => {},
        alert_box: { confirmMessage: () => {} }
    };
    vm.runInNewContext(clientSource.slice(start, end), context);

    context.Caesar.Hero.getEquipOffHero(17);
    assert.equal(ajaxRequest.url, 'https://localhost:9000/api/AHeroEquip/putEquipOffHero');
    assert.deepEqual({ ...ajaxRequest.data }, {
        idEquip: 17,
        token: 'test-token',
        server: 3
    });

    const rawError = 'PHP warning: invalid response';
    assert.equal(ajaxRequest.success(rawError, 'success', {}), rawError);
    assert.deepEqual(errors, [rawError]);

    ajaxRequest.success(JSON.stringify({
        state: 'ok',
        PlayerEquip: [{ id_equip: 17, id_hero: 4, on_hero: 'helmet' }]
    }), 'success', {});
    assert.deepEqual({ ...units[17] }, { id_hero: 4, on_hero: 'helmet' });
    assert.equal(distributionCount, 1);
});

test('equipment actions are opened by one click and menu choices dispatch the matching action', () => {
    assert.match(clientSource, /\$\(document\)\.on\("click", "\.putable-equi, \.on_equip"/);
    assert.doesNotMatch(clientSource, /\$\(document\)\.on\("dblclick", "\.(?:putable-equi|on_equip)"/);

    const start = clientSource.indexOf(
        '$(document).on("click", "#hero-equipment-action-menu button", function(event) {'
    );
    const end = clientSource.indexOf('\n\n$(document).on("click", function(event) {', start);
    assert.ok(start >= 0 && end > start, 'equipment action menu handler should exist');

    const document = {};
    const handlers = {};
    const dispatched = [];
    const context = {
        document,
        closeHeroEquipmentActionMenu: () => {},
        $: selector => {
            if (selector === document) {
                return {
                    on: (event, name, handler) => { handlers[name] = handler; },
                    trigger: (name, args) => { dispatched.push([name, ...args]); }
                };
            }
            if (selector === '#hero-equipment-action-menu') {
                return { remove: () => {} };
            }
            return {
                attr: name => selector.attrs[name]
            };
        }
    };
    vm.runInNewContext(clientSource.slice(start, end), context);

    for (const [action, expectedEvent] of [
        ['put', 'hero-equip:put'],
        ['remove', 'hero-equip:remove']
    ]) {
        handlers['#hero-equipment-action-menu button'].call({
            attrs: { 'data-action': action, 'data-equip-id': '17' }
        }, {
            preventDefault: () => {},
            stopPropagation: () => {}
        });
        assert.deepEqual(dispatched.pop(), [expectedEvent, 17]);
    }
});

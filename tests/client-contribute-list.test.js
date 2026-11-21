const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

function createJQueryPromise(promise) {
    const wrapped = {
        then(onFulfilled, onRejected) {
            return createJQueryPromise(promise.then(onFulfilled, onRejected));
        },
        done(callback) {
            promise.then(callback);
            return wrapped;
        },
        fail(callback) {
            promise.catch(callback);
            return wrapped;
        },
        always(callback) {
            promise.then(callback, callback);
            return wrapped;
        },
        promise() {
            return wrapped;
        }
    };
    return wrapped;
}

function createJQueryDeferred() {
    let resolve;
    let reject;
    const promise = createJQueryPromise(new Promise((resolvePromise, rejectPromise) => {
        resolve = resolvePromise;
        reject = rejectPromise;
    }));
    return { promise, resolve, reject };
}

test('contribute list loads only after the player response configures its JSON version', () => {
    const loginRequest = clientSource.indexOf('url: API_URL + "/home/HLogIn/playerEnterServerWeb"');
    const versionAssignment = clientSource.indexOf(
        "Caesar['Config']['JsVersion'] = JsonData['JsVersion'];",
        loginRequest
    );
    const loadList = clientSource.indexOf("Caesar['Contribute']['getList']();");
    const playerReady = clientSource.indexOf("$('html')['trigger']('PlayerReady');", loginRequest);

    assert.ok(loginRequest >= 0, 'player login request should exist');
    assert.ok(versionAssignment > loginRequest, 'JSON version should come from the player response');
    assert.ok(loadList > versionAssignment, 'contribute list should load after the version is set');
    assert.ok(playerReady > loadList, 'contribute list should start loading before PlayerReady');
    assert.equal(
        (clientSource.match(/Caesar\['Contribute'\]\['getList'\]\(\);/g) || []).length,
        1,
        'contribute list should have one controlled load point'
    );
});

test('contribute list URL includes the configured JSON version', () => {
    const start = clientSource.indexOf('Caesar.Contribute.getList = function() {');
    const end = clientSource.indexOf('\n};', start);
    assert.ok(start >= 0 && end > start, 'contribute list loader should exist');

    let request;
    const context = {
        API_URL: 'http://localhost:8092',
        Caesar: { Config: { JsVersion: '-0.0.24' }, Contribute: {} },
        $: { ajax: options => { request = options; } }
    };

    vm.runInNewContext(clientSource.slice(start, end + 3), context);
    context.Caesar.Contribute.getList();

    assert.equal(request.url, 'http://localhost:8092/js-0.0.24/json/contribute.json');
});

test('equipment power is fetched per item and cached for repeated tooltips', async () => {
    const start = clientSource.indexOf('Caesar.Equip.getEquipmentPower = function(equip, part, lvl) {');
    const end = clientSource.indexOf('\n};\n\nCaesar.Equip.getPlayerAmount', start);
    assert.ok(start >= 0 && end > start, 'equipment power loader should exist');

    const pending = createJQueryDeferred();
    const requests = [];
    const jquery = () => {};
    jquery.ajax = options => {
        requests.push(options);
        return pending.promise;
    };
    jquery.Deferred = () => {
        const deferred = createJQueryDeferred();
        return {
            resolve(value) {
                deferred.resolve(value);
                return this;
            },
            promise: () => deferred.promise
        };
    };
    const context = {
        API_URL: 'http://localhost:8092',
        $: jquery,
        Caesar: {
            Config: { AuthToken: 'session-token' },
            Equip: { PowerCache: {}, PowerRequests: {} }
        }
    };

    vm.runInNewContext(clientSource.slice(start, end + 3), context);
    const first = context.Caesar.Equip.getEquipmentPower('loy', 'belt', 1);
    const duplicate = context.Caesar.Equip.getEquipmentPower('loy', 'belt', 1);
    assert.equal(first, duplicate, 'overlapping requests should share the same promise');
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, 'http://localhost:8092/api/AItem/getEquipmentPower');
    assert.equal(JSON.stringify(requests[0].data), JSON.stringify({
        equip: 'loy',
        part: 'belt',
        lvl: 1,
        token: 'session-token'
    }));

    const power = {
        attack: 0, defence: 2, vitality: 0, damage: 0,
        break: 0, anti_break: 0, strike: 0, immunity: 0, sp_attr: 0
    };
    pending.resolve({ state: 'ok', Power: power });
    assert.deepEqual(JSON.parse(JSON.stringify(await first)), power);
    assert.deepEqual(
        JSON.parse(JSON.stringify(await context.Caesar.Equip.getEquipmentPower('loy', 'belt', 1))),
        power
    );
    assert.equal(requests.length, 1, 'cached requests should not hit the API again');
});

test('empty hero equipment slots do not request equipment power', () => {
    const start = clientSource.indexOf('$(document).on("mouseenter", ".equip-unit", function() {');
    const end = clientSource.indexOf('\n});\n\n$(document).on("mouseleave", ".equip-unit"', start);
    assert.ok(start >= 0 && end > start, 'equipment hover handler should exist');

    const hoverHandler = clientSource.slice(start, end);
    const emptySlotGuard = hoverHandler.indexOf('if (!equipType || equipType === "none")');
    const powerRequest = hoverHandler.indexOf('Caesar.Equip.getEquipmentPower(');
    assert.ok(emptySlotGuard >= 0, 'empty equipment should be ignored');
    assert.ok(powerRequest > emptySlotGuard, 'empty slot should be ignored before requesting power');
});

test('contribution and reward previews use cached power data absent from the equipment catalog', () => {
    const powerStart = clientSource.indexOf('getPowerMarkup: function(equip, part, lvl = 1) {');
    const powerEnd = clientSource.indexOf('\n    },\n    getImage:', powerStart);
    assert.ok(powerStart >= 0 && powerEnd > powerStart, 'equipment power renderer should exist');

    const powerRenderer = vm.runInNewContext(
        `({${clientSource.slice(powerStart, powerEnd + 6).replace(/,\s*$/, '')}}).getPowerMarkup`,
        {
            Caesar: {
                Equip: {
                    EquipList: { 'loy_belt_1': { name: 'حزام الولاء' } },
                    EquipFeature: { 0: { Title: 'لا توجد' } },
                    PowerCache: {
                        'loy_belt_1': {
                            attack: 0, defence: 2, vitality: 0, damage: 0,
                            break: 0, anti_break: 0, strike: 0, immunity: 0, sp_attr: 0
                        },
                        'loy_belt_2': {
                            attack: 0, defence: 5, vitality: 0, damage: 0,
                            break: 0, anti_break: 0, strike: 0, immunity: 0, sp_attr: 0
                        }
                    }
                }
            }
        }
    );
    const equipment = {
        getData: () => ({ image: 'images/equip/equip5.jpg', name: 'حزام الولاء' }),
        getPowerMarkup: powerRenderer
    };
    const recipe = {
        ListOfNeed: [
            { type: 'equip', equip: 'loy', part: 'belt', lvl: 1 },
            { type: 'promotion', promotion: 1 }
        ],
        Reword: [{ type: 'equip', equip: 'loy', part: 'belt', lvl: 2, amount: 1 }]
    };
    const context = {
        Caesar: {
            Contribute: { List: { recipe } },
            DPlayer: { Player: { porm: 1 } },
            BaseData: { Promotion: { 1: { Title: 'رتبة' } } },
            Equip: {
                EquipList: { 'loy_belt_1': { name: 'حزام الولاء' } },
                EquipFeature: { 0: { Title: 'لا توجد' } },
                PowerCache: {
                    'loy_belt_1': {
                        attack: 0, defence: 2, vitality: 0, damage: 0,
                        break: 0, anti_break: 0, strike: 0, immunity: 0, sp_attr: 0
                    },
                    'loy_belt_2': {
                        attack: 0, defence: 5, vitality: 0, damage: 0,
                        break: 0, anti_break: 0, strike: 0, immunity: 0, sp_attr: 0
                    }
                }
            }
        },
        Equipment: equipment
    };

    const requiredStart = clientSource.indexOf('Caesar.Contribute.ToUpgradeBox = function(idCont) {');
    const requiredEnd = clientSource.indexOf('\n};\n\n\nCaesar.Contribute.PrizeBox', requiredStart);
    const rewardStart = clientSource.indexOf('Caesar.Contribute.PrizeBox = function(idCont) {');
    const rewardEnd = clientSource.indexOf('\n};\n\n\nCaesar.Contribute.RequireList', rewardStart);
    assert.ok(requiredStart >= 0 && requiredEnd > requiredStart, 'contribution preview should exist');
    assert.ok(rewardStart >= 0 && rewardEnd > rewardStart, 'reward preview should exist');

    vm.runInNewContext(clientSource.slice(requiredStart, requiredEnd + 3), context);
    vm.runInNewContext(clientSource.slice(rewardStart, rewardEnd + 3), context);

    assert.match(context.Caesar.Contribute.ToUpgradeBox('recipe'), /حيوية/);
    assert.match(context.Caesar.Contribute.ToUpgradeBox('recipe'), /دفاع[\s\S]*\+2/);
    assert.match(context.Caesar.Contribute.PrizeBox('recipe'), /دفاع[\s\S]*\+5/);
});

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

function getWorldCityRefresh(context) {
    const assignment = 'Caesar.WsLib.Base.refreshWorldCitiesForPlayers = ';
    const start = clientSource.indexOf(assignment);
    const end = clientSource.indexOf('\n};\n\nCaesar.WsLib.Base.refreshCityFlag', start) + 2;
    assert.ok(start >= 0 && end > start, 'world city refresh handler should exist');
    return vm.runInNewContext(
        `(${clientSource.slice(start + assignment.length, end)})`,
        context
    );
}

function captureExpansionHandler(context) {
    let handler;
    const originalDollar = context.$;
    const wrappedDollar = function(value) {
        if (value === context.document) {
            return {
                on(eventName, selector, callback) {
                    if (eventName === 'click' && selector === '#expan-city button') handler = callback;
                    return this;
                }
            };
        }
        return originalDollar(value);
    };
    wrappedDollar.ajax = originalDollar.ajax;
    context.$ = wrappedDollar;

    const startMarker = '$(document).on("click", "#expan-city button", function() {';
    const endMarker = '\n\n$(document).on("click", "#bar_list .abandon"';
    const start = clientSource.indexOf(startMarker);
    const end = clientSource.indexOf(endMarker, start);
    assert.ok(start >= 0 && end > start, 'city expansion handler should exist');
    vm.runInNewContext(clientSource.slice(start, end), context);
    return handler;
}

function getCityFlagRefresh(context) {
    const assignment = 'Caesar.WsLib.Base.refreshCityFlag = ';
    const start = clientSource.indexOf(assignment);
    const end = clientSource.indexOf('\n\nCaesar[\'WsLib\'][\'Base\'][\'WorldFireUnit\']', start);
    assert.ok(start >= 0 && end > start, 'city flag refresh handler should exist');
    return vm.runInNewContext(
        `(${clientSource.slice(start + assignment.length, end).replace(/;\s*$/, '')})`,
        context
    );
}

test('world city updates are retained without trying to render an inactive map', () => {
    const unit = { CityFlag: '0' };
    const context = {
        WUT_CITY_LVL_0: 100,
        WorldUnit: { getWorldUnit: () => unit },
        Caesar: { World: {}, GE: {} }
    };
    const refresh = getWorldCityRefresh(context);

    assert.doesNotThrow(() => refresh({
        xCoord: 3,
        yCoord: 4,
        idCity: 22,
        idPlayer: 7,
        idGuild: 9,
        CityLvl: 2,
        CityFlag: '0',
        CityFlagExpiresAt: 123
    }));
    assert.equal(unit.CityLvl, 2);
    assert.equal(unit.idCity, 22);
    assert.equal(unit.idPlayer, 7);
    assert.equal(unit.CityFlagExpiresAt, 123);
    assert.equal(unit.ut, 102);
});

test('world city updates still redraw the map when its scene is active', () => {
    const calls = [];
    const unit = { CityFlag: '0', entite: { destroy: () => calls.push('destroy') } };
    const context = {
        WUT_CITY_LVL_0: 100,
        WorldUnit: { getWorldUnit: () => unit },
        Caesar: {
            World: {
                Map: {
                    DeletUnit: () => calls.push('delete'),
                    Scroll: value => calls.push(`scroll:${value}`),
                    RefreshWorld: () => calls.push('refresh')
                }
            },
            GE: { WorldScene: { cameras: { main: {} } } }
        }
    };
    const refresh = getWorldCityRefresh(context);

    refresh({
        xCoord: 3,
        yCoord: 4,
        idCity: 22,
        idPlayer: 7,
        idGuild: 9,
        CityLvl: 2,
        CityFlag: '0'
    });

    assert.deepEqual(calls, ['destroy', 'delete', 'scroll:true', 'refresh']);
});

test('city expansion updates the active city view immediately', () => {
    const city = { City: { id_city: 22, lvl: 1 } };
    const unit = { CityFlag: '0' };
    let expandedPlanCost = 0;
    let buildingRefreshes = 0;
    let expansionTabClicks = 0;
    let successMessages = 0;

    const context = {
        document: {},
        API_URL: 'http://localhost:8092',
        WUT_CITY_LVL_0: 100,
        WorldUnit: { getWorldUnit: () => unit },
        Caesar: {
            CurrentCity: city,
            Config: { AuthToken: 'test-token', idServer: 1 },
            City: { getCity: () => city },
            DPlayer: { Player: { id_guild: 9 } },
            WsLib: { Base: {} },
            LBase: {
                isJson: () => true,
                Error: message => { throw new Error(String(message)); }
            }
        },
        Palace: { isExpanable: () => true },
        Matrial: {
            takeFrom(name, amount) {
                assert.equal(name, 'expan_plan');
                expandedPlanCost = amount;
            }
        },
        alert_box: {
            confirmDialog(message, callback) { callback(); },
            succesMessage() { successMessages++; }
        },
        ws: { readyState: 0 },
        window: { requestAnimationFrame(callback) { callback(); } },
        fillCityWithBuilding() { buildingRefreshes++; },
        JSON
    };
    context.Caesar.WsLib.Base.refreshWorldCitiesForPlayers = getWorldCityRefresh(context);
    context.$ = value => {
        if (value === context.document) return {};
        if (typeof value === 'string') {
            return {
                each(callback) {
                    callback.call({});
                    return this;
                }
            };
        }
        return {
            attr(name) {
                return name === 'head_title' ? 'expantion' : undefined;
            },
            trigger(name) {
                if (name === 'click') expansionTabClicks++;
                return this;
            }
        };
    };
    context.$.ajax = options => options.success(JSON.stringify({
        state: 'ok',
        City: {
            id_city: 22,
            id_player: 7,
            x: 3,
            y: 4,
            lvl: 2,
            flag: '0'
        }
    }));

    const handler = captureExpansionHandler(context);
    assert.doesNotThrow(() => handler());
    assert.equal(city.City.lvl, 2);
    assert.equal(expandedPlanCost, 2);
    assert.equal(buildingRefreshes, 1);
    assert.equal(expansionTabClicks, 1);
    assert.equal(successMessages, 1);
});

test('city flag refresh broadcasts the three-letter tag to the map unit', () => {
    const unit = {};
    let worldRefreshes = 0;
    const context = {
        Caesar: { World: { Map: { RefreshWorld: () => { worldRefreshes++; } } }, WsLib: { Base: {} } },
        WorldUnit: { getWorldUnit: () => unit }
    };
    const refresh = getCityFlagRefresh(context);

    refresh({ x: 3, y: 4, f: 'flag_roma', fe: 12345, tag: 'ABC' });

    assert.equal(unit.CityFlag, 'flag_roma');
    assert.equal(unit.CityFlagExpiresAt, 12345);
    assert.equal(unit.CityFlagTag, 'ABC');
    assert.equal(worldRefreshes, 1);
});

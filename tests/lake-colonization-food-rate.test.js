const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientJsPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js');
const cityPhpPath = path.join(__dirname, '..', 'caesar-web', 'api', 'city.php');

test('caesar-web/api/city.php queries all production and consumption columns to prevent NaN', () => {
    const cityPhp = fs.readFileSync(cityPhpPath, 'utf8');

    // Verify city.php selects food_in, food_out, and other resource rates
    assert.ok(cityPhp.includes('`food_in`'), 'city.php must select food_in');
    assert.ok(cityPhp.includes('`food_out`'), 'city.php must select food_out');
    assert.ok(cityPhp.includes('`wood_in`'), 'city.php must select wood_in');
    assert.ok(cityPhp.includes('`stone_in`'), 'city.php must select stone_in');
    assert.ok(cityPhp.includes('`metal_in`'), 'city.php must select metal_in');
    assert.ok(cityPhp.includes('`coin_in`'), 'city.php must select coin_in');
    assert.ok(cityPhp.includes('`coin_out`'), 'city.php must select coin_out');
});

test('caesar-client.js afterBattelFinish safely updates food_out and production rates without setting undefined', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');

    // Ensure afterBattelFinish safely parses rates
    assert.ok(
        clientJs.includes('["food_in", "wood_in", "stone_in", "metal_in", "coin_in", "food_out", "coin_out"]'),
        'afterBattelFinish must safely iterate and assign all production and outflow rates'
    );
});

test('refreshWorldCityColonization triggers getCityBase and getCityBarray even when Unit is not cached on map', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');

    const start = clientJs.indexOf('Caesar.WsLib.Base.refreshWorldCityColonization = function(data) {');
    const end = clientJs.indexOf('Caesar.WsLib.Base.refreshWorldCitiesForPlayers =', start);
    assert.ok(start >= 0 && end > start, 'refreshWorldCityColonization must exist');

    const snippet = clientJs.slice(start, end);

    // Verify it doesn't early-return when Unit is null
    assert.ok(
        !snippet.includes('if (!Unit)\n        return;'),
        'refreshWorldCityColonization must not early return before city refresh when Unit is null'
    );

    const calls = [];
    const request = {
        done(cb) { cb(); return this; },
        fail() { return this; }
    };

    const context = {
        calls,
        Caesar: {
            WsLib: { Base: {} },
            DPlayer: { Player: { id_player: 10 } },
            CurrentCity: { City: { id_city: 1 } },
            City: {
                getCityBase(id) { calls.push(['base', id]); return request; },
                getCityBarray(id) { calls.push(['barray', id]); return request; }
            }
        },
        WorldUnit: {
            getWorldUnit: () => null, // Unit NOT cached in viewport!
            refreshUnitView() {}
        },
        WORLD_COLONIZED_UNITS: [],
        city_profile: {
            refresh_resource_view() {},
            refresh_wild_view() {}
        },
        Animation: { cityProductionRate() {} },
        Palace: { productionReport: () => '' },
        SCROLL_BAR_PROP: {},
        $(sel) {
            return {
                attr: () => '',
                html() { return this; },
                niceScroll() { return this; }
            };
        },
        data: {
            xCoord: 100,
            yCoord: 200,
            idCity: 1,
            id_player: 10,
            colonized: true
        }
    };

    vm.runInNewContext(`${snippet}\nCaesar.WsLib.Base.refreshWorldCityColonization(data);`, context);

    // Both base and barray must be called despite Unit being null!
    assert.deepEqual(context.calls, [['base', 1], ['barray', 1]]);
});

test('resource_veiw and resourcesRefresh never produce NaN when city rates are missing', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');

    // Verify defensive Number() || 0 coercion in resource_veiw
    assert.ok(
        clientJs.includes('var food_total = (Number(Caesar.CurrentCity.City.food_in) || 0) - (Number(Caesar.CurrentCity.City.food_out) || 0);'),
        'resource_veiw must guard food_total from NaN'
    );

    // Verify defensive Number() || 0 coercion in resourcesRefresh
    assert.ok(
        clientJs.includes('var food_rate = ((Number(Caesar.CurrentCity.City.food_in) || 0) - (Number(Caesar.CurrentCity.City.food_out) || 0)) / (60 * 60);'),
        'resourcesRefresh must guard food_rate from NaN'
    );
});

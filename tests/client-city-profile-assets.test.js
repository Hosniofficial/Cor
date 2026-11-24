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

function renderWildView(fields, unitTypes) {
    const start = clientSource.indexOf('    wild_veiw: function() {');
    const end = clientSource.indexOf('\n    refresh_resource_view:', start);
    assert.ok(start >= 0 && end > start, 'city wild view should exist');

    const context = {
        Caesar: {
            City: { getCity: () => ({ Barray: fields }) },
            World: { UnitTypeData: unitTypes }
        },
        $: () => ({ attr() { return this; }, html() { return this; } })
    };

    return vm.runInNewContext(`({${clientSource.slice(start, end)}\n}).wild_veiw()`, context);
}

function getBarrayEffects(fields) {
    const start = clientSource.indexOf('Palace.getBarrayEffect = function() {');
    const end = clientSource.indexOf('\nPalace.getTotalProduction = function() {', start);
    assert.ok(start >= 0 && end > start, 'barray production effect should exist');

    const context = {
        Caesar: { CurrentCity: { Barray: fields } },
        Palace: {},
        WorldUnit: {
            getWorldUnit: () => ({ ut: 1, l: undefined }),
            isRiver: type => type >= 1 && type <= 10,
            isWood: type => type >= 50 && type <= 59,
            isDesert: type => type >= 40 && type <= 49,
            isMountain: type => type >= 30 && type <= 39
        }
    };

    return vm.runInNewContext(`${clientSource.slice(start, end)}\nPalace.getBarrayEffect()`, context);
}

function refreshWorldCityColonization(data, playerId, cityId) {
    const start = clientSource.indexOf('Caesar.WsLib.Base.refreshWorldCityColonization = function(data) {');
    const end = clientSource.indexOf('\n\n\nCaesar.WsLib.Base.refreshWorldCitiesForPlayers =', start);
    assert.ok(start >= 0 && end > start, 'world colonization refresh handler should exist');

    const calls = [];
    const request = {
        done(callback) {
            callback();
            return this;
        },
        fail() {
            return this;
        }
    };
    const context = {
        calls,
        renderedReport: null,
        rateRefreshes: 0,
        Caesar: {
            WsLib: { Base: {} },
            DPlayer: { Player: { id_player: playerId } },
            GE: { scene: { isActive: scene => scene === 'City' } },
            CurrentCity: { City: { id_city: cityId } },
            City: {
                getCityBase(id) {
                    calls.push(['base', id]);
                    return request;
                },
                getCityBarray(id) {
                    calls.push(['barray', id]);
                    return request;
                }
            }
        },
        WorldUnit: {
            getWorldUnit: () => ({}),
            refreshUnitView() {}
        },
        WORLD_COLONIZED_UNITS: [],
        Animation: { cityProductionRate() { context.rateRefreshes++; } },
        Palace: { productionReport: () => 'updated production report' },
        SCROLL_BAR_PROP: {},
        $(selector) {
            return {
                attr: () => selector.includes('.selected') ? 'production' : undefined,
                html(value) {
                    if (selector === '.box_content') context.renderedReport = value;
                    return this;
                },
                niceScroll() {
                    return this;
                }
            };
        },
        data
    };

    vm.runInNewContext(
        `${clientSource.slice(start, end)}\nCaesar.WsLib.Base.refreshWorldCityColonization(data);`,
        context
    );
    return context;
}

function handleGuildDominanceAcquired(data, playerGuildId) {
    const start = clientSource.indexOf('Caesar.WsLib.Guild.DominanceAcquired = function(data) {');
    const end = clientSource.indexOf('\n\n\nCaesar.WsLib.Team.announceRelation =', start);
    assert.ok(start >= 0 && end > start, 'guild dominance WebSocket handler should exist');

    const messages = [];
    const context = {
        Caesar: {
            WsLib: { Guild: {} },
            DPlayer: { Player: { id_guild: playerGuildId } }
        },
        WorldUnit: { getUnitData: () => ({ Title: 'قلعة' }) },
        alert_box: { systemChatMessage: message => messages.push(message) },
        escapeHtml: value => value,
        getArabicNumbers: value => String(value),
        messages,
        data
    };

    vm.runInNewContext(
        `${clientSource.slice(start, end)}\nCaesar.WsLib.Guild.DominanceAcquired(data);`,
        context
    );
    return context.messages;
}

test('barray production bonuses use server levels instead of map cache values', () => {
    const effects = getBarrayEffects([
        { Type: 1, lvl: 3 },
        { Type: 50, lvl: 2 },
        { Type: 30, lvl: 4 },
        { Type: 40, lvl: 1 },
        { Type: 1, lvl: 'invalid' }
    ]);

    assert.equal(effects.food, 0.09);
    assert.equal(effects.wood, 0.06);
    assert.equal(effects.metal, 0.12);
    assert.equal(effects.stone, 0.03);
});

test('guild dominance notification is shown only to members of the winning guild', () => {
    const event = { id_guild: 4, xCoord: 12, yCoord: 34, unitType: 184, unitLvl: 2 };

    assert.equal(handleGuildDominanceAcquired(event, 4).length, 1);
    assert.match(handleGuildDominanceAcquired(event, 4)[0], /قلعة/);
    assert.equal(handleGuildDominanceAcquired(event, 5).length, 0);
});

test('colonization refresh recalculates the owner city and updates its open production report', () => {
    const context = refreshWorldCityColonization({
        xCoord: 10,
        yCoord: 20,
        idCity: 3,
        id_player: 2,
        colonized: true
    }, 2, 3);

    assert.deepEqual(context.calls, [['base', 3], ['barray', 3]]);
    assert.equal(context.rateRefreshes, 1);
    assert.equal(context.renderedReport, 'updated production report');
});

test('city wild view uses raw terrain thumbnails for configured wildland types', () => {
    const html = renderWildView(
        [{ Type: 50 }, { Type: 30 }, { Type: 40 }, { Type: 1 }],
        {
            1: { tileName: 'river_1', WSnapShoot: 'river_3.png' },
            30: { tileName: 'm_1', WSnapShoot: 'mountain_1.png' },
            40: { tileName: 'd_1', WSnapShoot: 'desert_1.png' },
            50: { tileName: 'w_1', WSnapShoot: 'wood_1.png' }
        }
    );

    for (const image of [
        'field-wildlands-01.jpg',
        'field-wildlands-02.jpg',
        'field-wildlands-03.jpg',
        'field-wildlands-04.jpg'
    ]) {
        assert.match(html, new RegExp(`images/world/wildlands/${image.replace('.', '\\.')}`));
        assert.ok(
            fs.existsSync(path.join(
                __dirname,
                '..',
                'caesar-assets',
                'web',
                'images',
                'world',
                'wildlands',
                image
            )),
            `${image} should be available in the city wildland assets`
        );
    }
});

test('city wild view keeps map snapshots for non-field units and handles missing snapshots', () => {
    const html = renderWildView(
        [{ Type: 71 }, { Type: 60 }],
        {
            60: { tileName: 'city_0' },
            71: { tileName: 'city_4', WSnapShoot: 'camps.png' }
        }
    );

    assert.match(html, /images\/world\/snap-shot\/camps\.png/);
    assert.match(html, /images\/world\/wildlands\/field-wildlands-05\.jpg/);
});

test('world tile images reuse snapshot copies without duplicate world-root files', () => {
    const imageNames = [
        'arena',
        'armed_heavy_band',
        'armed_heavy_squad',
        'armed_heavy_squadron',
        'armed_light_band',
        'armed_light_division',
        'armed_light_squad',
        'front_division',
        'guard_band',
        'guard_division',
        'guard_squad',
        'guard_squadron',
        'seaCity_1',
        'seaCity_2',
        'seaCity_3',
        'seaCity_4'
    ];
    const unitTypes = JSON.parse(fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'json', 'worldUnitData.json'),
        'utf8'
    ));
    const worldImageDirectory = path.join(__dirname, '..', 'caesar-assets', 'web', 'images', 'world');

    for (const name of imageNames) {
        assert.ok(
            Object.values(unitTypes).some(unit => unit.tileName === name),
            `${name} should remain a configured world tile`
        );
        assert.ok(
            clientSource.includes(
                `Caesar.GE.LoadingScene.load.image('${name}', BASE_ASSET_BATH + 'images/world/snap-shot/${name}.png');`
            ),
            `${name} should load its texture from the shared snapshot asset`
        );
        assert.ok(
            fs.existsSync(path.join(worldImageDirectory, 'snap-shot', `${name}.png`)),
            `${name} should exist in the shared snapshot directory`
        );
        assert.equal(
            fs.existsSync(path.join(worldImageDirectory, `${name}.png`)),
            false,
            `${name} should not remain duplicated in the world root`
        );
        assert.equal(
            clientSource.includes(`images/world/${name}.png`),
            false,
            `${name} should not be referenced from the world root`
        );
    }
});

test('unused CityFloor atlases are neither loaded nor shipped', () => {
    assert.doesNotMatch(clientSource, /MainCityBG_|images\/CityFloor\//);
    assert.equal(
        fs.existsSync(path.join(__dirname, '..', 'caesar-assets', 'web', 'images', 'CityFloor')),
        false
    );
});

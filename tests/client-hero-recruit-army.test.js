const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

function extractMethod(methodName, nextMethodName) {
    const start = clientSource.indexOf(`    ${methodName}: function`);
    const marker = `\n    },\n    ${nextMethodName}`;
    const markerStart = clientSource.indexOf(marker, start);
    assert.ok(start >= 0 && markerStart > start, `${methodName} should exist`);
    return clientSource.slice(start, markerStart + '\n    },'.length);
}

test('theater recruitment sends the selected city ID for authorization', () => {
    const start = clientSource.indexOf('    addHeroFromTheatr: function(id_hero_in_theater, lvl) {');
    const endMarker = '\n    },\n    getPowerRequired';
    const end = clientSource.indexOf(endMarker, start);
    assert.ok(start >= 0 && end > start, 'theater recruitment method should exist');

    let request;
    const context = {
        API_URL: 'http://localhost:8092',
        Caesar: {
            CurrentCity: { City: { id_city: 73, coin: 5000 } },
            Config: { AuthToken: 'test-token', idServer: 1 }
        },
        alert_box: { confirmMessage() {} },
        $: function() {}
    };
    context.$.ajax = options => { request = options; };

    const methodSource = clientSource.slice(start, end) + '\n    }';
    const Hero = vm.runInNewContext(`({${methodSource}})`, context);
    Hero.addHeroFromTheatr(4, 2);

    assert.equal(request.url, 'http://localhost:8092/api/ACityHero/addFromTheater');
    assert.equal(request.data.idCity, 73);
    assert.equal(request.data.idHero, 4);
});

test('theater hero potential zero is not coerced to one in the preview', () => {
    const startMarker = '$(document).on("click", ".show-hero-theater", function() {';
    const start = clientSource.indexOf(startMarker);
    const end = clientSource.indexOf('\n});', start) + 4;
    assert.ok(start >= 0 && end > start, 'theater preview handler should exist');

    let clickHandler;
    let preview;
    const heroData = {
        id_hero: 4,
        lvl: 2,
        ultra_p: 0,
        name: 1,
        avatar: 0
    };
    const context = {
        document: {},
        Caesar: { BaseData: { HeroTheaterName: ['Test', 'Hero'] } },
        showHero(hero) { preview = hero; },
        $: function(value) {
            if (value === context.document) {
                return {
                    on(eventName, selector, callback) {
                        assert.equal(eventName, 'click');
                        assert.equal(selector, '.show-hero-theater');
                        clickHandler = callback;
                    }
                };
            }
            return { attr: () => JSON.stringify(heroData) };
        }
    };
    vm.runInNewContext(clientSource.slice(start, end), context);
    clickHandler.call({});

    assert.equal(preview.ultra_p, 0);
});

test('a consul can manage troops and rearrange slots even when the hero is at capacity', () => {
    const hero = {
        Hero: { id_hero: 7, in_city: 1 },
        Army: { f_1_type: 1, f_1_num: 25 }
    };
    const context = {
        Caesar: {
            CurrentCity: { City: { console: 7 } },
            DPlayer: { Heros: [hero] },
            Hero: { getHero: () => hero },
            HeroArmy: {
                StartDialogBox: (drag, drop, max) => { context.dialogMax = max; }
            }
        },
        soldier_cap: { 1: 1 },
        getAvailPlaces: () => 0,
        alert_box: { confirmMessage() {}, failMessage() {} }
    };

    const availabilityStart = clientSource.indexOf('function heroAvailableForTask(id_hero) {');
    const availabilityEnd = clientSource.indexOf('\n}\n/*', availabilityStart) + 2;
    assert.ok(availabilityStart >= 0 && availabilityEnd > availabilityStart);

    const transferStart = clientSource.indexOf(
        'Caesar.HeroArmy.dropArmyToHero = function(DragPlace, DropPlace) {'
    );
    const transferEnd = clientSource.indexOf(
        '\n\n\nCaesar.HeroArmy.dropArmyToCity = function',
        transferStart
    );
    assert.ok(transferStart >= 0 && transferEnd > transferStart);
    vm.runInNewContext(
        `${clientSource.slice(availabilityStart, availabilityEnd)}\n${clientSource.slice(transferStart, transferEnd)}`,
        context
    );

    const drag = {
        attr(name) {
            return {
                'data-drag-place': 'hero',
                'data-id-hero': '7',
                'data-army-place': 'f_1',
                'army-type': 'army-1'
            }[name];
        }
    };
    const drop = { attr: name => name === 'data-id-hero' ? '7' : undefined };
    context.Caesar.HeroArmy.dropArmyToHero(drag, drop);

    assert.equal(context.heroAvailableForTask(7), true);
    assert.equal(context.dialogMax, 25);
});

test('city army totals include only troops assigned to heroes currently in that city', () => {
    const armyTypes = ['army_a', 'army_b', 'army_c', 'army_d', 'army_e', 'army_f'];
    const currentHero = {
        Hero: { id_city: 5, in_city: 1 },
        Army: { f_1_type: 1, f_1_num: 20, b_1_type: 2, b_1_num: 7 }
    };
    const heroes = [
        currentHero,
        { Hero: { id_city: 5, in_city: 0 }, Army: { f_1_type: 1, f_1_num: 40 } },
        { Hero: { id_city: 5, in_city: 2 }, Army: { f_1_type: 1, f_1_num: 50 } },
        { Hero: { id_city: 6, in_city: 1 }, Army: { f_1_type: 1, f_1_num: 60 } }
    ];
    const city = { id_city: 5 };
    for (const type of armyTypes) city[type] = 100;

    const rendered = {};
    let selectedView = 'army';
    let cityProfile;
    const context = {
        Caesar: {
            CurrentCity: { City: city },
            DPlayer: { Heros: heroes },
            Hero: { HeroState: { HERO_IN_CITY: 1 } }
        },
        getArabicNumbers: String,
        $: function(value) {
            if (value === '.page_content' || value === '#city-profile .page_content') {
                return { attr: () => selectedView };
            }
            if (value === '#city-profile .army .army_type') {
                return {
                    each(callback) {
                        for (const type of armyTypes) callback.call({ armyType: type });
                    }
                };
            }
            return {
                data: () => value.armyType,
                children() {
                    return this;
                },
                html(amount) {
                    rendered[value.armyType] = amount;
                    return this;
                }
            };
        }
    };
    const methods = [
        extractMethod('refresh_hero_view', 'refresh_city_resources'),
        extractMethod('refresh_army_view', 'afterBattelFinish')
    ].join('\n');
    cityProfile = vm.runInNewContext(`({${methods}})`, context);
    context.city_profile = cityProfile;

    cityProfile.refresh_hero_view();
    assert.equal(rendered.army_a, '120');
    assert.equal(rendered.army_b, '107');

    currentHero.Hero.in_city = 0;
    cityProfile.refresh_hero_view();
    assert.equal(rendered.army_a, '100');
    assert.equal(rendered.army_b, '100');

    selectedView = 'hero';
    cityProfile.refresh_army_view();
    assert.equal(rendered.army_a, '100');
});

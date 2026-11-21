const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

test('army recruitment preview matches raw per-unit durations for armies and fortifications', () => {
    const start = clientSource.indexOf(
        '$(document).on("keyup change", ".sol-2-build-amount", function() {'
    );
    const endMarker = '\n});';
    const end = clientSource.indexOf(endMarker, start);
    assert.ok(start >= 0 && end > start, 'army amount preview handler should exist');

    function previewDuration(armyType, unitTime, amount) {
        let handler;
        let displayedDuration;
        const document = {};
        const context = {
            document,
            Caesar: { BaseData: { Army: { [armyType]: { time: unitTime } } } },
            changeTimeFormat: duration => String(duration),
            $: function(target) {
                if (target === document) {
                    return { on(eventName, selector, callback) { handler = callback; } };
                }
                if (target === '#dialg_box .middle-content .selected_sol') {
                    return { attr: () => armyType };
                }
                return {
                    val: () => amount,
                    html(value) {
                        if (target.endsWith('.sol-time')) displayedDuration = value;
                        return this;
                    }
                };
            }
        };
        vm.runInNewContext(clientSource.slice(start, end + endMarker.length), context);
        handler.call({});
        return displayedDuration;
    }

    assert.equal(previewDuration('army_a', 50, 10), '500');
    assert.equal(previewDuration('army_a', 50, 1000), '50000');
    assert.equal(previewDuration('wall_a', 60, 1000), '60000');
    assert.equal(previewDuration('wall_b', 180, 1000), '180000');
    assert.equal(previewDuration('wall_c', 600, 1000), '600000');
    assert.equal(previewDuration('army_a', 50, 1), '50');
});

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

test('theater list highlights heroes with potential in blue while leaving potential zero default', () => {
    const start = clientSource.indexOf('Caesar.Building.Theater.RefreshTheaterListView = function(BuildingPlace) {');
    const end = clientSource.indexOf('\n};\n\n$(document).on("click", ".for_building .nav_bar', start);
    assert.ok(start >= 0 && end > start, 'RefreshTheaterListView should exist');

    let replacedHtml = '';
    const context = {
        $: function(selector) {
            return {
                html: () => {},
                replaceWith: (html) => { replacedHtml = html; }
            };
        },
        changeTimeFormat: () => '00:00:00',
        Translate: {
            Title: {
                TH: {
                    Hero: { ar: 'البطل' },
                    Lvl: { ar: 'المستوى' },
                    Potential: { ar: 'الامكانيات' },
                    Sway: { ar: 'العزم' },
                    Bravery: { ar: 'الشجاعة' },
                    Parry: { ar: 'المناورة' },
                    Retainer: { ar: 'الراتب' },
                    SignUpFee: { ar: 'التكلفة' }
                }
            },
            Button: {
                General: { Action: { ar: 'العملية' } },
                MenuList: { View: { ar: 'عرض' } }
            }
        },
        UserLag: { language: 'ar' },
        Caesar: {
            Building: { Theater: {} },
            BaseData: { HeroTheaterName: ['القائد 1', 'القائد 2'] },
            CurrentCity: {
                BuildingType: { 1: 6 },
                BuildingLvl: { 1: 10 },
                HeroTheater: {
                    lastUpdate: 1000,
                    refreshTime: 3600,
                    HeroList: [
                        { id_hero: 1, name: 0, lvl: 5, ultra_p: 6 },
                        { id_hero: 2, name: 1, lvl: 3, ultra_p: 0 }
                    ]
                }
            }
        }
    };
    context.$.now = () => 1000000;

    vm.runInNewContext(clientSource.slice(start, end + 2), context);
    context.Caesar.Building.Theater.RefreshTheaterListView(1);

    // Hero 1 (ultra_p: 6) has POT-HERO on all detail columns and is-pot-hero
    assert.match(replacedHtml, /class="tr is-pot-hero" data-id-hero-theater="1"/);
    assert.match(replacedHtml, /data-id-hero-theater="1"[\s\S]*?class="td_1 POT-HERO"[\s\S]*?class="td_2 POT-HERO">5<\/div>[\s\S]*?class="td_3 POT-HERO">6<\/div>[\s\S]*?class="td_4 POT-HERO">25 <\/div>[\s\S]*?class="td_5 POT-HERO">20<\/div>[\s\S]*?class="td_6 POT-HERO">21 <\/div>[\s\S]*?class="td_7 POT-HERO">50 <\/div>[\s\S]*?class="td_8 POT-HERO">5000 <\/div>/);

    // Hero 2 (ultra_p: 0) does not have POT-HERO on any detail columns
    assert.doesNotMatch(replacedHtml, /class="tr is-pot-hero" data-id-hero-theater="2"/);
    assert.match(replacedHtml, /data-id-hero-theater="2"[\s\S]*?class="td_1 ">[\s\S]*?class="td_2 ">3<\/div>[\s\S]*?class="td_3 ">0<\/div>[\s\S]*?class="td_4 ">23 <\/div>[\s\S]*?class="td_5 ">18<\/div>[\s\S]*?class="td_6 ">19 <\/div>[\s\S]*?class="td_7 ">30 <\/div>[\s\S]*?class="td_8 ">3000 <\/div>/);
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

test('battle completion refresh accepts direct city payload and guards missing city', () => {
    const start = clientSource.indexOf('    afterBattelFinish: function() {');
    const end = clientSource.indexOf('\n    },\n    getAllCitiesData:', start);
    assert.ok(start >= 0 && end > start, 'after battle refresh method should exist');

    let ajaxOptions;
    let refreshed = false;
    const city = { id_city: 5, food: 10 };
    const context = {
        Caesar: {
            CurrentCity: { City: city },
            Battel: { Battels: [] },
            LBase: { Error: () => {} }
        },
        ID_PLAYER: 7,
        TOKEN: 'token',
        Date,
        isJson: () => true,
        $: {
            ajax(options) {
                ajaxOptions = options;
                options.success(JSON.stringify({
                    food: 900,
                    wood: 800,
                    stone: 700,
                    metal: 600,
                    coin: 500,
                    food_out: 12,
                    wall_cap: 25000
                }));
            }
        },
        city_profile: {
            refresh_resource_view() {
                refreshed = true;
            }
        }
    };

    const cityProfile = vm.runInNewContext(
        `({${clientSource.slice(start, end)}\n    }})`,
        context
    );
    cityProfile.afterBattelFinish();

    assert.equal(ajaxOptions.data.id_city, 5);
    assert.equal(city.food, 900);
    assert.equal(city.wood, 800);
    assert.equal(city.wall_cap, 25000);
    assert.equal(refreshed, true);
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

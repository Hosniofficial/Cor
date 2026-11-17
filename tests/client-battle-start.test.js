const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function createBattleStart(cityHeroes, hospitalLevel) {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf('function battelStart() {');
    const end = clientSource.indexOf('\n\n\nfunction getReportTitle', start);
    assert.ok(start >= 0 && end > start, 'battle-start function should exist');

    const failures = [];
    const attackabilityChecks = [];
    let closedAlerts = 0;
    const context = {
        Caesar: {
            CurrentHero: { Hero: { in_city: 1, id_hero: 7, id_city: 42 } },
            CurrentCity: { City: { console: 99 } },
            DPlayer: { Heros: cityHeroes },
            Hero: { HeroState: { HERO_IN_CITY: 1 } },
            World: { UnitData: { isPromLvlOk: () => true } },
            City: { getCity: () => ({ BuildingLvl: { hospital: hospitalLevel } }) },
            BaseData: {
                BattelTasks: {
                    BATTEL_TASK_SUPPORT: 1,
                    BATTEL_TASK_JOIN_ATT: 2,
                    BATTEL_TASK_JOIN_DEF: 3,
                    BATTEL_TASK_HERO_TRANS: 4
                }
            }
        },
        WorldUnit: { getWorldUnit: () => ({ ut: 6, x: 10, y: 15 }) },
        battel_data: { x_coord: 10, y_coord: 15, task: 0 },
        cityHasType: () => 'hospital',
        BUILDING_TYPS: { HOSPITAL: 'hospital' },
        Battel: {
            isAttackable: (heroId, unitType) => {
                attackabilityChecks.push([heroId, unitType]);
                return false;
            }
        },
        isMyBarr: () => false,
        $: () => ({ click: () => { closedAlerts++; } }),
        alert_box: { failMessage: message => failures.push(message) }
    };
    vm.runInNewContext(clientSource.slice(start, end), context);

    return {
        run: () => context.battelStart(),
        failures,
        attackabilityChecks,
        get closedAlerts() { return closedAlerts; }
    };
}

test('battle start enforces the hospital limit and checks the selected target', () => {
    const atHospitalLimit = createBattleStart([
        { Hero: { id_city: 42, in_city: 1 } },
        { Hero: { id_city: 42, in_city: 0 } },
        { Hero: { id_city: 99, in_city: 0 } }
    ], 1);
    atHospitalLimit.run();
    assert.match(atHospitalLimit.failures[0], /وصل عدد الابطال بالخارج الى الحد الاقصى/);
    assert.equal(atHospitalLimit.closedAlerts, 1);
    assert.deepEqual(atHospitalLimit.attackabilityChecks, []);

    const belowHospitalLimit = createBattleStart([
        { Hero: { id_city: 42, in_city: 1 } }
    ], 1);
    belowHospitalLimit.run();
    assert.deepEqual(belowHospitalLimit.attackabilityChecks, [[7, 6]]);
    assert.match(belowHospitalLimit.failures[0], /لا يمكنك الهجوم بالبطل الحالى/);
});

test('the conquest mission bypasses the client fitness threshold', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf('$(document).on("click", "#start_battel"');
    const end = clientSource.indexOf('\nfunction isMyBarr', start);
    assert.ok(start >= 0 && end > start, 'battle-start click handler should exist');

    function run(task) {
        const handlers = [];
        const failures = [];
        let confirmations = 0;
        const document = {};
        const context = {
            document,
            Caesar: {
                CurrentHero: { Hero: { power: 0 } },
                BaseData: { BattelTasks: { BATTEL_TASK_DOMINATE: 1 } }
            },
            battel_data: { x_coord: 10, y_coord: 15, task },
            Hero: { getPowerRequired: () => 10 },
            BATTAL_TASKS: { 0: { ar_title: 'attack' }, 1: { ar_title: 'conquer' } },
            heroAvailableForTask: () => true,
            getArabicNumbers: value => value,
            $: target => target === document
                ? { on: (event, selector, handler) => handlers.push(handler) }
                : { attr: () => {}, removeAttr: () => {} },
            alert_box: {
                confirmMessage: message => failures.push(message),
                confirmDialog: () => { confirmations++; }
            }
        };
        vm.runInNewContext(clientSource.slice(start, end), context);
        handlers[0].call({});
        return { failures, confirmations };
    }

    assert.match(run(0).failures[0], /لا توجد لياقة بدنية كافية/);
    assert.deepEqual(run(1), { failures: [], confirmations: 1 });
});

test('battle join handles invalid responses and updates the hero that initiated the request', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf('function battelStart() {');
    const end = clientSource.indexOf('\n\n\nfunction getReportTitle', start);
    assert.ok(start >= 0 && end > start, 'battle-start function should exist');

    const joiningHero = {
        Hero: { id_hero: 7, id_city: 42, in_city: 1, power: 100, power_ls: 0 }
    };
    const heroes = [joiningHero, { Hero: { id_hero: 8, id_city: 42, in_city: 1 } }];
    const errors = [];
    const heroLookups = [];
    const afterJoinCalls = [];
    const socketMessages = [];
    const playerNotifications = { hero_in_battel: 0, battel_number: 0 };
    let ajaxRequest;
    const $ = () => ({ click: () => {}, trigger: () => {} });
    $.ajax = options => (ajaxRequest = options);

    const context = {
        Caesar: {
            CurrentHero: joiningHero,
            CurrentCity: { City: { console: 99 } },
            DPlayer: { Heros: heroes },
            Hero: {
                HeroState: { HERO_IN_CITY: 1 },
                getHero: id => {
                    heroLookups.push(id);
                    return heroes.find(hero => Number(hero.Hero.id_hero) === Number(id));
                }
            },
            World: { UnitData: { isPromLvlOk: () => true } },
            City: { getCity: () => ({ BuildingLvl: { hospital: 2 } }) },
            BaseData: {
                BattelTasks: {
                    BATTEL_TASK_SUPPORT: 1,
                    BATTEL_TASK_JOIN_ATT: 2,
                    BATTEL_TASK_JOIN_DEF: 3,
                    BATTEL_TASK_HERO_TRANS: 4
                }
            },
            Config: { AuthToken: 'test-token', idServer: 3 },
            Battel: { Battels: [] },
            LBase: {
                Error: response => {
                    errors.push(response);
                    return response;
                }
            }
        },
        WorldUnit: { getWorldUnit: () => ({ ut: 6, x: 10, y: 15 }) },
        battel_data: { x_coord: 10, y_coord: 15, task: 2, id_battel: 55, side: 1 },
        cityHasType: () => 'hospital',
        BUILDING_TYPS: { HOSPITAL: 'hospital' },
        Battel: {
            isAttackable: () => true,
            checkJoinReq: () => true,
            afterJoin: (...coords) => { afterJoinCalls.push(coords); }
        },
        isMyBarr: () => false,
        $,
        WS_HOST: 'localhost',
        WS_PORT: 9000,
        isJson: text => {
            try {
                JSON.parse(text);
                return true;
            } catch {
                return false;
            }
        },
        alert_box: { confirmMessage: () => {}, failMessage: () => {} },
        Hero: { heroAttackProc: () => {} },
        PLAYER_NOTIF: playerNotifications,
        city_profile: { refresh_hero_view: () => {} },
        Fixed: { refreshPlayerNotif: () => {} },
        ws: { readyState: 1, send: message => { socketMessages.push(message); } }
    };
    vm.runInNewContext(clientSource.slice(start, end), context);

    context.battelStart();
    assert.equal(ajaxRequest.data.idHero, 7);

    const rawError = 'Unexpected server output';
    assert.equal(ajaxRequest.success(rawError, 'success', {}), rawError);
    assert.deepEqual(errors, [rawError]);

    ajaxRequest.success(JSON.stringify({
        state: 'ok',
        hero_power: 80,
        hero_power_ls: 1234,
        unit_type: 6,
        unit_lvl: 2,
        Battel: { id_battel: 55, id_player: 9 }
    }), 'success', {});

    assert.deepEqual(heroLookups, [7]);
    assert.equal(joiningHero.Hero.in_city, 0);
    assert.equal(joiningHero.Hero.power, 80);
    assert.equal(joiningHero.Hero.power_ls, 1234);
    assert.equal(context.battel_data.type, 6);
    assert.equal(context.battel_data.lvl, 2);
    assert.equal(context.Caesar.Battel.Battels[0].id_battel, 55);
    assert.equal(playerNotifications.hero_in_battel, 1);
    assert.equal(playerNotifications.battel_number, 1);
    assert.deepEqual(afterJoinCalls, [[10, 15]]);
    assert.equal(socketMessages.length, 1);
});

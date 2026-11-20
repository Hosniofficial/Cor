const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {
    HERO_ACTION_POWER_COST,
    applyPersistedWorldUnitLevels,
    getArmyMinimumSpeed,
    getHeroMaxPower,
    getHeroRequiredPower,
    getMarchDuration,
    getNextResetAt,
    getResetIntervalHours,
    recoverHeroPower,
    getReturnDuration,
    getRomanCommanderName,
    getWrappedDistance,
    isFixedDurationTarget,
    normalizeDynamicWorldUnitSeedLevels,
    ROMAN_COMMANDERS
} = require('../caesar-shared/battle-mechanics');

test('hero fitness recovers once per two-minute interval and caps cleanly', () => {
    assert.equal(HERO_ACTION_POWER_COST, 10);
    assert.deepEqual(recoverHeroPower(40, 100, 1000, 1119), {
        power: 40,
        powerLastSync: 1000
    });
    assert.deepEqual(recoverHeroPower(40, 100, 1000, 1241), {
        power: 42,
        powerLastSync: 1240
    });
    assert.deepEqual(recoverHeroPower(99, 100, 1000, 1500), {
        power: 100,
        powerLastSync: 1500
    });
    assert.deepEqual(recoverHeroPower(40, 100, 0, 1500), {
        power: 40,
        powerLastSync: 1500
    });
});

test('conquest bypasses the minimum fitness requirement but still uses normal thresholds elsewhere', () => {
    assert.equal(getHeroRequiredPower(0, 0), 10);
    assert.equal(getHeroRequiredPower(0, 40), 40);
    assert.equal(getHeroRequiredPower(1, 40), 0);
    assert.throws(() => getHeroRequiredPower(0, -1), TypeError);
});

test('hero maximum fitness follows the original level formula and cap', () => {
    assert.equal(getHeroMaxPower(1), 52);
    assert.equal(getHeroMaxPower(10), 70);
    assert.equal(getHeroMaxPower(100), 250);
    assert.equal(getHeroMaxPower(255), 560);
    assert.throws(() => getHeroMaxPower(Number.NaN), TypeError);
});

test('dynamic world-unit map seeds are normalized without changing fixed-level units', () => {
    const worldUnits = new Map([
        ['2_3', { ut: 70, t: 30, l: 11, baseL: 11 }],
        ['5_6', { ut: 201, t: 151, l: 17, baseL: 17 }],
        ['8_9', { ut: 221, t: 5, l: 9, baseL: 9 }]
    ]);
    const normalized = normalizeDynamicWorldUnitSeedLevels(worldUnits, {
        70: { lvlChange: true },
        201: { lvlChange: true },
        221: { lvlChange: false }
    });

    assert.equal(normalized, 2);
    assert.deepEqual(worldUnits.get('2_3'), { ut: 70, t: 30, l: 1, baseL: 1 });
    assert.deepEqual(worldUnits.get('5_6'), { ut: 201, t: 151, l: 1, baseL: 1 });
    assert.deepEqual(worldUnits.get('8_9'), { ut: 221, t: 5, l: 9, baseL: 9 });
});

test('Roman commanders are stable per target level and change across levels', () => {
    const commander = getRomanCommanderName(70, 1, 20, 30);
    assert.doesNotMatch(commander, /^القيصر /);
    assert.ok(ROMAN_COMMANDERS.includes(commander));
    assert.equal(getRomanCommanderName(70, 1, 20, 30), commander);
    assert.notEqual(getRomanCommanderName(70, 2, 20, 30), commander);
});

test('all packaged maps seed level-changing units at level one', () => {
    const root = path.join(__dirname, '..');
    const unitData = JSON.parse(fs.readFileSync(
        path.join(root, 'caesar-assets', 'api', 'js-3.4.113', 'json', 'worldUnitData.json'),
        'utf8'
    ));
    const mapFiles = [
        path.join(root, 'caesar-assets', 'api', 'js-3.4.113', 'json', 'WorldBarrary.json'),
        path.join(root, 'caesar-assets', 'web', 'js-3.4.113', 'json', 'WorldBarrary.json'),
        path.join(root, 'caesar-assets', 'api', 'js-0.0.24', 'json', 'WorldBarrary.json')
    ];
    let canonicalMap;
    for (const mapFile of mapFiles) {
        const mapData = JSON.parse(fs.readFileSync(mapFile, 'utf8'));
        if (canonicalMap === undefined) canonicalMap = mapData;
        assert.equal(mapData, canonicalMap, `${mapFile} should match the other packaged maps`);
        for (const entry of mapData.split(',')) {
            const [x, y, rawType, rawLevel] = entry.split('_');
            const unitType = Number(rawType);
            if (unitData[unitType] && unitData[unitType].lvlChange === true) {
                assert.equal(Number(rawLevel), 1, `${mapFile}: dynamic unit ${unitType} at ${x}_${y}`);
            }
        }
    }
});

test('persisted map levels are restored while expired levels return to their base', () => {
    const worldUnits = new Map([
        ['2_3', { ut: 70, t: 30, l: 1 }],
        ['5_6', { ut: 81, t: 32, l: 1 }],
        ['8_9', { ut: 82, t: 33, l: 1 }]
    ]);
    const restored = applyPersistedWorldUnitLevels(worldUnits, [
        {
            x_coord: 2, y_coord: 3, unit_type: 70, unit_subtype: 30,
            base_lvl: 1, current_lvl: 5, max_lvl: 50, next_reset_at: 500
        },
        {
            x_coord: 5, y_coord: 6, unit_type: 81, unit_subtype: 32,
            base_lvl: 2, current_lvl: 7, max_lvl: 40, next_reset_at: 50
        },
        {
            x_coord: 8, y_coord: 9, unit_type: 99, unit_subtype: 33,
            base_lvl: 1, current_lvl: 5, max_lvl: 30, next_reset_at: 500
        }
    ], 100);

    assert.equal(restored, 2);
    assert.equal(worldUnits.get('2_3').l, 5);
    assert.equal(worldUnits.get('5_6').l, 2);
    assert.equal(worldUnits.get('8_9').l, 1);
});

test('wrapped distance uses the shorter route across the map edge', () => {
    assert.equal(getWrappedDistance(2, 10, 498, 10), 24000);
});

test('march duration uses the slowest deployed unit and special target rules', () => {
    const army = {
        f_1_type: 1, f_1_num: 10,
        b_1_type: 6, b_1_num: 1
    };
    assert.equal(getArmyMinimumSpeed(army), 100);
    assert.equal(getMarchDuration({ x1: 0, y1: 0, x2: 10, y2: 0, army, unitType: 20 }), 600);
    assert.equal(getMarchDuration({ x1: 0, y1: 0, x2: 1, y2: 0, army, unitType: 60, isCity: true }), 900);
    assert.equal(getMarchDuration({ x1: 0, y1: 0, x2: 10, y2: 0, army, unitType: 70 }), 120);
    assert.equal(isFixedDurationTarget(70), true);
    assert.equal(isFixedDurationTarget(71), false);
    assert.equal(getMarchDuration({ x1: 0, y1: 0, x2: 1, y2: 0, army, unitType: 71 }), 120);
    assert.equal(getMarchDuration({ x1: 0, y1: 0, x2: 10, y2: 0, army, unitType: 80 }), 600);
    assert.equal(getMarchDuration({ x1: 0, y1: 0, x2: 10, y2: 0, army, unitType: 177 }), 300);
    assert.equal(getMarchDuration({ x1: 0, y1: 0, x2: 10, y2: 0, army, unitType: 180 }), 900);
    assert.equal(getMarchDuration({ x1: 0, y1: 0, x2: 10, y2: 0, army, unitType: 184 }), 3600);
    assert.equal(getMarchDuration({ x1: 0, y1: 0, x2: 10, y2: 0, army, unitType: 231 }), 900);
    assert.equal(getMarchDuration({ x1: 0, y1: 0, x2: 10, y2: 0, army, unitType: 200 }), 120);
    for (let unitType = 200; unitType <= 205; unitType++) {
        assert.equal(getMarchDuration({ x1: 0, y1: 0, x2: 10, y2: 0, army, unitType }), 120);
        assert.equal(isFixedDurationTarget(unitType), true);
    }
});

test('return duration follows map distance and army speed without a city minimum', () => {
    assert.equal(getReturnDuration({
        x1: 1, y1: 1, x2: 2, y2: 1,
        army: { f_1_type: 6, f_1_num: 1 }
    }), 60);
});

test('return duration applies the configured maximum only to fixed-duration targets', () => {
    const army = { f_1_type: 6, f_1_num: 1 };
    assert.equal(getReturnDuration({
        x1: 0, y1: 0, x2: 1, y2: 0, army, unitType: 101, maxDuration: 120
    }), 60);
    assert.equal(getReturnDuration({
        x1: 0, y1: 0, x2: 3, y2: 0, army, unitType: 101, maxDuration: 120
    }), 120);
    assert.equal(getReturnDuration({
        x1: 0, y1: 0, x2: 3, y2: 0, army, unitType: 177, maxDuration: 120
    }), 180);
    assert.equal(getReturnDuration({
        x1: 0, y1: 0, x2: 3, y2: 0, army, unitType: 200, maxDuration: 120
    }), 120);
    assert.equal(getReturnDuration({
        x1: 0, y1: 0, x2: 3, y2: 0, army, unitType: 180, maxDuration: 120
    }), 180);
    assert.equal(getReturnDuration({
        x1: 0, y1: 0, x2: 3, y2: 0, army, unitType: 184, maxDuration: 120
    }), 180);
});

test('reset schedules advance through UTC slots for six-hour and twelve-hour units', () => {
    const beforeTwoUtc = Date.UTC(2026, 0, 1, 1, 30, 0) / 1000;
    const afterTwoUtc = Date.UTC(2026, 0, 1, 2, 0, 3) / 1000;
    assert.equal(new Date(getNextResetAt(55, beforeTwoUtc) * 1000).toISOString(), '2026-01-01T02:00:02.000Z');
    assert.equal(new Date(getNextResetAt(55, afterTwoUtc) * 1000).toISOString(), '2026-01-01T08:00:02.000Z');

    const beforeEightUtc = Date.UTC(2026, 0, 1, 7, 59, 59) / 1000;
    const afterEightUtc = Date.UTC(2026, 0, 1, 8, 0, 1) / 1000;
    assert.equal(new Date(getNextResetAt(30, beforeEightUtc) * 1000).toISOString(), '2026-01-01T08:00:00.000Z');
    assert.equal(new Date(getNextResetAt(30, afterEightUtc) * 1000).toISOString(), '2026-01-01T20:00:00.000Z');
    assert.equal(getNextResetAt(999, beforeEightUtc), 0);
});

test('reset schedules honor configured category intervals while preserving their UTC anchors', () => {
    const beforeEightUtc = Date.UTC(2026, 0, 1, 7, 59, 59) / 1000;
    assert.equal(getResetIntervalHours(30, { camp_reset_interval_hours: 8 }), 8);
    assert.equal(
        new Date(getNextResetAt(30, beforeEightUtc, { camp_reset_interval_hours: 8 }) * 1000).toISOString(),
        '2026-01-01T08:00:00.000Z'
    );
    assert.equal(getResetIntervalHours(55, { gang_reset_interval_hours: 3 }), 3);
    assert.equal(getResetIntervalHours(32, { squad_reset_interval_hours: 5 }), 5);
    assert.equal(getResetIntervalHours(30, { camp_reset_interval_hours: 0 }), 12);
});

test('camp reset countdown matches the server 08:00 and 20:00 UTC schedule', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    const timeRestSource = clientSource.match(/var TimeRest = \{[\s\S]*?\n\};/);
    assert.ok(timeRestSource, 'client TimeRest definition should exist');

    for (const isoTime of [
        '2026-01-01T07:59:59.000Z',
        '2026-01-01T08:00:00.000Z',
        '2026-01-01T08:00:01.000Z',
        '2026-01-01T19:59:59.000Z',
        '2026-01-01T20:00:00.000Z',
        '2026-01-01T20:00:01.000Z'
    ]) {
        const nowMilliseconds = Date.parse(isoTime);
        class FixedDate extends Date {
            constructor(...args) {
                super(...(args.length ? args : [nowMilliseconds]));
            }
        }
        const clientResetMilliseconds = vm.runInNewContext(
            `${timeRestSource[0]}\nTimeRest.restEvery12().getTime()`,
            { Date: FixedDate }
        );
        const expectedResetSeconds = getNextResetAt(30, Math.floor(nowMilliseconds / 1000));
        assert.equal(clientResetMilliseconds, expectedResetSeconds * 1000, isoTime);
    }
});

test('client reset countdown follows a live camp interval override', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    const timeRestSource = clientSource.match(/var LIVE_RESET_INTERVALS = \{\};[\s\S]*?\n\};/);
    assert.ok(timeRestSource, 'client reset schedule should support live configuration');

    const nowMilliseconds = Date.parse('2026-01-01T15:00:00.000Z');
    class FixedDate extends Date {
        constructor(...args) {
            super(...(args.length ? args : [nowMilliseconds]));
        }
    }
    const result = vm.runInNewContext(
        `${timeRestSource[0]}\nLIVE_RESET_INTERVALS.camp = 8; TimeRest.restEvery12().toISOString()`,
        { Date: FixedDate }
    );
    assert.equal(result, '2026-01-01T16:00:00.000Z');
});

test('battle report shows wilderness levels and omits changing target levels', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    const start = clientSource.indexOf('BattelField.battelModel = function(battel, list)');
    const end = clientSource.indexOf('\n};', start);
    assert.ok(start >= 0 && end > start, 'battle report model should exist');

    let mapCoordinates;
    let tooltipCoordinates;
    let renderedHtml = '';
    let targetUnitType = 70;
    const context = {
        BattelField: {},
        WorldUnit: {
            getWorldUnit(x, y) {
                mapCoordinates = [x, y];
                return { ut: targetUnitType, l: 17 };
            }
        },
        Caesar: {
            World: {
                UnitTypeData: {
                    70: { Title: 'مناورات', lvlChange: true },
                    97: { Title: 'عصابات', lvlChange: true },
                    201: { Title: 'ميدان', lvlChange: true },
                    30: { Title: 'هضبة', lvlChange: false }
                }
            }
        },
        WorldUtil: {
            tooltipHeader(x, y) {
                tooltipCoordinates = [x, y];
                return `unit at ${x},${y} level 17`;
            }
        },
        Translate: {
            Title: { TH: { Subject: { ar: 'Subject' } } },
            Button: {
                General: { JoinDefence: { ar: 'Join defense' }, JoinAttack: { ar: 'Join attack' } },
                MenuList: { Intelligence: { ar: 'Intelligence' }, RefreshHero: { ar: 'Refresh' } }
            }
        },
        UserLag: { language: 'ar' },
        changeTimeFormat: () => '',
        SCROLL_BAR_PROP: {},
        refreshBattleReportListScroll: () => {},
        $: () => ({
            length: 0,
            replaceWith(html) { renderedHtml = html; },
            addClass() {},
            niceScroll() {}
        })
    };
    const model = clientSource.slice(start, end + 3);
    vm.runInNewContext(model, context);
    context.BattelField.battelModel({
        x_coord: 37,
        y_coord: 92,
        time_start: 1,
        time_end: 2,
        CityName: 'Test city',
        id_battel: 10
    }, '');

    assert.deepEqual(mapCoordinates, [37, 92]);
    assert.match(renderedHtml, /مناورات/);
    assert.doesNotMatch(renderedHtml, /مستوى|level 17/);
    assert.equal(tooltipCoordinates, undefined);

    for (const [type, title] of [[97, 'عصابات'], [201, 'ميدان']]) {
        targetUnitType = type;
        context.BattelField.battelModel({
            x_coord: 37,
            y_coord: 92,
            time_start: 1,
            time_end: 2,
            CityName: 'Test city',
            id_battel: 10
        }, '');
        assert.match(renderedHtml, new RegExp(title));
        assert.doesNotMatch(renderedHtml, /مستوى|level 17/);
        assert.equal(tooltipCoordinates, undefined);
    }

    targetUnitType = 30;
    context.BattelField.battelModel({
        x_coord: 37,
        y_coord: 92,
        time_start: 1,
        time_end: 2,
        CityName: 'Test city',
        id_battel: 10
    }, '');
    assert.match(renderedHtml, /هضبة مستوى 17/);
    assert.deepEqual(mapCoordinates, [37, 92]);
    assert.equal(tooltipCoordinates, undefined);
});

test('reset schedules use the original map subtype identifiers', () => {
    assert.equal(getResetIntervalHours(30), 12);
    assert.equal(getResetIntervalHours(32), 4);
    assert.equal(getResetIntervalHours(48), 4);
    assert.equal(getResetIntervalHours(55), 6);
    assert.equal(getResetIntervalHours(56), 12);
    assert.equal(getResetIntervalHours(100), 12);
    assert.equal(getResetIntervalHours(153), 4);
    assert.equal(getResetIntervalHours(230), 0);
    for (let subtype = 231; subtype <= 236; subtype++) {
        assert.equal(getResetIntervalHours(subtype), 12);
    }

    const beforeEightUtc = Date.UTC(2026, 0, 1, 7, 59, 59) / 1000;
    const beforeTwentyUtc = Date.UTC(2026, 0, 1, 19, 59, 59) / 1000;
    assert.equal(new Date(getNextResetAt(231, beforeEightUtc) * 1000).toISOString(), '2026-01-01T08:00:00.000Z');
    assert.equal(new Date(getNextResetAt(236, beforeTwentyUtc) * 1000).toISOString(), '2026-01-01T20:00:00.000Z');
});

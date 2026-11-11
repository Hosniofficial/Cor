const test = require('node:test');
const assert = require('node:assert/strict');
const {
    getArmyMinimumSpeed,
    getMarchDuration,
    getNextResetAt,
    getReturnDuration,
    getWrappedDistance
} = require('../battle-mechanics');

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
    assert.equal(getMarchDuration({ x1: 0, y1: 0, x2: 10, y2: 0, army, unitType: 177 }), 300);
});

test('return duration follows map distance and army speed without a city minimum', () => {
    assert.equal(getReturnDuration({
        x1: 1, y1: 1, x2: 2, y2: 1,
        army: { f_1_type: 6, f_1_num: 1 }
    }), 60);
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

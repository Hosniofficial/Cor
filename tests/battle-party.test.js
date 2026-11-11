const test = require('node:test');
const assert = require('node:assert/strict');
const {
    allocateProportionally,
    calculateArmyCasualties,
    calculateArmyPower,
    didBattleSideWin
} = require('../battle-party');

test('army power includes hero stats and equipment with existing combat weights', () => {
    const power = calculateArmyPower(
        { f_1_type: 1, f_1_num: 10 },
        { power: 50, point_b: 10, point_b_plus: 0 },
        { 1: { att: 100, hp: 50 } },
        [{ attack: 10, vitality: 5, damage: 2, break: 4, strike: 1 }]
    );
    assert.equal(power, 1310);
});

test('proportional allocations preserve the full pool and favor stronger participants', () => {
    const members = [
        { id_member: 1, combatPower: 1 },
        { id_member: 2, combatPower: 2 },
        { id_member: 3, combatPower: 0 }
    ];
    const shares = allocateProportionally(10, members);
    assert.deepEqual(shares.map(share => share.amount), [3, 7, 0]);
    assert.equal(shares.reduce((sum, share) => sum + share.amount, 0), 10);
});

test('battle results are translated relative to each participant side', () => {
    assert.equal(didBattleSideWin(true, 1), true);
    assert.equal(didBattleSideWin(true, 0), false);
    assert.equal(didBattleSideWin(false, 1), false);
    assert.equal(didBattleSideWin(false, 0), true);
    assert.throws(() => didBattleSideWin(true, 2), RangeError);
});

test('casualty results preserve remaining troops and report wounded by type', () => {
    const result = calculateArmyCasualties(
        { f_1_type: 1, f_1_num: 10, b_1_type: 2, b_1_num: 5 },
        0.4,
        0.5,
        0.5,
        true
    );
    assert.deepEqual(result.remainingArmy, {
        f_1: { type: 1, amount: 7 },
        f_2: { type: 0, amount: 0 },
        f_3: { type: 0, amount: 0 },
        b_1: { type: 2, amount: 4 },
        b_2: { type: 0, amount: 0 },
        b_3: { type: 0, amount: 0 }
    });
    assert.equal(result.totals.fallen, 6);
    assert.equal(result.totals.permanentLoss, 2);
    assert.equal(result.totals.wounded, 1);
    assert.deepEqual(result.wounded, { 1: 1 });
});

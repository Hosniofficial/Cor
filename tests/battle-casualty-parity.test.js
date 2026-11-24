const test = require('node:test');
const assert = require('node:assert/strict');
const { calculateArmyCasualties } = require('../caesar-shared/battle-party');

test('authentic casualty split: camp battle preserves 75% recovery and sends 60% of remainder to plaza with medical statue', () => {
    // 1000 infantry in camp battle (lossRate 1.0, recovery 0.75, statue 0.60)
    const result = calculateArmyCasualties(
        { f_1_type: 1, f_1_num: 1000 },
        1.0,
        0.75,
        0.60,
        true
    );

    assert.equal(result.totals.fallen, 1000);
    assert.equal(result.totals.recovered, 750, '75% recover on the battlefield and return with hero');
    assert.equal(result.totals.wounded, 150, '60% of unrecovered 250 go to Plaza as wounded');
    assert.equal(result.totals.permanentLoss, 100, 'Remaining 100 are permanently lost');
    assert.equal(result.remainingArmy.f_1.amount, 750, 'Hero returns with 750 living troops');
    assert.equal(result.wounded[1], 150, 'Plaza receives 150 type-1 wounded');
});

test('authentic casualty split: wilds battle has 0% field recovery, full loss subject to wounded', () => {
    // 1000 infantry in wilds battle (lossRate 1.0, recovery 0.00, statue 0.60)
    const result = calculateArmyCasualties(
        { f_1_type: 1, f_1_num: 1000 },
        1.0,
        0.00,
        0.60,
        true
    );

    assert.equal(result.totals.fallen, 1000);
    assert.equal(result.totals.recovered, 0, '0% field recovery in wilds (authentic real battle)');
    assert.equal(result.totals.wounded, 600, '60% of unrecovered 1000 go to Plaza as wounded');
    assert.equal(result.totals.permanentLoss, 400, '40% are permanently lost');
    assert.equal(result.remainingArmy.f_1.amount, 0, 'Hero returns with 0 troops if wiped out');
    assert.equal(result.wounded[1], 600, 'Plaza receives 600 type-1 wounded');
});

test('authentic casualty split: medicine study level increases wounded rate proportionally', () => {
    // Test base rate (lvl 0 -> 10%)
    const lvl0Rate = Math.min(0.60, 0.10 + 0 * 0.05);
    assert.equal(lvl0Rate, 0.10, 'Level 0 medicine gives base 10%');

    // Test intermediate rate (lvl 4 -> 30%)
    const lvl4Rate = Math.min(0.60, 0.10 + 4 * 0.05);
    assert.ok(Math.abs(lvl4Rate - 0.30) < 1e-9, 'Level 4 medicine gives 30%');

    // Test intermediate rate (lvl 7 -> 45%)
    const lvl7Rate = Math.min(0.60, 0.10 + 7 * 0.05);
    assert.ok(Math.abs(lvl7Rate - 0.45) < 1e-9, 'Level 7 medicine gives 45%');

    // Test max rate (lvl 10 -> 60%)
    const lvl10Rate = Math.min(0.60, 0.10 + 10 * 0.05);
    assert.ok(Math.abs(lvl10Rate - 0.60) < 1e-9, 'Level 10 medicine reaches 60% cap');

    // Test casualty counts scale with medicine level:
    const woundedLvl0 = calculateArmyCasualties({ f_1_type: 1, f_1_num: 1000 }, 1.0, 0, lvl0Rate, true).totals.wounded;
    const woundedLvl4 = calculateArmyCasualties({ f_1_type: 1, f_1_num: 1000 }, 1.0, 0, lvl4Rate, true).totals.wounded;
    const woundedLvl10 = calculateArmyCasualties({ f_1_type: 1, f_1_num: 1000 }, 1.0, 0, lvl10Rate, true).totals.wounded;

    assert.equal(woundedLvl0, 100, 'Level 0 medicine yields 100 wounded per 1000 unrecovered');
    assert.equal(woundedLvl4, 300, 'Level 4 medicine yields 300 wounded per 1000 unrecovered');
    assert.equal(woundedLvl10, 600, 'Level 10 medicine yields 600 wounded per 1000 unrecovered');
});

test('defeat outcome sends wounded to Plaza using authentic medicine / medicalBuff rate', () => {
    // Defeat (isVictory = false), 1000 infantry fallen in city/wild (0 recovery)
    // Even on defeat, medicine study protects troops and transfers wounded to Plaza
    const defeatResult = calculateArmyCasualties(
        { f_1_type: 1, f_1_num: 1000 },
        1.0,
        0.00,
        0.60, // e.g. with medical buff or max study
        false // Defeat
    );

    assert.equal(defeatResult.totals.fallen, 1000);
    assert.equal(defeatResult.totals.recovered, 0);
    assert.equal(defeatResult.totals.wounded, 600, 'Defeat must also transfer 60% wounded to Plaza');
    assert.equal(defeatResult.totals.permanentLoss, 400);
    assert.equal(defeatResult.wounded[1], 600);
});

test('defeat outcome in Statue of War or Camp preserves field recovery on hero army and sends wounded to Plaza', () => {
    // Defeat in Statue of War (recoveryRate = 0.60), 10,000 archers completely fallen
    const statueDefeat = calculateArmyCasualties(
        { f_1_type: 4, f_1_num: 10000 },
        1.0,
        0.60, // 60% recovery for Statue of War
        0.60, // 60% wounded rate from medicine / buff
        false // Defeat
    );

    assert.equal(statueDefeat.totals.fallen, 10000);
    assert.equal(statueDefeat.totals.recovered, 6000, '60% recover and return alive with the hero');
    assert.equal(statueDefeat.remainingArmy.f_1.amount, 6000, 'Hero returns with 6000 living troops in formation');
    assert.equal(statueDefeat.totals.wounded, 2400, '60% of unrecovered 4000 go to Plaza as wounded (2400)');
    assert.equal(statueDefeat.totals.permanentLoss, 1600, 'Remaining 1600 are permanently lost');
    assert.equal(statueDefeat.wounded[4], 2400, 'Plaza receives 2400 type-4 wounded');

    // Defeat in Camp (recoveryRate = 0.75), 10,000 archers completely fallen
    const campDefeat = calculateArmyCasualties(
        { f_1_type: 4, f_1_num: 10000 },
        1.0,
        0.75, // 75% recovery for Camp
        0.60, // 60% wounded rate
        false // Defeat
    );

    assert.equal(campDefeat.totals.fallen, 10000);
    assert.equal(campDefeat.totals.recovered, 7500, '75% recover and return alive with the hero');
    assert.equal(campDefeat.remainingArmy.f_1.amount, 7500, 'Hero returns with 7500 living troops in formation');
    assert.equal(campDefeat.totals.wounded, 1500, '60% of unrecovered 2500 go to Plaza as wounded (1500)');
    assert.equal(campDefeat.totals.permanentLoss, 1000, 'Remaining 1000 are permanently lost');
});

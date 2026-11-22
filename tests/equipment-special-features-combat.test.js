const assert = require('node:assert/strict');
const test = require('node:test');
const {
    calculateArmyPower,
    simulateTacticalCombat
} = require('../caesar-shared/battle-party');

const SOL_STATS = [
    { att: 0,   hp: 0 },
    { att: 8,   hp: 60 },   // 1: مشاة (Infantry)
    { att: 30,  hp: 250 },  // 2: فرسان (Cavalry)
    { att: 25,  hp: 400 },  // 3: مدرعين (Armored / Triarii)
    { att: 9,   hp: 45 },   // 4: رماة (Archers)
    { att: 19,  hp: 100 },  // 5: مقاليع (Ballistas)
    { att: 40,  hp: 600 },  // 6: منجنيق (Catapults)
    { att: 10,  hp: 80 }    // 7: جواسيس (Spies)
];

test('Equipment feature "وابل السهام" (sp_attr = 1 or 2) grants archers unified arrow barrage attack boost', () => {
    // Formation: Front line infantry/armored (f_1, f_2) and back line Archers (b_1, b_2)
    const createAttacker = (equipmentBonuses = []) => ({
        id_member: 1,
        hero: { lvl: 1, power: 50 },
        army: {
            f_1_type: 1, f_1_num: 500,  // Infantry in front
            b_1_type: 4, b_1_num: 1000, // Archers in back
            b_2_type: 4, b_2_num: 1000  // Archers in back
        },
        equipmentBonuses
    });

    const createDefender = () => ({
        id_member: 2,
        hero: { lvl: 1, power: 50 },
        army: {
            f_1_type: 1, f_1_num: 15000 // Large enemy infantry wall
        },
        equipmentBonuses: []
    });

    // 1. Without special attribute (baseline)
    const attackerNoSp = createAttacker([{ attack: 0, sp_attr: 0 }]);
    simulateTacticalCombat([attackerNoSp], {}, {
        solStats: SOL_STATS,
        maxRounds: 1,
        npcDefenderTroops: {},
        defendingMembers: [createDefender()]
    });
    const baselineKills = attackerNoSp.killsByType[1];

    // 2. With unified "وابل السهام" (sp_attr = 1)
    const attackerSp1 = createAttacker([{ attack: 0, sp_attr: 1 }]);
    const resultSp1 = simulateTacticalCombat([attackerSp1], {}, {
        solStats: SOL_STATS,
        maxRounds: 1,
        npcDefenderTroops: {},
        defendingMembers: [createDefender()]
    });
    const killsSp1 = attackerSp1.killsByType[1];

    // 3. With unified "وابل السهام" (sp_attr = 2)
    const attackerSp2 = createAttacker([{ attack: 0, sp_attr: 2 }]);
    const resultSp2 = simulateTacticalCombat([attackerSp2], {}, {
        solStats: SOL_STATS,
        maxRounds: 1,
        npcDefenderTroops: {},
        defendingMembers: [createDefender()]
    });
    const killsSp2 = attackerSp2.killsByType[1];

    assert.ok(killsSp1 > baselineKills, `sp_attr = 1 kills (${killsSp1}) must exceed baseline (${baselineKills})`);
    assert.equal(killsSp1, killsSp2, 'Both sp_attr = 1 and 2 must provide identical unified Arrow Barrage boost');
    assert.ok(
        resultSp1.roundsLog.some(line => line.includes('وابل السهام') && !line.includes('أمامي') && !line.includes('خلفي')),
        'Battle log must record unified name "وابل السهام" without front or back words'
    );
});

test('Arrow barrage does not boost ballistas or catapults', () => {
    const siegeKills = spAttr => {
        const attacker = {
            id_member: 1,
            hero: { lvl: 1, power: 50 },
            army: {
                b_1_type: 5, b_1_num: 1000,
                b_2_type: 6, b_2_num: 1000
            },
            equipmentBonuses: [{ attack: 0, sp_attr: spAttr }]
        };
        const defender = {
            id_member: 2,
            hero: { lvl: 1, power: 50 },
            army: { f_1_type: 1, f_1_num: 15000 },
            equipmentBonuses: []
        };

        simulateTacticalCombat([attacker], {}, {
            solStats: SOL_STATS,
            maxRounds: 1,
            npcDefenderTroops: {},
            defendingMembers: [defender]
        });
        return attacker.killsByType[1];
    };

    const baselineKills = siegeKills(0);
    assert.ok(baselineKills > 0, 'Siege troops should inflict casualties in the baseline battle');
    assert.equal(siegeKills(1), baselineKills, 'Barrage must not increase siege-unit damage');
});

test('Equipment feature "الدرع" (sp_attr = 3) reduces incoming damage and fortifies armored & infantry units', () => {
    const createAttacker = () => ({
        id_member: 1,
        hero: { lvl: 1, power: 50 },
        army: {
            f_1_type: 2, f_1_num: 2000 // 2000 Cavalry attacking
        },
        equipmentBonuses: []
    });

    const createDefender = (equipmentBonuses = []) => ({
        id_member: 2,
        hero: { lvl: 1, power: 50 },
        army: {
            f_1_type: 3, f_1_num: 1000, // Armored (Triarii)
            f_2_type: 1, f_2_num: 2000  // Infantry
        },
        equipmentBonuses
    });

    // 1. Without Shield (baseline losses)
    const attackerNoShield = createAttacker();
    const defenderNoShield = createDefender([{ defence: 0, sp_attr: 0 }]);
    simulateTacticalCombat([attackerNoShield], {}, {
        solStats: SOL_STATS,
        maxRounds: 1,
        npcDefenderTroops: {},
        defendingMembers: [defenderNoShield]
    });
    const lossesNoShield = Object.values(attackerNoShield.killsByType).reduce((sum, val) => sum + val, 0);

    // 2. With "الدرع" (sp_attr = 3)
    const attackerWithShield = createAttacker();
    const defenderWithShield = createDefender([{ defence: 0, sp_attr: 3 }]);
    const resultWithShield = simulateTacticalCombat([attackerWithShield], {}, {
        solStats: SOL_STATS,
        maxRounds: 1,
        npcDefenderTroops: {},
        defendingMembers: [defenderWithShield]
    });
    const lossesWithShield = Object.values(attackerWithShield.killsByType).reduce((sum, val) => sum + val, 0);

    assert.ok(
        lossesWithShield < lossesNoShield,
        `Defender losses with shield (${lossesWithShield}) must be strictly less than without shield (${lossesNoShield})`
    );

    const reductionPercent = ((lossesNoShield - lossesWithShield) / lossesNoShield) * 100;
    assert.ok(
        reductionPercent >= 20,
        `Shield should reduce casualties by at least 20% (was ${reductionPercent.toFixed(1)}%)`
    );

    assert.ok(
        resultWithShield.roundsLog.some(line => line.includes('الدرع')),
        'Battle log must record active shield feature'
    );
});

test('Shield damage mitigation stacks linearly and caps at 60%', () => {
    const defenderCasualties = shieldCount => {
        const attacker = {
            id_member: 1,
            hero: { lvl: 1, power: 50 },
            army: { f_1_type: 2, f_1_num: 100000 },
            equipmentBonuses: []
        };
        const defender = {
            id_member: 2,
            hero: { lvl: 1, power: 50 },
            army: { f_1_type: 2, f_1_num: 100000 },
            equipmentBonuses: Array.from({ length: shieldCount }, () => ({ sp_attr: 3 }))
        };

        simulateTacticalCombat([attacker], {}, {
            solStats: SOL_STATS,
            maxRounds: 1,
            npcDefenderTroops: {},
            defendingMembers: [defender]
        });
        return attacker.killsByType[2];
    };

    const baseline = defenderCasualties(0);
    const oneShield = defenderCasualties(1);
    const fiveShields = defenderCasualties(5);
    const eightShields = defenderCasualties(8);

    assert.equal(oneShield / baseline, 0.88, 'One shield should reduce incoming damage by 12%');
    assert.equal(fiveShields / baseline, 0.4, 'Five shields should reach the 60% damage-reduction cap');
    assert.equal(eightShields, fiveShields, 'Additional shields must not exceed the 60% cap');
});

test('Army power calculation incorporates special attribute ratings', () => {
    const army = { f_1_type: 1, f_1_num: 100 };
    const hero = { power: 50, point_b: 0, point_b_plus: 0 };
    const stats = { 1: { att: 10, hp: 60 } };

    const powerBase = calculateArmyPower(army, hero, stats, [{ attack: 10 }]);
    const powerWithBarrage1 = calculateArmyPower(army, hero, stats, [{ attack: 10, sp_attr: 1 }]);
    const powerWithBarrage2 = calculateArmyPower(army, hero, stats, [{ attack: 10, sp_attr: 2 }]);
    const powerWithShield = calculateArmyPower(army, hero, stats, [{ attack: 10, sp_attr: 3 }]);

    assert.equal(powerWithBarrage1 - powerBase, 50, 'Barrage sp_attr=1 should add 50 combat power');
    assert.equal(powerWithBarrage2 - powerBase, 50, 'Barrage sp_attr=2 should add 50 combat power');
    assert.equal(powerWithShield - powerBase, 60, 'Shield should add 60 combat power');
});

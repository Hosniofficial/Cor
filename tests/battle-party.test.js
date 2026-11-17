const test = require('node:test');
const assert = require('node:assert/strict');
const equipmentPowerData = require('../caesar-assets/api/data/equipment-power.json');
const {
    allocateProportionally,
    calculateArmyCasualties,
    calculateArmyCapacity,
    calculateArmyPower,
    calculateKillBasedRewards,
    calculateMemberKillScore,
    distributeMemberTroopLosses,
    allocateResourceLoot,
    didBattleSideWin,
    simulateTacticalCombat
} = require('../caesar-shared/battle-party');

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

test('living army capacity can be used to distribute resource loot', () => {
    assert.equal(calculateArmyCapacity({
        f_1_type: 1, f_1_num: 2,
        b_1_type: 2, b_1_num: 1,
        b_2_type: 6, b_2_num: 0
    }), 180);

    const members = [
        { id_member: 1, cargoCapacity: 40 },
        { id_member: 2, cargoCapacity: 120 }
    ];
    assert.deepEqual(
        allocateProportionally(8, members, member => member.cargoCapacity).map(share => share.amount),
        [2, 6]
    );
});

test('resource loot is shared by cargo capacity and capped by total carrying capacity', () => {
    const members = [
        { id_member: 1, cargoCapacity: 40 },
        { id_member: 2, cargoCapacity: 120 }
    ];
    const shares = allocateResourceLoot(
        { food: 100, wood: 100, stone: 0, metal: 0, coin: 200 },
        members
    );
    assert.deepEqual(shares.map(share => share.resources), [
        { food: 10, wood: 10, stone: 0, metal: 0, coin: 20 },
        { food: 30, wood: 30, stone: 0, metal: 0, coin: 60 }
    ]);
    assert.deepEqual(
        shares.map(share => Object.values(share.resources).reduce((sum, amount) => sum + amount, 0)),
        [40, 120]
    );
    assert.throws(() => allocateResourceLoot({ food: -1 }, members), TypeError);
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
        f_1: { type: 1, amount: 8 },
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
    assert.deepEqual(result.woundedBySlot, {
        f_1: 1,
        f_2: 0,
        f_3: 0,
        b_1: 0,
        b_2: 0,
        b_3: 0
    });
});

test('wounded troops are removed from the field army only once', () => {
    const result = calculateArmyCasualties(
        { f_1_type: 2, f_1_num: 1000 },
        0,
        0.65,
        0.70,
        true,
        { f_1: 613 }
    );

    assert.deepEqual(result.remainingArmy.f_1, { type: 2, amount: 864 });
    assert.equal(result.totals.fallen, 387);
    assert.equal(result.totals.recovered, 251);
    assert.equal(result.totals.wounded, 95);
    assert.equal(result.totals.permanentLoss, 41);
});

test('tactical combat pools team damage and credits kills by each member’s contribution', () => {
    const attackers = [
        {
            task: 0,
            hero: { point_b: 0, point_b_plus: 0 },
            army: { f_1_type: 6, f_1_num: 2 }
        },
        {
            task: 0,
            hero: { point_b: 0, point_b_plus: 0 },
            army: { f_1_type: 1, f_1_num: 1000 }
        }
    ];
    const result = simulateTacticalCombat(attackers, { 1: 150 }, {
        solStats: [
            null,
            { att: 8, hp: 60 },
            { att: 30, hp: 250 },
            { att: 25, hp: 400 },
            { att: 9, hp: 45 },
            { att: 19, hp: 100 },
            { att: 40, hp: 600 }
        ]
    });

    assert.equal(result.roundsCount, 2);
    assert.equal(result.attackersWon, true);
    assert.equal(attackers.reduce((sum, member) => sum + member.killsByType[1], 0), 150);
    assert.ok(attackers[1].killsByType[1] > attackers[0].killsByType[1]);
    assert.equal(calculateMemberKillScore({ 1: 500, 6: 1 }), 2150);
});

test('tactical combat continues beyond three rounds until one side is eliminated', () => {
    const attackers = [{
        hero: { lvl: 1, point_b: 0, point_b_plus: 0 },
        army: { f_1_type: 1, f_1_num: 12 }
    }];
    const result = simulateTacticalCombat(attackers, { 1: 10 }, {
        npcDefenderTroops: { 1: 10 },
        solStats: [null, { att: 8, hp: 60 }]
    });

    assert.ok(result.roundsCount > 3);
    assert.equal(result.attackersWon, true);
    assert.equal(result.livingDefenders[1], 0);
    assert.ok(result.remainingAttackers[0].army.f_1 > 0);
});

test('unbounded tactical combat fails explicitly when neither side can make progress', () => {
    assert.throws(() => simulateTacticalCombat([{
        hero: { lvl: 1 },
        army: { f_1_type: 1, f_1_num: 1 }
    }], { 2: 1 }, {
        solStats: [null, { att: 0, hp: 60 }, { att: 0, hp: 250 }]
    }), /made no progress/);
});

test('simulated counterattack casualties are used by post-battle casualty recovery', () => {
    const attackers = [{
        hero: { point_b: 0 },
        army: { f_1_type: 1, f_1_num: 10 }
    }];
    const result = simulateTacticalCombat(attackers, { 2: 10 }, {
        maxRounds: 1,
        solStats: [
            null,
            { att: 8, hp: 60 },
            { att: 30, hp: 250 }
        ]
    });
    assert.equal(result.attackersWon, false);
    assert.equal(result.remainingAttackers[0].army.f_1, 6);
    const casualties = calculateArmyCasualties(
        attackers[0].army,
        0.1,
        0.5,
        0.2,
        false,
        result.remainingAttackers[0].army
    );
    assert.equal(casualties.totals.fallen, 4);
    assert.equal(casualties.remainingArmy.f_1.amount, 8);
});

test('joined defenders receive per-hero kill credit for counterattacks', () => {
    const attacker = {
        hero: { point_b: 0 },
        army: { f_1_type: 1, f_1_num: 10 }
    };
    const defender = {
        id_member: 4,
        hero: { point_b: 0 },
        army: { f_1_type: 2, f_1_num: 5 }
    };
    const result = simulateTacticalCombat([attacker], { 2: 5 }, {
        maxRounds: 1,
        defendingMembers: [defender],
        npcDefenderTroops: {},
        solStats: [
            null,
            { att: 8, hp: 60 },
            { att: 30, hp: 250 }
        ]
    });

    assert.deepEqual(defender.killsByType[1], 2);
    assert.equal(result.remainingDefenders[0].army.f_1, 4);
});

test('wall traps apply before the first attack round and appear in the combat log', () => {
    const attackers = [{
        hero: { point_b: 0 },
        army: { f_1_type: 1, f_1_num: 10 }
    }];
    const result = simulateTacticalCombat(attackers, { 1: 1 }, {
        solStats: [
            null,
            { att: 8, hp: 60 },
            { att: 30, hp: 250 },
            { att: 25, hp: 400 },
            { att: 9, hp: 45 },
            { att: 19, hp: 100 },
            { att: 40, hp: 600 }
        ],
        wallFortifications: { traps: 2 }
    });

    assert.equal(result.attackersWon, true);
    assert.equal(result.remainingAttackers[0].army.f_1, 9);
    assert.match(result.roundsLog[0], /كمائن السور/);
});

test('10v10 large armies resolve identically regardless of participant query order', () => {
    const makeSide = baseId => Array.from({ length: 10 }, (_, index) => ({
        id_member: baseId + index,
        hero: {
            lvl: 20 + index,
            power: 50 + index,
            point_b: index * 3,
            point_c: index * 2
        },
        army: {
            f_1_type: index % 2 ? 1 : 2,
            f_1_num: 1000000,
            b_1_type: 6,
            b_1_num: index % 3 ? 100000 : 0
        },
        equipmentBonuses: index % 2
            ? [{ attack: 20, defence: 15, damage: 3, vitality: 10 }]
            : [{ attack: 10, defence: 25, strike: 4, immunity: 2 }]
    }));
    const attackers = makeSide(1);
    const defenders = makeSide(101);
    const options = {
        solStats: [
            null,
            { att: 8, hp: 60 },
            { att: 30, hp: 250 },
            { att: 25, hp: 400 },
            { att: 9, hp: 45 },
            { att: 19, hp: 100 },
            { att: 40, hp: 600 }
        ],
        npcDefenderTroops: {},
        defendingMembers: defenders
    };
    const first = simulateTacticalCombat(attackers, {}, options);
    const reorderedAttackers = makeSide(1).reverse();
    const reorderedDefenders = makeSide(101).reverse();
    const reordered = simulateTacticalCombat(reorderedAttackers, {}, {
        ...options,
        defendingMembers: reorderedDefenders
    });
    const normalize = result => ({
        attackersWon: result.attackersWon,
        roundsCount: result.roundsCount,
        roundsLog: result.roundsLog,
        attackers: result.remainingAttackers
            .map(({ member, army }) => ({ id: member.id_member, army, kills: member.killsByType }))
            .sort((left, right) => left.id - right.id),
        defenders: result.remainingDefenders
            .map(({ member, army }) => ({ id: member.id_member, army, kills: member.killsByType }))
            .sort((left, right) => left.id - right.id)
    });

    assert.deepEqual(normalize(reordered), normalize(first));
    assert.ok(first.roundsCount > 3);
    assert.equal(first.livingDefenders && Object.values(first.livingDefenders).reduce((sum, count) => sum + count, 0), 0);
    assert.equal(first.remainingAttackers.length, 10);
    assert.equal(first.remainingDefenders.length, 10);
});

test('250 participants per side can resolve million-unit armies in aggregate', () => {
    const makeSide = baseId => Array.from({ length: 250 }, (_, index) => ({
        id_member: baseId + index,
        hero: { lvl: 1, power: 50 },
        army: { f_1_type: 1, f_1_num: 1000000 }
    }));
    const attackers = makeSide(1);
    const defenders = makeSide(1001);
    const result = simulateTacticalCombat(attackers, {}, {
        maxRounds: 1,
        solStats: [null, { att: 8, hp: 60 }],
        npcDefenderTroops: {},
        defendingMembers: defenders
    });

    assert.equal(result.remainingAttackers.length, 250);
    assert.equal(result.remainingDefenders.length, 250);
    assert.equal(result.roundsCount, 1);
    assert.equal(
        attackers.reduce((sum, member) => sum + member.killsByType[1], 0),
        250000000 - result.remainingDefenders.reduce((sum, member) => sum + member.army.f_1, 0)
    );
    assert.equal(
        defenders.reduce((sum, member) => sum + member.killsByType[1], 0),
        250000000 - result.remainingAttackers.reduce((sum, member) => sum + member.army.f_1, 0)
    );
});

test('combat equipment attack and defense stats affect group casualties', () => {
    const stats = [null, { att: 8, hp: 60 }];
    const run = (attackerEquipment, defenderEquipment) => {
        const attacker = {
            id_member: 1,
            hero: { lvl: 1, power: 50 },
            army: { f_1_type: 1, f_1_num: 1000 },
            equipmentBonuses: attackerEquipment
        };
        const defender = {
            id_member: 2,
            hero: { lvl: 1, power: 50 },
            army: { f_1_type: 1, f_1_num: 1000 },
            equipmentBonuses: defenderEquipment
        };
        const result = simulateTacticalCombat([attacker], {}, {
            solStats: stats,
            maxRounds: 1,
            npcDefenderTroops: {},
            defendingMembers: [defender]
        });
        return {
            defenderKills: attacker.killsByType[1],
            attackerLosses: 1000 - result.remainingAttackers[0].army.f_1
        };
    };
    const baseline = run([], []);
    const attackerWithWeapon = run([{ attack: 100 }], []);
    const defenderWithArmor = run([], [{ defence: 300 }]);

    assert.ok(attackerWithWeapon.defenderKills > baseline.defenderKills);
    assert.ok(defenderWithArmor.defenderKills < baseline.defenderKills);
});

test('real level-one blue equipment changes battle damage and troop losses', () => {
    const equipment = ['armor', 'boot', 'helmet', 'shield', 'sword'].map(part =>
        equipmentPowerData.find(item => item.equip === 'blue' && item.part === part && item.lvl === 1)
    );
    assert.ok(equipment.every(Boolean));

    const run = equipmentBonuses => {
        const attacker = {
            id_member: 1,
            hero: { lvl: 1, power: 50 },
            army: { f_1_type: 1, f_1_num: 1000 },
            equipmentBonuses
        };
        const defender = {
            id_member: 2,
            hero: { lvl: 1, power: 50 },
            army: { f_1_type: 1, f_1_num: 1000 }
        };
        const result = simulateTacticalCombat([attacker], {}, {
            solStats: [null, { att: 8, hp: 60 }],
            maxRounds: 1,
            npcDefenderTroops: {},
            defendingMembers: [defender]
        });
        return {
            kills: attacker.killsByType[1],
            attackerLosses: 1000 - result.remainingAttackers[0].army.f_1
        };
    };

    const unequipped = run([]);
    const equipped = run(equipment);
    assert.ok(equipped.kills > unequipped.kills);
    assert.ok(equipped.attackerLosses < unequipped.attackerLosses);
});

test('battle damage follows the hero formation slot order', () => {
    const attacker = {
        id_member: 1,
        hero: { lvl: 1, power: 50 },
        army: { f_1_type: 2, f_1_num: 1, f_2_type: 1, f_2_num: 10 }
    };
    const defender = {
        id_member: 2,
        hero: { lvl: 1, power: 50 },
        army: { f_1_type: 1, f_1_num: 40 }
    };
    const result = simulateTacticalCombat([attacker], {}, {
        solStats: [null, { att: 8, hp: 60 }, { att: 30, hp: 250 }],
        maxRounds: 1,
        npcDefenderTroops: {},
        defendingMembers: [defender]
    });

    assert.equal(result.remainingAttackers[0].army.f_1, 0);
    assert.equal(result.remainingAttackers[0].army.f_2, 9);
});

test('all numeric equipment combat attributes use the backend BattleService weights', () => {
    const stats = [null, { att: 8, hp: 60 }];
    const simulate = (attackerEquipment = [], defenderEquipment = []) => {
        const attacker = {
            id_member: 1,
            hero: { lvl: 1, power: 50 },
            army: { f_1_type: 1, f_1_num: 100 },
            equipmentBonuses: attackerEquipment
        };
        const defender = {
            id_member: 2,
            hero: { lvl: 1, power: 50 },
            army: { f_1_type: 1, f_1_num: 10000 },
            equipmentBonuses: defenderEquipment
        };
        simulateTacticalCombat([attacker], {}, {
            solStats: stats,
            maxRounds: 1,
            npcDefenderTroops: {},
            defendingMembers: [defender]
        });
        return attacker.killsByType[1];
    };
    const baseline = simulate();

    for (const bonus of [
        { attack: 100 },
        { damage: 100 },
        { break: 100 },
        { strike: 100 }
    ]) {
        assert.ok(simulate([bonus]) > baseline, `${Object.keys(bonus)[0]} should increase dealt damage`);
    }
    for (const bonus of [
        { defence: 300 },
        { vitality: 1000 },
        { anti_break: 1000 },
        { immunity: 2000 }
    ]) {
        assert.ok(simulate([], [bonus]) < baseline, `${Object.keys(bonus)[0]} should reduce received damage`);
    }
});

test('kill-based rewards do not award non-killers a share of another member’s kills', () => {
    const attackers = [
        {
            task: 0,
            hero: { point_b: 100, point_b_plus: 10 },
            player: { prestige: 100 },
            killsByType: { 6: 500 }
        },
        {
            task: 0,
            hero: { point_b: 0, point_b_plus: 0 },
            player: { prestige: 100 },
            killsByType: { 1: 0 }
        }
    ];
    const rewards = calculateKillBasedRewards({
        attackers,
        isVictory: true,
        targetType: 70,
        targetLvl: 1
    });

    assert.equal(rewards[0].expGained, Math.floor((75000 * 1.25 + 20) * 1.11));
    assert.equal(rewards[1].expGained, 20);
    assert.equal(rewards[1].honorGained, 0);
    assert.ok(rewards.every(reward => !Object.hasOwn(reward, 'prestigeGained')));
});

test('kill-based rewards apply defeat, domination, and PvP honor rules', () => {
    const attacker = {
        task: 1,
        hero: { point_b: 0, point_b_plus: 0 },
        player: { prestige: 500 },
        killsByType: { 1: 10 }
    };
    const defeat = calculateKillBasedRewards({
        attackers: [attacker],
        isVictory: false,
        targetType: 1,
        targetLvl: 2
    })[0];
    assert.equal(defeat.expGained, 20);
    assert.equal(defeat.honorGained, 10);
    assert.ok(!Object.hasOwn(defeat, 'prestigeGained'));

    const domination = calculateKillBasedRewards({
        attackers: [attacker],
        isVictory: true,
        targetType: 1,
        targetLvl: 2
    })[0];
    assert.equal(domination.honorGained, 170);

    const pvp = calculateKillBasedRewards({
        attackers: [attacker],
        isVictory: true,
        targetType: 60,
        targetLvl: 2,
        defenderPrestige: 100
    })[0];
    assert.equal(pvp.honorGained, 0);
});

test('kill-based rewards use the report experience multipliers without granting prestige', () => {
    const attacker = {
        task: 0,
        hero: { point_b: 0, point_b_plus: 0 },
        player: { prestige: 100 },
        killsByType: { 6: 1 }
    };
    const expectedExperience = new Map([
        [1, 170],
        [70, 207],
        [97, 245],
        [150, 320],
        [60, 170]
    ]);

    for (const [targetType, xp] of expectedExperience) {
        const [reward] = calculateKillBasedRewards({
            attackers: [attacker],
            isVictory: true,
            targetType,
            targetLvl: 1
        });
        assert.equal(reward.expGained, xp);
        assert.ok(!Object.hasOwn(reward, 'prestigeGained'));
    }

    const [noKills] = calculateKillBasedRewards({
        attackers: [{
            id_member: 1,
            task: 0,
            hero: { point_b: 0 },
            player: { id_player: 5, prestige: 100 },
            killsByType: {}
        }],
        isVictory: true,
        targetType: 1,
        targetLvl: 1
    });
    assert.equal(noKills.expGained, 20);
    assert.ok(!Object.hasOwn(noKills, 'prestigeGained'));
});

test('honor is calculated once per player when multiple heroes participate', () => {
    const attackers = [
        {
            id_member: 1,
            task: 1,
            hero: { point_b: 0 },
            player: { id_player: 7, prestige: 100 },
            killsByType: { 1: 10 }
        },
        {
            id_member: 2,
            task: 1,
            hero: { point_b: 0 },
            player: { id_player: 7, prestige: 100 },
            killsByType: { 1: 10 }
        }
    ];
    const rewards = calculateKillBasedRewards({
        attackers,
        isVictory: true,
        targetType: 1,
        targetLvl: 1
    });

    assert.deepEqual(rewards.map(reward => reward.honorGained), [70, 70]);
    assert.ok(rewards.every(reward => !Object.hasOwn(reward, 'prestigeGained')));
});

test('defender troop losses are distributed proportionally by unit and preserve each participant’s formation', () => {
    const defenders = [
        {
            id_member: 1,
            army: { f_1_type: 1, f_1_num: 10, b_1_type: 2, b_1_num: 3 }
        },
        {
            id_member: 2,
            army: { f_1_type: 1, f_1_num: 30, b_1_type: 2, b_1_num: 1 }
        }
    ];
    const remaining = distributeMemberTroopLosses(defenders, { 1: 20, 2: 2 });
    assert.deepEqual(remaining.get(1), { f_1: 5, f_2: 0, f_3: 0, b_1: 1, b_2: 0, b_3: 0 });
    assert.deepEqual(remaining.get(2), { f_1: 15, f_2: 0, f_3: 0, b_1: 1, b_2: 0, b_3: 0 });
});

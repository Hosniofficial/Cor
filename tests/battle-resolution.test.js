const test = require('node:test');
const assert = require('node:assert/strict');
const equipmentPowerData = require('../caesar-private/data/equipment-power.json');
const {
    applyCityConquest,
    calculateCityConquestLoyalty,
    enlistCityGarrisonDefenders,
    resolveBattle,
    shouldBroadcastBattleVictory
} = require('../caesar-shared/battle-resolution');
const {
    allocateProportionally,
    calculateArmyCasualties,
    calculateArmyCapacity,
    calculateArmyPower,
    calculateKillBasedRewards,
    didBattleSideWin,
    distributeMemberTroopLosses,
    simulateTacticalCombat
} = require('../caesar-shared/battle-party');

const slots = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
const equipmentPowerMap = new Map(
    equipmentPowerData.map(item => [`${item.equip}_${item.part}_${item.lvl}`, item])
);
const solStats = [
    { att: 0, hp: 0 },
    { att: 8, hp: 60 },
    { att: 30, hp: 250 },
    { att: 25, hp: 400 },
    { att: 9, hp: 45 },
    { att: 19, hp: 100 },
    { att: 40, hp: 600 }
];

test('only victorious city-colonization attacks reduce loyalty by five points', () => {
    assert.equal(calculateCityConquestLoyalty(20, true, 0, 60), 15);
    assert.equal(calculateCityConquestLoyalty(3, true, 0, 60), 0);
    assert.equal(calculateCityConquestLoyalty(20, false, 0, 60), null);
    assert.equal(calculateCityConquestLoyalty(20, true, 4, 60), null);
    assert.equal(calculateCityConquestLoyalty(20, true, 0, 1), null);
});

test('conquest creates tributary relation at zero loyalty without changing city ownership', async () => {
    const calls = [];
    const db = {
        async query(sql, params = []) {
            calls.push({ sql, params });
            if (sql.startsWith('SELECT id_city, id_player, loy, loy_max, dis_loy')) {
                return [[{
                    id_city: 8,
                    id_player: 4,
                    loy: 5,
                    loy_max: 90,
                    dis_loy: 2
                }], []];
            }
            if (sql.startsWith('SELECT id_city FROM city_tributary')) return [[], []];
            return [{ affectedRows: 1 }, []];
        }
    };

    const result = await applyCityConquest(db, {
        targetCity: { id_city: 8, id_player: 4, loy: 5 },
        isVictory: true,
        attackTask: 0,
        unitType: 60,
        attackerPlayerId: 2,
        sovereignCityId: 3,
        now: 123
    });

    assert.deepEqual(result, {
        idCity: 8,
        idSovereignCity: 3,
        idOwner: 4,
        loyalty: 90,
        subordinated: true
    });
    assert.ok(calls.some(({ sql, params }) =>
        sql.startsWith('INSERT INTO city_tributary') &&
        JSON.stringify(params) === JSON.stringify([8, 3, 123, 123])
    ));
    assert.ok(calls.some(({ sql }) => sql.startsWith('UPDATE city SET loy = ?')));
    assert.ok(!calls.some(({ sql }) => /UPDATE\s+city\s+SET\s+id_player/i.test(sql)));
});

test('already-subordinate cities are not conquered again', async () => {
    const updates = [];
    const db = {
        async query(sql) {
            if (sql.startsWith('SELECT id_city, id_player, loy, loy_max, dis_loy')) {
                return [[{
                    id_city: 8,
                    id_player: 4,
                    loy: 45,
                    loy_max: 90,
                    dis_loy: 0
                }], []];
            }
            if (sql.startsWith('SELECT id_city FROM city_tributary')) return [[{ id_city: 8 }], []];
            updates.push(sql);
            return [{ affectedRows: 1 }, []];
        }
    };

    const result = await applyCityConquest(db, {
        targetCity: { id_city: 8, id_player: 4, loy: 45 },
        isVictory: true,
        attackTask: 0,
        unitType: 60,
        attackerPlayerId: 2,
        sovereignCityId: 3,
        now: 123
    });
    assert.equal(result, null);
    assert.deepEqual(updates, []);
});

test('city garrison defenders are enlisted in order and respect the battle-side cap', async () => {
    const calls = [];
    const db = {
        async query(sql, params = []) {
            calls.push({ sql, params });
            if (sql.startsWith('SELECT COUNT(*) AS amount FROM world_battel_member')) {
                return [[{ amount: 0 }], []];
            }
            if (sql.startsWith('SELECT g.id_hero')) {
                return [[
                    {
                        id_hero: 42, id_player: 7, ord: 0, home_city_id: 2,
                        in_city: 0, home_x: 10, home_y: 11,
                        f_1_num: 10, f_2_num: 0, f_3_num: 0,
                        b_1_num: 0, b_2_num: 0, b_3_num: 0
                    },
                    {
                        id_hero: 43, id_player: 8, ord: 1, home_city_id: 3,
                        in_city: 1, home_x: 12, home_y: 13,
                        f_1_num: 10, f_2_num: 0, f_3_num: 0,
                        b_1_num: 0, b_2_num: 0, b_3_num: 0
                    }
                ], []];
            }
            return [{ affectedRows: 1 }, []];
        }
    };

    const enlisted = await enlistCityGarrisonDefenders(db, {
        battleId: 99,
        cityId: 4,
        battleArrival: 200,
        now: 150,
        maxDefenders: 1
    });

    assert.deepEqual(enlisted, [42]);
    const insertion = calls.find(({ sql }) => sql.startsWith('INSERT INTO world_battel_member'));
    assert.ok(insertion);
    assert.equal(insertion.params[3], 42);
    assert.ok(calls.some(({ sql, params }) =>
        sql.startsWith("UPDATE city_garrison SET status = 'engaged'") &&
        JSON.stringify(params) === JSON.stringify([99, 42])
    ));
    assert.equal(calls.filter(({ sql }) => sql.startsWith('INSERT INTO world_battel_member')).length, 1);
});

test('battle resolution releases its transaction when the battle is no longer marching', async () => {
    const calls = [];
    const connection = {
        async beginTransaction() {
            calls.push('begin');
        },
        async query(sql) {
            calls.push(sql);
            return [[{ state: 'aborted' }], []];
        },
        async rollback() {
            calls.push('rollback');
        },
        async commit() {
            calls.push('commit');
        },
        release() {
            calls.push('release');
        }
    };
    const context = {
        battelId: 7,
        idHero: 11,
        returnDuration: 60,
        unitType: 70,
        unitLvl: 1,
        unitTitle: 'مناورات',
        attackTask: 0,
        xCoord: 10,
        yCoord: 20,
        targetCityRows: [],
        isBarray: false,
        unitTypeData: {},
        baseUnit: null,
        hero: { id_hero: 11, name: 'Hero' },
        player: { id_player: 2, name: 'Player' },
        city: { id_city: 3, x: 1, y: 1 }
    };
    const dependencies = {
        getDb: async () => ({ getConnection: async () => connection }),
        publishBroadcast() {},
        publishToPlayer() {}
    };

    await resolveBattle(context, dependencies);
    assert.deepEqual(calls, [
        'begin',
        'SELECT state, time_arrive FROM world_battel WHERE id_battel = ? FOR UPDATE',
        'rollback',
        'release'
    ]);
});

test('battle resolution saves kill-based rewards and actual round details', async () => {
    const equippedSword = equipmentPowerData.find(item =>
        item.equip === 'blue' && item.part === 'sword' && item.lvl === 1
    );
    const savedReports = [];
    const queries = [];
    const heroArmyWrites = [];
    const playerItemWrites = [];
    const resourceCredits = [];
    let receivedEquipmentBonuses;
    const colonizationInserts = [];
    const broadcasts = [];
    let palaceLevel = 4;
    let wildCount = 0;
    let committed = false;
    const connection = {
        async beginTransaction() {},
        async commit() {
            committed = true;
        },
        async rollback() {},
        release() {},
        async query(sql, params = []) {
            queries.push(sql);
            if (sql.startsWith('INSERT INTO player_item')) playerItemWrites.push(params);
            if (sql.startsWith('INSERT INTO hero_army')) heroArmyWrites.push(params);
            if (sql.startsWith('INSERT INTO city_barray')) {
                colonizationInserts.push(params);
                wildCount++;
            }
            if (sql.startsWith('UPDATE city_hero SET exp = exp + CASE')) {
                queries.push(`hero-exp:${params[1]}`);
            }
            if (sql.startsWith('SELECT state, time_arrive FROM world_battel')) {
                return [[{ state: 'marching', time_arrive: 100 }], []];
            }
            if (sql.startsWith('SELECT m.id_member')) {
                return [[{
                    id_member: 1,
                    id_player: 2,
                    id_city: 3,
                    id_hero: 11,
                    side: 1,
                    task: 1,
                    return_duration: 60,
                    hero_name: 'Hero',
                    hero_avatar: 1,
                    hero_power: 50,
                    hero_power_max: 100,
                    point_b: 0,
                    point_b_plus: 0,
                    player_name: 'Player',
                    player_id_guild: 4,
                    city_flag: '0',
                    player_prestige: 100,
                    city_name: 'Home',
                    city_x: 1,
                    city_y: 1
                }], []];
            }
            if (sql.startsWith('SELECT * FROM hero_army')) {
                return [[{
                    id_hero: 11,
                    f_1_type: 6,
                    f_1_num: 2,
                    f_2_type: 0,
                    f_2_num: 0,
                    f_3_type: 0,
                    f_3_num: 0,
                    b_1_type: 0,
                    b_1_num: 0,
                    b_2_type: 0,
                    b_2_num: 0,
                    b_3_type: 0,
                    b_3_num: 0
                }], []];
            }
            if (sql.startsWith('SELECT * FROM player_equip')) {
                return [[{
                    id_hero: 11,
                    type: 'blue',
                    part: 'sword',
                    lvl: 1,
                    on_hero: 1
                }], []];
            }
            if (sql.startsWith('SELECT id_player, medicine')) return [[], []];
            if (sql.startsWith('SELECT id_hero, medal_den')) return [[], []];
            if (sql.startsWith('SELECT id_city, id_player, food')) {
                return [[{
                    id_city: 3,
                    id_player: 2,
                    food: 0,
                    wood: 0,
                    stone: 0,
                    metal: 0,
                    coin: 0
                }], []];
            }
            if (sql.startsWith('SELECT COALESCE(MAX(lvl), 0) AS lvl FROM city_building')) {
                return [[{ lvl: palaceLevel }], []];
            }
            if (sql.startsWith('SELECT COUNT(*) as cnt FROM city_barray')) {
                return [[{ cnt: wildCount }], []];
            }
            return [{ affectedRows: 1, insertId: 1 }, []];
        }
    };
    const aggregateMemberArmy = members => {
        const troops = {};
        for (const member of members) {
            for (const slot of slots) {
                const type = Number(member.army[`${slot}_type`]) || 0;
                const amount = Number(member.army[`${slot}_num`]) || 0;
                if (!type || !amount) continue;
                if (!troops[type]) troops[type] = { pre: 0, cur: 0 };
                troops[type].pre += amount;
                troops[type].cur += Number(member.remainingArmy && member.remainingArmy[slot]) || 0;
            }
        }
        const army = {};
        const remainingArmy = {};
        Object.entries(troops).forEach(([type, counts], index) => {
            army[`${slots[index]}_type`] = Number(type);
            army[`${slots[index]}_num`] = counts.pre;
            remainingArmy[slots[index]] = counts.cur;
        });
        return { army, remainingArmy, troops };
    };
    const reportArmySlots = (army, remainingArmy, defenderTroops) => ({
        attackerSlots: Object.fromEntries(slots.map(slot => [slot, {
            type: Number(army[`${slot}_type`]) || 0,
            pre: Number(army[`${slot}_num`]) || 0,
            cur: Number(remainingArmy[slot]) || 0
        }])),
        defenderSlots: Object.fromEntries(slots.map((slot, index) => {
            const [type, counts] = Object.entries(defenderTroops)[index] || [0, { pre: 0, cur: 0 }];
            return [slot, {
                type: Number(type),
                pre: Number(counts.pre) || 0,
                cur: Number(counts.cur) || 0
            }];
        }))
    });
    const context = {
        battelId: 7,
        idHero: 11,
        battleStartTime: 40,
        battleArrivalTime: 100,
        returnDuration: 60,
        unitType: 44,
        unitLvl: 1,
        unitTitle: 'برية',
        attackTask: 1,
        xCoord: 10,
        yCoord: 20,
        targetCityRows: [],
        isBarray: false,
        unitTypeData: {},
        baseUnit: null,
        hero: { id_hero: 11, name: 'Hero' },
        player: { id_player: 2, name: 'Player', id_guild: 0 },
        city: { id_city: 3, x: 1, y: 1 }
    };
    const dependencies = {
        getDb: async () => ({ getConnection: async () => connection }),
        worldUnits: new Map(),
        unitData: {},
        unitArmy: { 44: { 1: { 1: 1, army_a: 1 } } },
        prizeData: {
            Win: [
                { unitType: 44, lvl: 1, prize: 'stone_a', amount_min: 4, amount_max: 4, win_rate: 10000 },
                { unitType: 44, lvl: 1, prize: 'coin_a', amount_min: 1, amount_max: 1, win_rate: 10000 },
                { unitType: 44, lvl: 1, prize: 'necklace_1', amount_min: 1, amount_max: 1, win_rate: 10000 },
                { unitType: 44, lvl: 1, prize: 'luxury_1', amount_min: 1, amount_max: 1, win_rate: 10000 }
            ]
        },
        equipPowerMap: new Map([['blue_sword_1', equippedSword]]),
        liveServerConfig: { wounded_recovery_rate: 0, event_multipliers: {} },
        SOL_STATS: solStats,
        slots,
        aggregateMemberArmy,
        reportArmySlots,
        creditCityResources: async (db, idCity, resources) => {
            resourceCredits.push({ idCity, resources });
            return resources;
        },
        advanceWorldUnitLevel: async () => 1,
        allocateProportionally,
        calculateArmyPower,
        calculateArmyCapacity,
        calculateKillBasedRewards,
        distributeMemberTroopLosses,
        didBattleSideWin,
        calculateArmyCasualties,
        simulateTacticalCombat(...args) {
            receivedEquipmentBonuses = args[0][0].equipmentBonuses;
            return simulateTacticalCombat(...args);
        },
        hasActiveBattleAt: async () => false,
        publishBroadcast(message) {
            broadcasts.push(JSON.parse(message));
        },
        publishToPlayer() {},
        saveWsBattleReport: async (db, report) => {
            assert.equal(committed, true);
            savedReports.push(report);
        }
    };

    await resolveBattle(context, dependencies);

    assert.equal(savedReports.length, 1);
    assert.equal(savedReports[0].battleStartTime, 40);
    assert.equal(savedReports[0].battleArrivalTime, 100);
    assert.equal(savedReports[0].returnDuration, 60);
    assert.equal(savedReports[0].roundCount, 1);
    assert.equal(savedReports[0].combatSnapshot.version, 1);
    assert.equal(savedReports[0].combatSnapshot.reportHeroId, 11);
    assert.equal(savedReports[0].combatSnapshot.attackers.length, 1);
    assert.equal(savedReports[0].combatSnapshot.attackers[0].hero.id_hero, 11);
    assert.equal(savedReports[0].combatSnapshot.attackers[0].army.f_1_num, 2);
    assert.equal(savedReports[0].combatSnapshot.attackers[0].equipmentBonuses[0].sp_attr, equippedSword.sp_attr);
    assert.equal(savedReports[0].combatSnapshot.wallFortifications.traps, 0);
    assert.deepEqual(receivedEquipmentBonuses, [equippedSword]);
    assert.ok(savedReports[0].roundsLog.some(line => /معدات بطل المهاجم/.test(line)));
    assert.ok(savedReports[0].roundsLog.some(line => /الجولة/.test(line)));
    assert.deepEqual(savedReports[0].killsByType, { 1: 1, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0, 7: 0 });
    assert.equal(savedReports[0].expGained, 24);
    assert.equal(savedReports[0].honorGained, 102);
    assert.equal(resourceCredits.length, 1);
    assert.equal(resourceCredits[0].idCity, 3);
    for (const resource of ['food', 'wood', 'stone', 'metal', 'coin']) {
        assert.equal(Number.isSafeInteger(resourceCredits[0].resources[resource]), true);
        assert.equal(resourceCredits[0].resources[resource] > 0, resource === 'stone');
    }
    assert.deepEqual(savedReports[0].resourceLoot, resourceCredits[0].resources);
    assert.deepEqual(playerItemWrites.map(params => params[1]).sort(), [
        'luxury_1',
        'necklace_1'
    ]);
    assert.doesNotMatch(savedReports[0].opponentName, /^القيصر /);
    assert.doesNotMatch(savedReports[0].opponentName, /مستوى/);
    const heroRewardUpdates = queries.filter(sql => sql.startsWith('UPDATE city_hero SET exp ='));
    assert.ok(heroRewardUpdates.length > 0);
    assert.ok(heroRewardUpdates.every(sql => /power = LEAST\(power_max, power \+ 5\)/.test(sql)));
    assert.ok(queries.includes('hero-exp:24'));
    assert.ok(queries.some(sql => sql.startsWith('INSERT INTO hero_army')));
    assert.ok(queries.some(sql => sql.startsWith('UPDATE player SET honor =')));
    assert.ok(queries.some(sql => sql.startsWith('UPDATE guild SET honor =')));
    assert.ok(!queries.some(sql => /UPDATE (player|guild) SET .*prestige/i.test(sql)));
    assert.deepEqual(colonizationInserts, [[3, 2, 10, 20, 44, 1]]);
    assert.ok(broadcasts.some(message =>
        message.classPath === 'Base.refreshWorldCityColonization' && message.data.colonized === true
    ));

    const insertCountAtCapacity = colonizationInserts.length;
    const colonizationBroadcastCount = () => broadcasts.filter(message =>
        message.classPath === 'Base.refreshWorldCityColonization' && message.data.colonized === true
    ).length;
    const broadcastCountAtCapacity = colonizationBroadcastCount();
    palaceLevel = 30;
    wildCount = 10;
    dependencies.unitArmy[44][1][1] = 100000;
    await resolveBattle(context, dependencies);
    assert.equal(colonizationInserts.length, insertCountAtCapacity);
    assert.equal(colonizationBroadcastCount(), broadcastCountAtCapacity);
    const defeatedArmyWrite = heroArmyWrites[heroArmyWrites.length - 1];
    assert.deepEqual(defeatedArmyWrite.slice(2), Array(12).fill(0));

    // Wilderness battle victory should NOT broadcast to world chat
    const worldChatMessages = broadcasts.filter(m => m.classPath === 'Chat.WorldMsg');
    assert.equal(worldChatMessages.length, 0, 'wilderness attacks must never broadcast to world chat');
});

test('shouldBroadcastBattleVictory accurately filters world announcements based on authentic rules', () => {
    // 1. Wilderness (1..59) must NEVER broadcast
    for (const type of [1, 5, 10, 30, 35, 40, 44, 50, 57, 59]) {
        assert.equal(shouldBroadcastBattleVictory(type, 1), false, `wilderness type ${type} lvl 1 must not broadcast`);
        assert.equal(shouldBroadcastBattleVictory(type, 10), false, `wilderness type ${type} lvl 10 must not broadcast`);
    }

    // 2. Gangs, Bandits & Carthage Rebels (97..105) must NEVER broadcast
    for (let type = 97; type <= 105; type++) {
        assert.equal(shouldBroadcastBattleVictory(type, 1), false, `gang type ${type} lvl 1 must not broadcast`);
        assert.equal(shouldBroadcastBattleVictory(type, 10), false, `gang type ${type} lvl 10 must not broadcast`);
    }

    // 3. Regular units / squads (81..96) must NEVER broadcast
    for (let type = 81; type <= 96; type++) {
        assert.equal(shouldBroadcastBattleVictory(type, 10), false, `squad type ${type} must not broadcast`);
    }

    // 4. Camps & Maneuvers (70..80) ONLY broadcast on rounds 10, 20, 30, 40, 50
    for (let type = 70; type <= 80; type++) {
        for (const nonMilestone of [1, 2, 5, 9, 11, 15, 19, 21, 29, 31, 49, 51]) {
            assert.equal(shouldBroadcastBattleVictory(type, nonMilestone), false, `camp ${type} lvl ${nonMilestone} must not broadcast`);
        }
        for (const milestone of [10, 20, 30, 40, 50]) {
            assert.equal(shouldBroadcastBattleVictory(type, milestone), true, `camp ${type} milestone lvl ${milestone} MUST broadcast`);
        }
    }

    // 5. War Statues (200..202) ALWAYS broadcast on victory
    for (let type = 200; type <= 202; type++) {
        for (const lvl of [1, 5, 10, 25, 50, 75]) {
            assert.equal(shouldBroadcastBattleVictory(type, lvl), true, `war statue ${type} lvl ${lvl} MUST broadcast`);
        }
    }

    // 6. Castles (Small 184, Medium 185, Great 186) ALWAYS broadcast on victory
    for (let type = 184; type <= 186; type++) {
        for (const lvl of [1, 5, 10]) {
            assert.equal(shouldBroadcastBattleVictory(type, lvl), true, `castle ${type} lvl ${lvl} MUST broadcast`);
        }
    }

    // 7. Epic targets: Capitals, Arenas, Queens, Wolf Groups, Resource Fortresses
    assert.equal(shouldBroadcastBattleVictory(150, 1), true, 'Infantry capital must broadcast');
    assert.equal(shouldBroadcastBattleVictory(155, 1), true, 'Catapult capital must broadcast');
    assert.equal(shouldBroadcastBattleVictory(175, 1), true, 'Challenge arena must broadcast');
    assert.equal(shouldBroadcastBattleVictory(180, 1), true, 'Queen small must broadcast');
    assert.equal(shouldBroadcastBattleVictory(182, 1), true, 'Queen great must broadcast');
    assert.equal(shouldBroadcastBattleVictory(203, 1), true, 'Wolf group 1 must broadcast');
    assert.equal(shouldBroadcastBattleVictory(220, 1), true, 'Caesar battlefield must broadcast');
    assert.equal(shouldBroadcastBattleVictory(231, 1), true, 'Food fortress must broadcast');

    // 8. City colonization when subordinated
    assert.equal(shouldBroadcastBattleVictory(60, 1, { cityColonization: { subordinated: false } }), false);
    assert.equal(shouldBroadcastBattleVictory(60, 1, { cityColonization: { subordinated: true } }), true);
});

test('battle item drops honor table probabilities and exact rounds for victories and defeats', async () => {
    const makeHarness = (unitType, unitLvl, prizes, randomValues = [], attackersWon = true) => {
        const itemWrites = [];
        const equipWrites = [];
        const savedReports = [];
        const randomResults = [...randomValues];
        const connection = {
            async beginTransaction() {},
            async commit() {},
            async rollback() {},
            release() {},
            async query(sql, params = []) {
                if (sql.startsWith('INSERT INTO player_item')) itemWrites.push(params);
                if (sql.startsWith('INSERT INTO player_equip')) equipWrites.push(params);
                if (sql.startsWith('SELECT state, time_arrive FROM world_battel')) {
                    return [[{ state: 'marching', time_arrive: 100 }], []];
                }
                if (sql.startsWith('SELECT m.id_member')) {
                    return [[{
                        id_member: 1,
                        id_player: 2,
                        id_city: 3,
                        id_hero: 11,
                        side: 1,
                        task: 1,
                        return_duration: 60,
                        hero_name: 'Hero',
                        hero_avatar: 1,
                        hero_power: 50,
                        hero_power_max: 100,
                        point_b: 0,
                        point_b_plus: 0,
                        player_name: 'Player',
                        player_id_guild: 4,
                        city_flag: '0',
                        city_x: 1,
                        city_y: 1,
                        f_1_type: 6, f_1_num: 20000, f_2_type: 0, f_2_num: 0, f_3_type: 0, f_3_num: 0,
                        b_1_type: 0, b_1_num: 0, b_2_type: 0, b_2_num: 0, b_3_type: 0, b_3_num: 0
                    }], []];
                }
                if (sql.startsWith('SELECT * FROM hero_army')) {
                    return [[{
                        id_hero: 11,
                        f_1_type: 6, f_1_num: 20000, f_2_type: 0, f_2_num: 0, f_3_type: 0, f_3_num: 0,
                        b_1_type: 0, b_1_num: 0, b_2_type: 0, b_2_num: 0, b_3_type: 0, b_3_num: 0
                    }], []];
                }
                if (sql.startsWith('SELECT * FROM player_equip')) return [[], []];
                if (sql.startsWith('SELECT id_player, medicine')) return [[], []];
                if (sql.startsWith('SELECT id_hero, medal_den')) return [[], []];
                if (sql.startsWith('SELECT id_city, id_player, food')) {
                    return [[{ id_city: 3, id_player: 2, food: 0, wood: 0, stone: 0, metal: 0, coin: 0 }], []];
                }
                return [{ affectedRows: 1, insertId: 1 }, []];
            }
        };

        const context = {
            battelId: 101,
            idHero: 11,
            battleStartTime: 40,
            battleArrivalTime: 100,
            returnDuration: 60,
            unitType,
            unitLvl,
            unitTitle: 'Target',
            attackTask: 1,
            xCoord: 15,
            yCoord: 25,
            targetCityRows: [],
            isBarray: false,
            unitTypeData: {},
            baseUnit: null,
            hero: { id_hero: 11, name: 'Hero' },
            player: { id_player: 2, name: 'Player', id_guild: 0 },
            city: { id_city: 3, x: 1, y: 1 }
        };

        const dependencies = {
            getDb: async () => ({ getConnection: async () => connection }),
            worldUnits: new Map(),
            unitData: {},
            unitArmy: { [unitType]: { [unitLvl]: { 1: 1, army_a: 1 } } },
            prizeData: { Win: prizes },
            equipPowerMap: equipmentPowerMap,
            liveServerConfig: { wounded_recovery_rate: 0, event_multipliers: {} },
            SOL_STATS: solStats,
            slots,
            aggregateMemberArmy: () => ({
                army: { f_1_type: 6, f_1_num: 2 },
                remainingArmy: { f_1: 2 },
                troops: { 6: { pre: 2, cur: 2 } }
            }),
            reportArmySlots: () => ({ attackerSlots: {}, defenderSlots: {} }),
            creditCityResources: async (db, idCity, resources) => resources,
            advanceWorldUnitLevel: async () => 1,
            allocateProportionally: () => ({ 1: 1 }),
            calculateArmyPower: () => 100,
            calculateArmyCapacity: () => 100,
            calculateKillBasedRewards,
            distributeMemberTroopLosses: () => ({ 1: { f_1: 0 } }),
            didBattleSideWin: (isVictory, side) => side === 1 ? isVictory : !isVictory,
            calculateArmyCasualties,
            simulateTacticalCombat(...args) {
                const result = simulateTacticalCombat(...args);
                result.attackersWon = attackersWon;
                return result;
            },
            hasActiveBattleAt: async () => false,
            randomInteger: (min, max) => Math.max(
                min,
                Math.min(max, randomResults.length ? randomResults.shift() : max)
            ),
            publishBroadcast() {},
            publishToPlayer() {},
            saveWsBattleReport: async (db, report) => savedReports.push(report)
        };

        return { context, dependencies, itemWrites, equipWrites, savedReports };
    };

    // 1. Camp with 0 win_rate: MUST NOT drop any fallback items (no necklace, no bread, no random prize)
    const campMiss = makeHarness(71, 10, [
        { unitType: 71, lvl: 10, prize: 'spartans_relics', amount_min: 1, amount_max: 1, win_rate: 0 }
    ]);
    await resolveBattle(campMiss.context, campMiss.dependencies);
    assert.equal(campMiss.itemWrites.length, 0, 'Camp with failed prize roll must award 0 items');

    // 2. Camp with 10000 win_rate: awards exact item and amount
    const campHit = makeHarness(71, 10, [
        { unitType: 71, lvl: 10, prize: 'spartans_relics', amount_min: 2, amount_max: 2, win_rate: 10000 }
    ]);
    await resolveBattle(campHit.context, campHit.dependencies);
    assert.equal(campHit.itemWrites.length, 1);
    assert.equal(campHit.itemWrites[0][1], 'spartans_relics');
    assert.equal(campHit.itemWrites[0][2], 2);

    // 3. Wilderness with 0 win_rate: MUST NOT drop fallback necklace
    const wildMiss = makeHarness(44, 1, [
        { unitType: 44, lvl: 1, prize: 'necklace_1', amount_min: 1, amount_max: 1, win_rate: 0 }
    ]);
    await resolveBattle(wildMiss.context, wildMiss.dependencies);
    assert.equal(wildMiss.itemWrites.length, 0, 'Wilderness with failed roll must award 0 items');

    // Same-round rewards must match both the unit ID and the exact round.
    const exactRound = makeHarness(71, 10, [
        { unitType: 71, lvl: 10, prize: 'round_10_item', amount_min: 1, amount_max: 1, win_rate: 10000 },
        { unitType: 71, lvl: 11, prize: 'round_11_item', amount_min: 1, amount_max: 1, win_rate: 10000 },
        { unitType: 72, lvl: 10, prize: 'other_camp_item', amount_min: 1, amount_max: 1, win_rate: 10000 }
    ]);
    await resolveBattle(exactRound.context, exactRound.dependencies);
    assert.deepEqual(exactRound.itemWrites.map(params => params[1]), ['round_10_item']);

    // Defeated attackers receive no victory spoils or table prize drops.
    const defeatPrize = makeHarness(71, 10, [
        { unitType: 71, lvl: 10, prize: 'round_10_item', amount_min: 1, amount_max: 1, win_rate: 10000 },
        { unitType: 71, lvl: 11, prize: 'round_11_item', amount_min: 1, amount_max: 1, win_rate: 10000 },
        { unitType: 72, lvl: 10, prize: 'other_camp_item', amount_min: 1, amount_max: 1, win_rate: 10000 }
    ], [], false);
    await resolveBattle(defeatPrize.context, defeatPrize.dependencies);
    assert.equal(defeatPrize.itemWrites.length, 0, 'Defeat must yield 0 victory prizes');
    assert.equal(defeatPrize.savedReports[0].itemLoot.length, 0);

    // Direct hero equipment drop has been removed in accordance with authentic game rules:
    // Equipment is obtained only through crafting and exchange, not direct drops.
    for (const testCase of [
        { unitType: 70, unitLvl: 3 },
        { unitType: 97, unitLvl: 3 },
        { unitType: 150, unitLvl: 15 }
    ]) {
        const noGearDrop = makeHarness(testCase.unitType, testCase.unitLvl, []);
        await resolveBattle(noGearDrop.context, noGearDrop.dependencies);
        assert.equal(noGearDrop.equipWrites.length, 0, 'No direct equipment drop allowed');
    }
});

test('castle and kingdom armies scale correctly while defense remains opt-in', async () => {
    const makeHarness = (unitType, unitLvl, options = {}) => {
        let captured = null;
        let capturedJoinedDefenders = [];
        let autoDefenderQueries = 0;
        const dominanceWrites = [];
        const attackingMember = {
            id_member: 1, id_player: 2, id_city: 3, id_hero: 11, side: 1, task: 1,
            return_duration: 60, hero_name: 'Hero', hero_avatar: 1, hero_power: 50,
            hero_power_max: 100, point_b: 0, point_b_plus: 0, player_name: 'Player',
            player_id_guild: 4, city_flag: '0', player_prestige: 100, city_name: 'Home',
            city_x: 1, city_y: 1
        };
        const defenderMembers = options.defenderMembers || [];
        const armies = [
            { id_hero: 11, f_1_type: 6, f_1_num: 10 },
            ...defenderMembers.map(member => ({ id_hero: member.id_hero, f_1_type: 6, f_1_num: 10 }))
        ];
        const connection = {
            async beginTransaction() {},
            async commit() {},
            async rollback() {},
            release() {},
            async query(sql, params = []) {
                if (sql.startsWith('SELECT state, time_arrive FROM world_battel')) {
                    return [[{ state: 'marching', time_arrive: 100 }], []];
                }
                if (sql.startsWith('SELECT id_guild FROM guild_dominance')) {
                    return [[{ id_guild: options.dominantGuildId || 0 }], []];
                }
                if (sql.startsWith('SELECT m.id_member')) {
                    return [[attackingMember, ...defenderMembers], []];
                }
                if (sql.startsWith('SELECT h.id_hero')) {
                    autoDefenderQueries++;
                    return [[], []];
                }
                if (sql.startsWith('SELECT * FROM hero_army')) return [armies, []];
                if (sql.startsWith('SELECT * FROM player_equip')) return [[], []];
                if (sql.startsWith('SELECT id_player, medicine')) return [[], []];
                if (sql.startsWith('SELECT id_hero, medal_den')) return [[], []];
                if (sql.startsWith('SELECT f_1_type, f_1_num')) return [[{ f_1_type: 6, f_1_num: 10 }], []];
                if (sql.startsWith('SELECT id_city, id_player, food')) return [[{ id_city: 3, id_player: 2, food: 100, wood: 100, stone: 100, metal: 100, coin: 100 }], []];
                if (sql.startsWith('SELECT COALESCE(MAX(lvl), 0) AS lvl FROM city_building')) return [[{ lvl: 10 }], []];
                if (sql.startsWith('SELECT COUNT(*) as cnt FROM city_barray')) return [[{ cnt: 0 }], []];
                if (sql.startsWith('INSERT INTO guild_dominance')) dominanceWrites.push(params);
                return [{}, []];
            }
        };
        const context = {
            battelId: 101, idHero: 11, battleStartTime: 40, battleArrivalTime: 100, returnDuration: 60,
            unitType, unitLvl, unitTitle: 'Target', attackTask: 1, xCoord: 15, yCoord: 25,
            targetCityRows: [], isBarray: false, unitTypeData: {}, baseUnit: null,
            hero: { id_hero: 11, name: 'Hero' }, player: { id_player: 2, name: 'Player', id_guild: 4 },
            city: { id_city: 3, x: 1, y: 1 }
        };
        const dependencies = {
            getDb: async () => ({ getConnection: async () => connection }),
            worldUnits: new Map(), unitData: {}, unitArmy: {}, prizeData: { Win: [] },
            equipPowerMap: equipmentPowerMap,
            liveServerConfig: { wounded_recovery_rate: 0, event_multipliers: {} },
            SOL_STATS: solStats, slots,
            aggregateMemberArmy: () => ({ army: { f_1_type: 6, f_1_num: 10 }, remainingArmy: { f_1: 10 }, troops: { 6: { pre: 10, cur: 10 } } }),
            reportArmySlots: () => ({ attackerSlots: {}, defenderSlots: {} }),
            creditCityResources: async (db, idCity, resources) => resources,
            advanceWorldUnitLevel: async () => 1, allocateProportionally: () => ({ 1: 1 }),
            calculateArmyPower: () => 100, calculateArmyCapacity: () => 100,
            calculateKillBasedRewards, distributeMemberTroopLosses: () => ({ 1: { f_1: 0 } }),
            didBattleSideWin: () => true, calculateArmyCasualties,
            simulateTacticalCombat(...args) {
                captured = args[1];
                capturedJoinedDefenders = args[2].defendingMembers;
                const res = simulateTacticalCombat(...args);
                res.attackersWon = options.attackersWon !== false;
                return res;
            },
            hasActiveBattleAt: async () => false, randomInteger: () => 1,
            publishBroadcast() {}, publishToPlayer() {}, saveWsBattleReport: async () => {}
        };
        return {
            context,
            dependencies,
            getCaptured: () => captured,
            getJoinedDefenders: () => capturedJoinedDefenders,
            getAutoDefenderQueryCount: () => autoDefenderQueries,
            getDominanceWrites: () => dominanceWrites
        };
    };

    const smallCastle = makeHarness(184, 1);
    await resolveBattle(smallCastle.context, smallCastle.dependencies);
    assert.equal(smallCastle.getCaptured()[1], 40000);
    assert.equal(smallCastle.getCaptured()[2], 20000);
    assert.equal(smallCastle.getCaptured()[4], 30000);

    const largeKingdom = makeHarness(182, 1);
    await resolveBattle(largeKingdom.context, largeKingdom.dependencies);
    assert.equal(largeKingdom.getCaptured()[1], 400000);
    assert.equal(largeKingdom.getCaptured()[2], 250000);
    assert.equal(largeKingdom.getCaptured()[3], 200000);
    assert.equal(largeKingdom.getCaptured()[4], 300000);
    assert.equal(largeKingdom.getCaptured()[5], 150000);
    assert.equal(largeKingdom.getCaptured()[6], 100000);

    const ownedCastle = makeHarness(184, 1, { dominantGuildId: 9 });
    await resolveBattle(ownedCastle.context, ownedCastle.dependencies);
    assert.equal(ownedCastle.getAutoDefenderQueryCount(), 0, 'Defence must be opt-in, not automatic');
    assert.equal(ownedCastle.getDominanceWrites()[0][4], 4, 'Attacker guild takes control after victory');

    const defender = {
        id_member: 2, id_player: 12, id_city: 13, id_hero: 14, side: 0, task: 1,
        return_duration: 60, hero_name: 'Defender', hero_avatar: 2, hero_power: 50,
        hero_power_max: 100, point_b: 0, point_b_plus: 0, player_name: 'Guild member',
        player_id_guild: 9, city_flag: '0', player_prestige: 100, city_name: 'Guild City',
        city_x: 2, city_y: 2
    };
    const defendedCastle = makeHarness(184, 1, {
        dominantGuildId: 9,
        attackersWon: false,
        defenderMembers: [defender]
    });
    await resolveBattle(defendedCastle.context, defendedCastle.dependencies);
    assert.equal(defendedCastle.getJoinedDefenders().length, 1, 'Joined guild defenders participate in combat');
    assert.equal(defendedCastle.getDominanceWrites().length, 0, 'A successful defence preserves the current owner');
});

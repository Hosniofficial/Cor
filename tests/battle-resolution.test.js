const test = require('node:test');
const assert = require('node:assert/strict');
const equipmentPowerData = require('../caesar-assets/api/data/equipment-power.json');
const {
    applyCityConquest,
    calculateCityConquestLoyalty,
    enlistCityGarrisonDefenders,
    resolveBattle
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
        unitType: 1,
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
        unitArmy: { 1: { 1: { 1: 1, army_a: 1 } } },
        prizeData: { Win: [] },
        equipPowerMap: new Map([['blue_sword_1', equippedSword]]),
        liveServerConfig: { wounded_recovery_rate: 0, event_multipliers: {} },
        SOL_STATS: solStats,
        slots,
        aggregateMemberArmy,
        reportArmySlots,
        creditCityResources: async (db, idCity, resources) => resources,
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
    assert.deepEqual(receivedEquipmentBonuses, [equippedSword]);
    assert.ok(savedReports[0].roundsLog.some(line => /معدات بطل المهاجم/.test(line)));
    assert.ok(savedReports[0].roundsLog.some(line => /الجولة/.test(line)));
    assert.deepEqual(savedReports[0].killsByType, { 1: 1, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0, 7: 0 });
    assert.equal(savedReports[0].expGained, 24);
    assert.equal(savedReports[0].honorGained, 102);
    assert.match(savedReports[0].opponentName, /^القيصر /);
    assert.doesNotMatch(savedReports[0].opponentName, /مستوى/);
    const heroRewardUpdates = queries.filter(sql => sql.startsWith('UPDATE city_hero SET exp ='));
    assert.ok(heroRewardUpdates.length > 0);
    assert.ok(heroRewardUpdates.every(sql => /power = LEAST\(power_max, power \+ 5\)/.test(sql)));
    assert.ok(queries.includes('hero-exp:24'));
    assert.ok(queries.some(sql => sql.startsWith('INSERT INTO hero_army')));
    assert.ok(queries.some(sql => sql.startsWith('UPDATE player SET honor =')));
    assert.ok(queries.some(sql => sql.startsWith('UPDATE guild SET honor =')));
    assert.ok(!queries.some(sql => /UPDATE (player|guild) SET .*prestige/i.test(sql)));
    assert.deepEqual(colonizationInserts, [[3, 2, 10, 20, 1, 1]]);
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
    dependencies.unitArmy[1][1][1] = 100000;
    await resolveBattle(context, dependencies);
    assert.equal(colonizationInserts.length, insertCountAtCapacity);
    assert.equal(colonizationBroadcastCount(), broadcastCountAtCapacity);
    const defeatedArmyWrite = heroArmyWrites[heroArmyWrites.length - 1];
    assert.deepEqual(defeatedArmyWrite.slice(2), Array(12).fill(0));
});

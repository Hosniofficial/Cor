const test = require('node:test');
const assert = require('node:assert/strict');
const { resolveBattle } = require('../caesar-shared/battle-resolution');

test('defeat outcome in battle report displays full combat casualties in slots and includes recovery summary', async () => {
    const savedReports = [];
    const connection = {
        beginTransaction: async () => {},
        commit: async () => {},
        rollback: async () => {},
        release: async () => {},
        query: async (sql, params) => {
            if (/SELECT state, time_arrive FROM world_battel/i.test(sql)) {
                return [[{ state: 'marching', time_arrive: 100 }]];
            }
            if (/SELECT m\.id_member/i.test(sql)) {
                return [[{
                    id_member: 1,
                    id_player: 100000,
                    id_city: 1,
                    id_hero: 5,
                    side: 1,
                    task: 0,
                    x_city: 300,
                    y_city: 300,
                    return_duration: 60,
                    hero_name: 'HOSNI',
                    hero_avatar: 1,
                    hero_lvl: 100,
                    hero_power: 100,
                    hero_power_max: 100,
                    point_b: 10,
                    point_b_plus: 0,
                    point_c: 10,
                    point_c_plus: 0,
                    player_name: 'HOSNI',
                    player_id_guild: 0,
                    city_flag: '0',
                    player_prestige: 1000,
                    city_name: 'City',
                    city_x: 300,
                    city_y: 300
                }]];
            }
            if (/SELECT \* FROM hero_army/i.test(sql)) {
                return [[{
                    id_hero: 5,
                    id_player: 100000,
                    f_1_type: 1, f_1_num: 100,
                    f_2_type: 0, f_2_num: 0,
                    f_3_type: 0, f_3_num: 0,
                    b_1_type: 0, b_1_num: 0,
                    b_2_type: 4, b_2_num: 10000,
                    b_3_type: 0, b_3_num: 0
                }]];
            }
            if (/SELECT \* FROM hero_equipment/i.test(sql)) {
                return [[]];
            }
            if (/SELECT id_city, id_player FROM city WHERE x =/i.test(sql)) {
                return [[]];
            }
            if (/SELECT \* FROM city_wounded/i.test(sql)) {
                return [[]];
            }
            if (/UPDATE (world_battel|world_battel_member|hero_army|city_wounded)/i.test(sql)) {
                return [{ affectedRows: 1 }];
            }
            if (/INSERT INTO (hero_army|city_wounded)/i.test(sql)) {
                return [{ affectedRows: 1 }];
            }
            return [[]];
        }
    };

    const slots = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
    const solStats = {
        1: { att: 10, def: 10, hp: 100, sp: 10, cap: 10, range: 10 },
        4: { att: 30, def: 5, hp: 50, sp: 10, cap: 10, range: 100 }
    };

    const context = {
        battelId: 999,
        idHero: 5,
        battleStartTime: 10,
        battleArrivalTime: 100,
        returnDuration: 60,
        unitType: 201, // War Statue (60% recovery rate)
        unitSubtype: 0,
        unitLvl: 1,
        unitTitle: 'تمثال كلوديوس',
        attackTask: 0,
        xCoord: 330,
        yCoord: 410,
        targetCityRows: [],
        isBarray: false,
        unitTypeData: {},
        baseUnit: {},
        hero: { id_hero: 5, name: 'HOSNI' },
        player: { id_player: 100000, name: 'HOSNI', id_guild: 0 },
        city: { id_city: 1, x: 300, y: 300 }
    };

    const dependencies = {
        getDb: async () => ({ getConnection: async () => connection }),
        worldUnits: new Map(),
        unitData: {},
        unitArmy: {
            201: {
                1: {
                    1: 1920000, army_a: 1920000,
                    4: 840000, army_d: 840000
                }
            }
        },
        prizeData: { Win: [], Defeat: [] },
        equipPowerMap: new Map(),
        liveServerConfig: { wounded_recovery_rate: 0.60 },
        SOL_STATS: solStats,
        slots,
        aggregateMemberArmy: members => {
            const troops = {};
            for (const member of members) {
                for (const slot of slots) {
                    const type = Number(member.army[`${slot}_type`]) || 0;
                    const amount = Number(member.army[`${slot}_num`]) || 0;
                    if (!type || amount <= 0) continue;
                    if (!troops[type]) troops[type] = { pre: 0, cur: 0 };
                    troops[type].pre += amount;
                    troops[type].cur += Number(member.remainingArmy && member.remainingArmy[slot]) || 0;
                }
            }
            return { army: members[0].army, remainingArmy: members[0].remainingArmy, troops };
        },
        reportArmySlots: (army, remainingArmy, defenderTroops, isVictory, injuredBySlot = {}) => {
            const attackerSlots = {};
            for (const slot of slots) {
                const type = Number(army[`${slot}_type`]) || 0;
                const pre = Number(army[`${slot}_num`]) || 0;
                const cur = Number(remainingArmy[slot]) || 0;
                attackerSlots[slot] = {
                    type,
                    pre,
                    cur,
                    injured: Math.max(0, Number(injuredBySlot[slot]) || 0)
                };
            }
            const defenderSlots = Object.fromEntries(slots.map(s => [s, { type: 0, pre: 0, cur: 0 }]));
            return { attackerSlots, defenderSlots };
        },
        creditCityResources: async () => ({}),
        advanceWorldUnitLevel: async () => 1,
        allocateProportionally: () => ({}),
        calculateArmyPower: () => 1000,
        calculateArmyCapacity: () => 10000,
        calculateKillBasedRewards: () => [{ member: { hero: { id_hero: 5 }, player: { id_player: 100000 } }, expGained: 100, honorGained: 100 }],
        distributeMemberTroopLosses: () => ({}),
        didBattleSideWin: (isVictory, side) => (isVictory ? side === 1 : side === 0),
        calculateArmyCasualties: (army, lossRate, recoveryRate, woundedRate, isVictory, combatRemainingArmy) => {
            // Hero wiped out in combat (combatRemainingArmy is 0)
            const remainingArmy = {};
            const wounded = {};
            const woundedBySlot = {};
            const totals = { fallen: 10100, recovered: 6060, permanentLoss: 1616, wounded: 2424 };
            for (const slot of slots) {
                const amount = Number(army[`${slot}_num`]) || 0;
                const type = Number(army[`${slot}_type`]) || 0;
                if (amount > 0) {
                    const recovered = Math.floor(amount * recoveryRate);
                    const unrecovered = amount - recovered;
                    const w = Math.floor(unrecovered * woundedRate);
                    remainingArmy[slot] = { type, amount: recovered };
                    woundedBySlot[slot] = w;
                    wounded[type] = w;
                } else {
                    remainingArmy[slot] = { type: 0, amount: 0 };
                    woundedBySlot[slot] = 0;
                }
            }
            return { remainingArmy, wounded, woundedBySlot, totals };
        },
        simulateTacticalCombat: () => ({
            attackersWon: false, // Defeat
            roundsCount: 3,
            livingAttackers: { 1: 0, 4: 0 },
            livingDefenders: { 1: 1800000, 4: 840000 },
            remainingAttackers: [{
                member: { id_member: 1 },
                army: { f_1: 0, f_2: 0, f_3: 0, b_1: 0, b_2: 0, b_3: 0 }
            }],
            remainingDefenders: [],
            roundsLog: ['الجولة 1: خسائر', 'الجولة 2: خسائر', 'الجولة 3: خسائر']
        }),
        hasActiveBattleAt: async () => false,
        publishBroadcast: () => {},
        publishToPlayer: () => {},
        saveWsBattleReport: async (db, report) => {
            savedReports.push(report);
        }
    };

    await resolveBattle(context, dependencies);

    assert.equal(savedReports.length, 1);
    const rep = savedReports[0];
    assert.equal(rep.isVictory, false);

    // In report slots, on defeat, cur is 0 (combat wipeout), so loss = pre - cur = 10000 (full combat loss)
    assert.equal(rep.attackerSlots.b_2.pre, 10000);
    assert.equal(rep.attackerSlots.b_2.cur, 0, 'Report slot cur must reflect combat remaining (0) on defeat');
    assert.equal(rep.attackerSlots.b_2.pre - rep.attackerSlots.b_2.cur, 10000, 'Report slot loss must match combat fallen');
    assert.equal(rep.attackerSlots.b_2.injured, 400, 'Injured must be reported');
    assert.ok(!rep.desc, 'Desc must remain clean without injected defeat recovery message');
    assert.ok(!rep.roundsLog.some(line => line.includes('عودة القوات الناجية')), 'RoundsLog must not inject defeat recovery message');
});

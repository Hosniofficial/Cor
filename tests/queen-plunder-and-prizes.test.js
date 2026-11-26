'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const {
    getMatchingUnitPrizes,
    isPrizeDropSuccessful,
    mergeItemLootEntries
} = require('../caesar-shared/battle-loot');
const { resolveBattle } = require('../caesar-shared/battle-resolution');
const {
    calculateKillBasedRewards,
    calculateArmyCasualties,
    simulateTacticalCombat
} = require('../caesar-shared/battle-party');

const root = path.join(__dirname, '..');
const assetRoots = [
    path.join(root, 'caesar-assets', 'web', 'js-3.4.113'),
    path.join(root, 'caesar-assets', 'api', 'js-3.4.113'),
    path.join(root, 'caesar-assets', 'api', 'js-0.0.24')
];
const clientSource = fs.readFileSync(
    path.join(assetRoots[0], 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

test('every prize item across Win, Sp, Plunder, and Lose exists in all itemBase.json copies and ItemLang/ar.json', () => {
    const prizes = JSON.parse(fs.readFileSync(path.join(root, 'caesar-private', 'data', 'world-unit-prizes.json'), 'utf8'));
    const arLang = JSON.parse(fs.readFileSync(path.join(assetRoots[0], 'json', 'ItemLang', 'ar.json'), 'utf8'));
    const baseBuffers = assetRoots.map(dir => fs.readFileSync(path.join(dir, 'json', 'itemBase.json')));

    for (let i = 1; i < baseBuffers.length; i++) {
        assert.equal(Buffer.compare(baseBuffers[0], baseBuffers[i]), 0, `itemBase.json matches asset root ${i}`);
    }

    const itemBase = JSON.parse(baseBuffers[0].toString('utf8'));
    for (const bucket of ['Win', 'Sp', 'Plunder', 'Lose']) {
        for (const entry of prizes[bucket] || []) {
            assert.ok(itemBase[entry.prize], `${bucket} prize "${entry.prize}" exists in itemBase.json`);
            assert.ok(itemBase[entry.prize].image, `${bucket} prize "${entry.prize}" defines image`);
            assert.ok(arLang[entry.prize] && arLang[entry.prize].name, `${bucket} prize "${entry.prize}" has Arabic name`);
        }
    }
});

test('high drop rate scale (1000) is preserved and fixed-tier special units resolve map levels cleanly', () => {
    const prizes = JSON.parse(fs.readFileSync(path.join(root, 'caesar-private', 'data', 'world-unit-prizes.json'), 'utf8'));

    assert.equal(isPrizeDropSuccessful(1000, 0.999), true);
    assert.equal(isPrizeDropSuccessful(1500, 0.999), true);
    assert.equal(isPrizeDropSuccessful(500, 0.499), true);

    const q180Win = getMatchingUnitPrizes(prizes.Win, { unitType: 180, unitLvl: 10 });
    assert.ok(q180Win.length >= 2, 'Queen 180 map level 10 resolves Win entries');

    const q181Win = getMatchingUnitPrizes(prizes.Win, { unitType: 181, unitLvl: 0 });
    assert.ok(q181Win.length >= 2, 'Queen 181 map level 0 resolves Win entries');

    const q180Sp = getMatchingUnitPrizes(prizes.Sp, { unitType: 180, unitLvl: 10 });
    assert.ok(q180Sp.some(r => r.prize === 'gift_box'), 'Queen 180 resolves Sp guild prizes');

    const c184Win = getMatchingUnitPrizes(prizes.Win, { unitType: 184, unitLvl: 8 });
    assert.ok(c184Win.length >= 2, 'Castle 184 map level 8 resolves Win entries');

    const campStrictMiss = getMatchingUnitPrizes(
        [{ unitType: 71, lvl: 1, prize: 'food_a' }],
        { unitType: 71, unitLvl: 10 }
    );
    assert.equal(campStrictMiss.length, 0, 'Regular camps never fall back across rounds');

    const merged = mergeItemLootEntries([
        { prize: 'bronze_bar', amount: 2 },
        { Item: 'bronze_bar', amount: 3 },
        { prize: 't_map', amount: 1 }
    ]);
    assert.deepEqual(merged, [
        { prize: 'bronze_bar', amount: 5 },
        { prize: 't_map', amount: 1 }
    ]);
});

test('resolveBattle distributes Sp prizes to all guild members on Queen victory and grants 0 prizes on defeat', async () => {
    const solStats = { 6: { att: 50, def: 50, vit: 100 } };
    const slots = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];

    const createBattleMock = ({ unitType, unitLvl, attackersWon, prizeData }) => {
        const itemWrites = [];
        const msgDiffWrites = [];
        const broadcasts = [];
        const playerMessages = [];
        const finishedPayloads = [];
        const reports = [];

        const connection = {
            async beginTransaction() {},
            async commit() {},
            async rollback() {},
            release() {},
            async query(sql, params = []) {
                if (sql.startsWith('INSERT INTO player_item')) itemWrites.push(params);
                if (sql.startsWith('INSERT INTO msg_diff')) msgDiffWrites.push(params);
                if (sql.startsWith('SELECT state, time_arrive FROM world_battel')) {
                    return [[{ state: 'marching', time_arrive: 100 }], []];
                }
                if (sql.startsWith('SELECT m.id_member')) {
                    return [[{
                        id_member: 1, id_player: 2, id_city: 3, id_hero: 11, side: 1, task: 1,
                        return_duration: 60, hero_name: 'ليونيداس', hero_avatar: 1,
                        hero_power: 50, hero_power_max: 100, point_b: 0, point_b_plus: 0,
                        player_name: 'القائد', player_id_guild: 4, city_flag: 'ROM',
                        player_prestige: 100, city_name: 'روما', city_x: 1, city_y: 1,
                        f_1_type: 6, f_1_num: 10000, f_2_type: 0, f_2_num: 0, f_3_type: 0, f_3_num: 0,
                        b_1_type: 0, b_1_num: 0, b_2_type: 0, b_2_num: 0, b_3_type: 0, b_3_num: 0
                    }], []];
                }
                if (sql.startsWith('SELECT * FROM hero_army')) {
                    return [[{ id_hero: 11, f_1_type: 6, f_1_num: 10000 }], []];
                }
                if (sql.startsWith('SELECT * FROM player_equip')) return [[], []];
                if (sql.startsWith('SELECT id_player, medicine')) return [[], []];
                if (sql.startsWith('SELECT id_hero, medal_den')) return [[], []];
                if (sql.startsWith('SELECT id_player FROM guild_member')) {
                    return [[{ id_player: 2 }, { id_player: 99 }], []];
                }
                if (sql.startsWith('SELECT name FROM guild WHERE id_guild = ? LIMIT 1')) {
                    return [[{ name: 'نسور روما' }], []];
                }
                if (sql.startsWith('SELECT id_city, id_player, food')) {
                    return [[{ id_city: 3, id_player: 2, food: 0, wood: 0, stone: 0, metal: 0, coin: 0 }], []];
                }
                if (sql.startsWith('SELECT')) return [[], []];
                return [{ affectedRows: 1, insertId: 1 }, []];
            }
        };

        const context = {
            battelId: 501,
            idHero: 11,
            battleStartTime: 40,
            battleArrivalTime: 100,
            returnDuration: 60,
            unitType,
            unitLvl,
            unitTitle: 'الملكة الصغرى',
            attackTask: 1,
            xCoord: 200,
            yCoord: 150,
            targetCityRows: [],
            isBarray: false,
            unitTypeData: {},
            baseUnit: null,
            hero: { id_hero: 11, name: 'ليونيداس' },
            player: { id_player: 2, name: 'القائد', id_guild: 4, guild_name: 'نسور روما', city_flag: 'ROM' },
            city: { id_city: 3, x: 1, y: 1 }
        };

        const deps = {
            getDb: async () => ({ getConnection: async () => connection }),
            worldUnits: new Map(),
            unitData: {},
            unitArmy: { [unitType]: { [unitLvl]: { 1: 1 } } },
            prizeData,
            equipPowerMap: new Map(),
            liveServerConfig: { wounded_recovery_rate: 0, event_multipliers: {} },
            SOL_STATS: solStats,
            slots,
            aggregateMemberArmy: () => ({
                army: { f_1_type: 6, f_1_num: 100 },
                remainingArmy: { f_1: 100 },
                troops: { 6: { pre: 100, cur: 100 } }
            }),
            reportArmySlots: () => ({ attackerSlots: {}, defenderSlots: {} }),
            creditCityResources: async () => ({ food: 0, wood: 0, stone: 0, metal: 0, coin: 0 }),
            advanceWorldUnitLevel: async () => 1,
            allocateProportionally: () => ({ 1: 1 }),
            calculateArmyPower: () => 100,
            calculateArmyCapacity: () => 100,
            calculateKillBasedRewards,
            distributeMemberTroopLosses: () => ({ 1: { f_1: 0 } }),
            didBattleSideWin: (won, side) => side === 1 ? won : !won,
            calculateArmyCasualties,
            simulateTacticalCombat(...args) {
                const res = simulateTacticalCombat(...args);
                res.attackersWon = attackersWon;
                return res;
            },
            hasActiveBattleAt: async () => false,
            randomInteger: (min, max) => min,
            publishBroadcast(msg) { broadcasts.push(JSON.parse(msg)); },
            publishToPlayer(idPlayer, msg) {
                const parsed = JSON.parse(msg);
                playerMessages.push({ idPlayer, msg: parsed });
                if (parsed.classPath === 'Battel.Finished') finishedPayloads.push(parsed);
            },
            saveWsBattleReport: async (db, report) => reports.push(report)
        };

        return { context, deps, itemWrites, msgDiffWrites, broadcasts, playerMessages, finishedPayloads, reports };
    };

    // 1. Queen 180 Victory (with map tile level 10): Win items + Sp items for BOTH guild members (2 and 99)
    const winScenario = createBattleMock({
        unitType: 180,
        unitLvl: 10,
        attackersWon: true,
        prizeData: {
            Win: [{ unitType: 180, lvl: 1, prize: 'exp_hero_8', amount_min: 2, amount_max: 2, win_rate: 1000 }],
            Sp: [{ unitType: 180, lvl: 1, prize: 'gift_box', amount_min: 1, amount_max: 1, win_rate: 1000 }],
            Lose: [{ unitType: 180, lvl: 1, prize: 'bronze_bar', amount_min: 1, amount_max: 1, win_rate: 1000 }]
        }
    });
    await resolveBattle(winScenario.context, winScenario.deps);

    assert.ok(winScenario.itemWrites.some(p => p[0] === 2 && p[1] === 'exp_hero_8' && p[2] === 2), 'Attacker gets Win prize');
    assert.ok(winScenario.itemWrites.some(p => p[0] === 2 && p[1] === 'gift_box' && p[2] === 1), 'Leader gets Sp gift');
    assert.ok(winScenario.itemWrites.some(p => p[0] === 99 && p[1] === 'gift_box' && p[2] === 1), 'Non-combatant guildmate gets Sp gift');
    assert.equal(winScenario.msgDiffWrites.length, 2, 'msg_diff sent to each guild member');
    const mailBody = JSON.parse(winScenario.msgDiffWrites[0][2]);
    assert.equal(mailBody.type, 'sp_prize');
    assert.deepEqual(mailBody.items, [{ Item: 'gift_box', amount: 1 }]);
    assert.ok(winScenario.playerMessages.some(m => m.idPlayer === 99 && m.msg.classPath === 'Chat.QueenPrizeSent'));
    assert.ok(winScenario.broadcasts.some(b => b.classPath === 'Guild.PrizeSent' && b.GuildName === 'نسور روما'));

    // 2. Queen 180 Defeat: defeated attackers receive 0 item drops (even when Lose entries exist in table)
    const defeatScenario = createBattleMock({
        unitType: 180,
        unitLvl: 10,
        attackersWon: false,
        prizeData: {
            Win: [{ unitType: 180, lvl: 1, prize: 'exp_hero_8', amount_min: 2, amount_max: 2, win_rate: 1000 }],
            Sp: [{ unitType: 180, lvl: 1, prize: 'gift_box', amount_min: 1, amount_max: 1, win_rate: 1000 }],
            Lose: [{ unitType: 180, lvl: 1, prize: 'bronze_bar', amount_min: 3, amount_max: 3, win_rate: 1000 }]
        }
    });
    await resolveBattle(defeatScenario.context, defeatScenario.deps);

    assert.equal(defeatScenario.itemWrites.length, 0, 'Defeated attacker receives 0 item writes');
    assert.deepEqual(defeatScenario.finishedPayloads[0].ItemPrize, []);
    assert.deepEqual(defeatScenario.reports[0].itemLoot, []);
    assert.equal(defeatScenario.msgDiffWrites.length, 0, 'No Sp mail on defeat');
});

test('client prize fetch, formatting helpers, and harvest message render all buckets and Plunder items accurately', () => {
    const getFn = (sig) => {
        const idx = clientSource.indexOf(sig);
        assert.ok(idx >= 0, `Client contains ${sig}`);
        return idx;
    };

    // 1. WorldUnit.prize block populates Win/Lose/Sp/Plunder and resolves Queen fixed lvl fallback
    const wuStart = getFn('WorldUnit.prize.PrizFor = {');
    const wuEnd = getFn('Caesar.World.Map.posX = function');
    let capturedAjax = null;
    const wuContext = {
        API_URL: 'http://localhost',
        Caesar: { Config: { AuthToken: 'test-token', idServer: 1 } },
        WorldUnit: { getWorldUnit: () => ({ x: 10, y: 20, ut: 180, l: 10 }), prize: {} },
        Matrial: { image: k => `images/items/${k}.jpg` },
        $: { ajax: opts => { capturedAjax = opts; } }
    };
    vm.runInNewContext(clientSource.slice(wuStart, wuEnd), wuContext);
    wuContext.WorldUnit.prize.fetchUnitPrizes(180);
    capturedAjax.success({
        state: 'ok',
        unitType: 180,
        prizes: [{ unitType: 180, lvl: 1, prize: 'exp_hero_8', amount_min: 1, amount_max: 1 }],
        buckets: {
            Win: [{ unitType: 180, lvl: 1, prize: 'exp_hero_8', amount_min: 1, amount_max: 1 }],
            Plunder: [{ unitType: 180, lvl: 1, prize: 'bronze_bar', amount_min: 1, amount_max: 1 }],
            Sp: [{ unitType: 180, lvl: 1, prize: 'gift_box', amount_min: 1, amount_max: 1 }],
            Lose: [{ unitType: 180, lvl: 1, prize: 'coin_a', amount_min: 1, amount_max: 1 }]
        }
    });
    assert.equal(wuContext.WorldUnit.prize.getUnitPrize(180, 10, 'Plunder')[0].prize, 'bronze_bar');
    assert.equal(wuContext.WorldUnit.prize.getUnitPrize(180, 10, 'Sp')[0].prize, 'gift_box');
    assert.equal(wuContext.WorldUnit.prize.getUnitPrize(180, 10, 'Lose').length, 0);

    // 2. Harvest message helper + sp_prize renderer
    const helperStart = getFn('function formatHarvestResourcesText(harvested)');
    const helperEnd = getFn('$(document).on("click", "#PLUNDE_PRIZE"');
    const helperCtx = {
        getArabicNumbers: n => String(n),
        Matrial: {
            prizeToString: list => list.map(p => `x ${p.amount} ${p.Item}`).join(' , '),
            getMatrialName: k => k,
            image: k => `images/items/${k}.jpg`
        },
        Caesar: {}
    };
    vm.runInNewContext(clientSource.slice(helperStart, helperEnd), helperCtx);
    const msg = helperCtx.buildHarvestSuccessMessage({
        PrizeList: [{ Item: 'bronze_bar', amount: 2 }],
        harvested: { food: 500000, wood: 0, stone: 0, metal: 0, coin: 50000 }
    });
    assert.match(msg, /bronze_bar/);
    assert.match(msg, /500000 الغذاء/);
    assert.ok(!msg.includes('تم جمع غنيمة المملكة'));
});

test('ItemLang/ar.json preserves bag table metadata and Matrial.listOf + Caesar.Item.ItemBox display player items', () => {
    const validTables = new Set([
        'matrial_main',
        'matrial_acce',
        'matrial_product',
        'matrial_box',
        'matrial_luxury',
        'matrial_flags'
    ]);
    const arBuffers = assetRoots.map(dir => fs.readFileSync(path.join(dir, 'json', 'ItemLang', 'ar.json')));
    for (let i = 1; i < arBuffers.length; i++) {
        assert.equal(Buffer.compare(arBuffers[0], arBuffers[i]), 0, `ItemLang/ar.json identical at asset root ${i}`);
    }
    const items = JSON.parse(arBuffers[0].toString('utf8'));
    for (const [key, item] of Object.entries(items)) {
        assert.ok(validTables.has(item.table), `${key} must keep valid bag table (got ${item.table})`);
        assert.ok(typeof item.image === 'string' && item.image.length > 0, `${key} must keep image`);
        assert.ok(typeof item.use === 'string' && item.use.length > 0, `${key} must keep use mode`);
    }

    // Verify Matrial.listOf + Caesar.Item.ItemBox populate owned items across tabs when player_item lacks .table
    const matrialStart = clientSource.indexOf('var Matrial = {');
    const matrialEnd = clientSource.indexOf('$(document).on("click", "#buyNewItem"', matrialStart);
    const itemBoxStart = clientSource.indexOf('Caesar.Item.ItemBox = function');
    const itemBoxEnd = clientSource.indexOf('Caesar.Item.ItemMallBox = function', itemBoxStart);
    assert.ok(matrialStart > 0 && matrialEnd > matrialStart);
    assert.ok(itemBoxStart > 0 && itemBoxEnd > itemBoxStart);

    const clonedItems = JSON.parse(JSON.stringify(items));
    clonedItems.bronze_horn.playerAmount = 5;
    clonedItems.bread.playerAmount = 13;
    clonedItems.hidden_box.playerAmount = 2;

    const noopEl = () => ({
        attr: () => noopEl(),
        toggle: () => noopEl(),
        toggleClass: () => noopEl(),
        text: () => noopEl(),
        html: () => noopEl(),
        each: () => noopEl(),
        find: () => noopEl()
    });
    const $ = () => noopEl();
    const ctx = {
        $,
        matrial_player: {},
        Translate: { Button: { MenuList: { Buy: { ar: 'شراء' } } } },
        UserLag: { language: 'ar' },
        getArabicNumbers: n => String(n),
        updateDialogPager: () => {},
        Caesar: {
            DPlayer: { Items: {} },
            BaseData: { Items: clonedItems },
            Item: {}
        }
    };
    vm.runInNewContext(
        clientSource.slice(matrialStart, matrialEnd) + '\n' + clientSource.slice(itemBoxStart, itemBoxEnd),
        ctx
    );

    const mainHtml = ctx.Caesar.Item.ItemBox('matrial_main');
    const acceHtml = ctx.Caesar.Item.ItemBox('matrial_acce');
    const boxHtml = ctx.Caesar.Item.ItemBox('matrial_box');
    assert.match(mainHtml, /matrial_type="bronze_horn"/);
    assert.match(acceHtml, /matrial_type="bread"/);
    assert.match(boxHtml, /matrial_type="hidden_box"/);
});

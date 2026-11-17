const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('battle reports hide non-wilderness resources and replace legacy garrison names', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf('function getReportContent(detail, data_obj, offset) {');
    const end = clientSource.indexOf('\n}\n', start);
    assert.ok(start >= 0 && end > start, 'battle report renderer should exist');

    const context = {
        ID_PLAYER: 1,
        SERVER_ID: 1,
        Caesar: {
            DPlayer: { Player: { id_player: 1 } },
            BaseData: { HeroAvatar: [] }
        },
        Matrial: { image: () => '' },
        army_icon: [],
        checkWinner: () => true,
        changeTimeFormat: value => `${value}s`,
        getArabicNumbers: value => String(value),
        getBattleTroopLosses: (pre, post, dead, loss) =>
            Number(loss ?? dead ?? Number(pre) - Number(post)),
        getBattleTroopLossTitle: injured =>
            Number(injured) > 0 ? `الخسائر (إصابات: ${injured})` : 'الخسائر',
        console: { log() {} }
    };
    vm.runInNewContext(clientSource.slice(start, end + 2), context);

    const detail = {
        encId: '5',
        general_data: { p_name: 'Player', task: 0, lvl: 2, round_num: 1, side_win: 1 },
        heros: [
            { side: 1, h_name: 'ترابان', id_player: 1, avatar: 1, xp: 0 },
            { side: 0, h_name: 'حامية الأعداء [مستوى 2]', id_player: 0 }
        ],
        prize: {
            resource: { food: 7000, wood: 7000, stone: 7000, metal: 7000, coin: 14000 },
            matrial: [],
            honor: 0
        }
    };
    const campReport = context.getReportContent(detail, {
        x_coord: 37,
        y_coord: 92,
        target_type: 70,
        time_stamp: '2026-11-12 13:36:30'
    }, 0);

    assert.doesNotMatch(campReport, /class="resource-row"/);
    assert.match(campReport, /القيصر /);
    assert.doesNotMatch(campReport, /حامية الأعداء \[مستوى 2\]/);

    const wildernessReport = context.getReportContent(detail, {
        x_coord: 37,
        y_coord: 92,
        target_type: 30,
        time_stamp: '2026-11-12 13:36:30'
    }, 0);
    assert.match(wildernessReport, /class="resource-row"/);
});

test('battle report shows battle duration and expected hero return time when present', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf('function getReportContent(detail, data_obj, offset) {');
    const end = clientSource.indexOf('\n}\n', start);
    const context = {
        ID_PLAYER: 1,
        SERVER_ID: 1,
        Caesar: {
            DPlayer: { Player: { id_player: 1 } },
            BaseData: { HeroAvatar: [] }
        },
        Matrial: { image: () => '' },
        army_icon: [],
        checkWinner: () => true,
        changeTimeFormat: value => `${value}s`,
        getArabicNumbers: value => String(value),
        getBattleTroopLosses: (pre, post, dead, loss) =>
            Number(loss ?? dead ?? Number(pre) - Number(post)),
        getBattleTroopLossTitle: injured =>
            Number(injured) > 0 ? `الخسائر (إصابات: ${injured})` : 'الخسائر',
        console: { log() {} }
    };
    vm.runInNewContext(clientSource.slice(start, end + 2), context);

    const report = context.getReportContent({
        general_data: { p_name: 'Player', task: 0, lvl: 2, round_num: 1, side_win: 1 },
        heros: [{ side: 1, h_name: 'Hero', id_player: 1, avatar: 1, xp: 0 }],
        prize: { resource: {}, matrial: [], honor: 0 },
        battle_data: {
            battle_duration_sec: 9,
            return_duration_sec: 60,
            time_back: 1794738851
        }
    }, { x_coord: 37, y_coord: 92, target_type: 70, time_stamp: '2026-11-12 13:36:30' }, 0);

    assert.match(report, /مدة المعركة: 9s/);
    assert.match(report, /مدة العودة: 60s/);
    assert.match(report, /موعد عودة البطل المتوقع:/);
});

test('battle report troop counts display recorded deaths, not remaining troops', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf('function getBattleTroopLosses(pre, post, dead, loss) {');
    const end = clientSource.indexOf('\n}\n', start);
    assert.ok(start >= 0 && end > start, 'battle troop loss helper should exist');

    const context = {};
    vm.runInNewContext(clientSource.slice(start, end + 2), context);

    assert.equal(context.getBattleTroopLosses('100', '80', '7'), 7);
    assert.equal(context.getBattleTroopLosses('100', '80', undefined), 20);
    assert.equal(context.getBattleTroopLosses('100', '120', '9'), 9);
    assert.equal(context.getBattleTroopLosses('100', '80', '7', '5'), 5);
});

test('battle report shows original losses and own-side injuries separately', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const lossStart = clientSource.indexOf('function getBattleTroopLosses(pre, post, dead, loss) {');
    const lossEnd = clientSource.indexOf('\n}\n', lossStart);
    const titleStart = clientSource.indexOf('function getBattleTroopLossTitle(injured) {');
    const titleEnd = clientSource.indexOf('\n}\n', titleStart);
    const reportStart = clientSource.indexOf('function getReportContent(detail, data_obj, offset) {');
    const reportEnd = clientSource.indexOf('\n}\n', reportStart);
    assert.ok(lossStart >= 0 && lossEnd > lossStart);
    assert.ok(titleStart >= 0 && titleEnd > titleStart);
    assert.ok(reportStart >= 0 && reportEnd > reportStart);

    const context = {
        ID_PLAYER: 1,
        SERVER_ID: 1,
        Caesar: {
            DPlayer: { Player: { id_player: 1 } },
            BaseData: { HeroAvatar: [] }
        },
        Matrial: { image: () => '' },
        army_icon: ['', '<i>unit</i>'],
        checkWinner: () => true,
        changeTimeFormat: value => `${value}s`,
        getArabicNumbers: value => String(value),
        console: { log() {} }
    };
    vm.runInNewContext([
        clientSource.slice(lossStart, lossEnd + 2),
        clientSource.slice(titleStart, titleEnd + 2),
        clientSource.slice(reportStart, reportEnd + 2)
    ].join('\n'), context);

    const ownHero = { side: 1, h_name: 'Hero', id_player: 1, avatar: 1, xp: 0 };
    const enemyHero = { side: 0, h_name: 'Enemy', id_player: 0, avatar: 2, xp: 0 };
    for (const hero of [ownHero, enemyHero]) {
        for (const slot of ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3']) {
            hero[`${slot}_pre`] = '0';
            hero[`${slot}_post`] = '0';
            hero[`${slot}_loss`] = '0';
            hero[`${slot}_injured`] = '0';
            hero[`${slot}_type`] = '0';
        }
    }
    Object.assign(ownHero, {
        f_1_type: '1',
        f_1_pre: '71',
        f_1_post: '68',
        f_1_loss: '3',
        f_1_injured: '2'
    });

    const report = context.getReportContent({
        general_data: { p_name: 'Player', task: 0, lvl: 4, round_num: 3, side_win: 1 },
        heros: [ownHero, enemyHero],
        prize: { resource: {}, matrial: [], honor: 0 }
    }, { x_coord: 443, y_coord: 261, target_type: 1, time_stamp: '2026-11-15 14:51:54' }, 0);

    assert.ok(report.includes('pre-amount stroke">71</div>'));
    assert.ok(report.includes('title="الخسائر (إصابات: 2)">3</div>'));
    assert.ok(!report.includes('title="الخسائر (إصابات: 2)">68</div>'));
});

test('battle replay shows troop deaths and falls back for older reports', () => {
    const replayBundle = fs.readFileSync(
        path.join(
            __dirname,
            '..',
            'caesar-assets',
            'web',
            'battle-replay',
            'BRAsset',
            'battle-replay.bundle.js'
        ),
        'utf8'
    );
    const lossExpression = 'lHero.dead?.[lPos] ?? Math.max(0, lHero.pre[lPos] - lHero.post[lPos])';
    assert.equal(replayBundle.split(lossExpression).length - 1, 2);

    const getDeaths = vm.runInNewContext(`(lHero, lPos) => ${lossExpression}`);
    assert.equal(getDeaths({ dead: [7], pre: [100], post: [80] }, 0), 7);
    assert.equal(getDeaths({ pre: [100], post: [80] }, 0), 20);
});

test('WebSocket battle reports store original loss counts and own injuries', async () => {
    const wsSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-ws', 'ws-server.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = wsSource.indexOf('function reportArmySlots(');
    const end = wsSource.indexOf('\nlet processingHeroReturns = false;', start);
    assert.ok(start >= 0 && end > start, 'battle report helpers should exist');

    const helpers = {};
    vm.runInNewContext(
        `${wsSource.slice(start, end)}\n` +
        'this.reportArmySlots = reportArmySlots;\n' +
        'this.aggregateMemberArmy = aggregateMemberArmy;\n' +
        'this.saveWsBattleReport = saveWsBattleReport;',
        helpers
    );

    const slotNames = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
    const army = {};
    const remainingArmy = {};
    for (const slot of slotNames) {
        army[`${slot}_type`] = 0;
        army[`${slot}_num`] = 0;
        remainingArmy[slot] = 0;
    }
    army.f_1_type = 1;
    army.f_1_num = 10;
    remainingArmy.f_1 = 7;

    const reportSlots = helpers.reportArmySlots(
        army,
        remainingArmy,
        { 2: { pre: 5, cur: 3, injured: 1 } },
        true,
        { f_1: 2 }
    );
    assert.equal(reportSlots.attackerSlots.f_1.injured, 2);
    assert.equal(reportSlots.defenderSlots.f_1.injured, 1);

    const aggregated = helpers.aggregateMemberArmy([{
        army,
        remainingArmy,
        casualties: { woundedBySlot: { f_1: 2 } }
    }]);
    assert.equal(aggregated.troops[1].injured, 2);

    const queries = [];
    const db = {
        async query(sql, params) {
            queries.push({ sql, params });
            return sql.startsWith('INSERT INTO report_player')
                ? [{ insertId: 42 }]
                : [{ affectedRows: 1 }];
        }
    };
    await helpers.saveWsBattleReport(db, {
        player: { id_player: 1, name: 'Player' },
        hero: { id_hero: 9, id_city: 3, name: 'Hero', avatar: 1, lvl: 4 },
        battleId: 7,
        xCoord: 443,
        yCoord: 261,
        unitType: 1,
        unitLvl: 4,
        attackTask: 0,
        isVictory: true,
        expGained: 0,
        honorGained: 0,
        resourceLoot: { food: 0, wood: 0, stone: 0, metal: 0, coin: 0 },
        itemLoot: [],
        attackerSlots: reportSlots.attackerSlots,
        defenderSlots: reportSlots.defenderSlots,
        writeBattleLog: false,
        heroSide: 1,
        returnDuration: 60,
        roundCount: 2,
        roundsLog: ['round one', 'round two']
    });

    const savedDetail = JSON.parse(queries[1].params[0]);
    assert.equal(savedDetail.heros[0].f_1_loss, '3');
    assert.equal(savedDetail.heros[0].f_1_injured, '2');
    assert.equal(savedDetail.heros[1].f_1_loss, '2');
    assert.equal('f_1_injured' in savedDetail.heros[1], false);

    const attackerBlocks = savedDetail.round_list.map(round => round.Heros['3'].ArmyBlocks[0]);
    const defenderBlocks = savedDetail.round_list.map(round => round.Heros['0'].ArmyBlocks[0]);
    assert.deepEqual(attackerBlocks.map(block => block.unit), [10, 8]);
    assert.deepEqual(attackerBlocks.map(block => block.dead), [2, 1]);
    assert.deepEqual(defenderBlocks.map(block => block.unit), [5, 4]);
    assert.deepEqual(defenderBlocks.map(block => block.dead), [1, 1]);
});

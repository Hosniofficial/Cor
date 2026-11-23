const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('battle replay retains the sell-voucher image referenced by its bundle', () => {
    const repositoryRoot = path.join(__dirname, '..');
    const replayDirectory = path.join(repositoryRoot, 'caesar-assets', 'web', 'battle-replay');
    const replayBundle = fs.readFileSync(
        path.join(replayDirectory, 'BRAsset', 'battle-replay.bundle.js'),
        'utf8'
    );
    const routerSource = fs.readFileSync(path.join(repositoryRoot, 'caesar-web', 'router.php'), 'utf8');

    assert.match(replayBundle, /image:\s*"Res\/items\/sellVoucher\.jpg"/);
    assert.ok(
        fs.existsSync(path.join(replayDirectory, 'Res', 'items', 'sellVoucher.jpg')),
        'the replay-specific sell-voucher image should exist'
    );
    assert.ok(
        fs.existsSync(path.join(replayDirectory, 'Res', 'player', 'Rank', 'Military_Rank_01.png')),
        'the replay-specific battle-rank image should exist'
    );
    assert.match(routerSource, /preg_match\('#\^\/Res\/\(items\|player\|tech\)\/\(\.\+\)\$#i', \$decodedUri, \$resourceMatch\)/);
    assert.match(routerSource, /technology01 \(1\)\.jpg/);
    assert.match(routerSource, /faceMatch/);
});

test('battle reports show the terrain resource and replace legacy garrison names', () => {
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
        Equipment: {
            getImage: (type, part, level) =>
                type === 'mili' && part === 'belt' ? '' : `images/equipment/${type}-${part}-${level}.png`,
            getName: (type, part, level) =>
                type === 'mili' && part === 'belt' ? '' : `${type} ${part} ${level}`
        },
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
            resource: { food: 0, wood: 0, stone: 123456, metal: 0, coin: 0 },
            matrial: [
                { prize: 'necklace_1', amount: 1 },
                {
                    prize: 'equip_loy_belt',
                    amount: 1,
                    equipmentType: 'loy',
                    equipmentPart: 'belt',
                    equipmentLevel: 3
                },
                {
                    prize: 'equip_mili_belt',
                    amount: 1,
                    equipmentType: 'mili',
                    equipmentPart: 'belt',
                    equipmentLevel: 3
                }
            ],
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
    assert.doesNotMatch(campReport, /القيصر /);
    assert.match(campReport, /أغسطس|تيبيريوس|كاليغولا|كلوديوس|نيرون|فسباسيان|تيطس|دوميتيان|نيرفا|تراجان|هادريان|أنطونينوس بيوس|ماركوس أوريليوس|كومودوس|سبتيميوس سيفيروس|قسطنطين/);
    assert.doesNotMatch(campReport, /حامية الأعداء \[مستوى 2\]/);

    const wildernessReport = context.getReportContent(detail, {
        x_coord: 37,
        y_coord: 92,
        target_type: 44,
        time_stamp: '2026-11-12 13:36:30'
    }, 0);
    assert.match(wildernessReport, /class="resource-row"/);
    assert.match(wildernessReport, /stone\.png"><span>\s*\+\s+123456/);
    assert.match(wildernessReport, /food\.png"><span>\s*\+\s+0/);
    assert.match(wildernessReport, /wood\.png"><span>\s*\+\s+0/);
    assert.match(wildernessReport, /resource\/metal\.png"><span>\s*\+\s+0/);
    assert.match(wildernessReport, /coin\.png"><span>\s*\+\s+0/);
    assert.ok(wildernessReport.indexOf('class="resource-row"') < wildernessReport.indexOf('class="prize-row flex"'));
    assert.match(wildernessReport, /class="amount stroke">1/);
    assert.match(wildernessReport, /class="equip-unit" data-equi-type="loy" data-equi-part="belt" data-equi-lvl="3" src="images\/equipment\/loy-belt-3\.png" title="loy belt 3"/);
    assert.match(wildernessReport, /<img src="images\/icons\/hero\/eq-bg\.png" title="equip_mili_belt">/);
    assert.doesNotMatch(wildernessReport, /data-equi-type="mili"/);
});

test('battle report omits return duration timing and includes view battle button next to return button', () => {
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
        encId: '12345',
        general_data: { p_name: 'Player', task: 0, lvl: 2, round_num: 1, side_win: 1 },
        heros: [{ side: 1, h_name: 'Hero', id_player: 1, avatar: 1, xp: 0 }],
        prize: { resource: {}, matrial: [], honor: 0 },
        battle_data: {
            battle_duration_sec: 9,
            return_duration_sec: 60,
            time_back: 1794738851
        }
    }, { x_coord: 37, y_coord: 92, target_type: 70, time_stamp: '2026-11-12 13:36:30' }, 0);

    assert.doesNotMatch(report, /مدة العودة|موعد عودة البطل|class="timing-row"/);
    assert.match(report, /id="back_msg"[^>]*>[\s\S]*?عودة/);
    assert.match(report, /id="view-battle-replay"[^>]*>[\s\S]*?عرض المعركة/);
    assert.match(clientSource, /window\.open\("battel\.php\?report="/);
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
    const logAttackerSlots = {
        ...reportSlots.attackerSlots,
        f_1: { type: 4, pre: 50, cur: 30, injured: 0 }
    };
    const logDefenderSlots = {
        ...reportSlots.defenderSlots,
        f_1: { type: 2, pre: 5, cur: 0, injured: 0 }
    };

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
        logAttackerSlots,
        logDefenderSlots,
        writeBattleLog: false,
        heroSide: 1,
        returnDuration: 60,
        roundCount: 2,
        roundsLog: ['equipment log', 'round one', 'round two'],
        combatSnapshot: { version: 1, reportHeroId: 9 }
    });

    const savedDetail = JSON.parse(queries[1].params[0]);
    assert.equal(savedDetail.heros[0].id_hero, 9);
    assert.deepEqual(savedDetail.combat_snapshot, { version: 1, reportHeroId: 9 });
    assert.equal(savedDetail.heros[0].f_1_loss, '3');
    assert.equal(savedDetail.heros[0].f_1_injured, '2');
    assert.equal(savedDetail.heros[1].f_1_loss, '2');
    assert.equal('f_1_injured' in savedDetail.heros[1], false);

    const attackerBlocks = savedDetail.round_list.map(round => round.Heros['3'].ArmyBlocks[0]);
    const defenderBlocks = savedDetail.round_list.map(round => round.Heros['0'].ArmyBlocks[0]);
    assert.equal(savedDetail.round_list.length, 2);
    assert.deepEqual(attackerBlocks.map(block => block.unit), [50, 40]);
    assert.deepEqual(attackerBlocks.map(block => block.dead), [10, 10]);
    assert.deepEqual(defenderBlocks.map(block => block.unit), [5, 2]);
    assert.deepEqual(defenderBlocks.map(block => block.dead), [3, 2]);

    await helpers.saveWsBattleReport(db, {
        player: { id_player: 2, name: 'Defender' },
        hero: { id_hero: 10, id_city: 4, name: 'Defender Hero', avatar: 2, lvl: 5 },
        battleId: 7,
        xCoord: 443,
        yCoord: 261,
        unitType: 1,
        unitLvl: 1,
        attackTask: 0,
        isVictory: true,
        expGained: 0,
        honorGained: 0,
        resourceLoot: { food: 0, wood: 0, stone: 0, metal: 0, coin: 0 },
        itemLoot: [],
        attackerSlots: reportSlots.attackerSlots,
        defenderSlots: reportSlots.defenderSlots,
        logAttackerSlots,
        logDefenderSlots,
        writeBattleLog: false,
        heroSide: 0,
        opponentName: 'قوات المهاجمين',
        returnDuration: 60,
        roundCount: 2,
        roundsLog: ['equipment log', 'round one', 'round two']
    });
    const defenderDetail = JSON.parse(queries[3].params[0]);
    assert.equal(defenderDetail.heros[0].side, 0);
    assert.equal(defenderDetail.hero_list[0].Hero.id_hero, 200101);
    assert.equal(defenderDetail.hero_list[1].Hero.id_hero, 10);
    assert.equal(defenderDetail.round_list[0].Heros['3'].ArmyBlocks[0].armyType, 4);
});

test('battle report APIs do not expose internal combat snapshots', () => {
    const reportApi = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-web', 'api', 'battelReport.php'),
        'utf8'
    );
    const replayApi = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-web', 'GetBattle.php'),
        'utf8'
    );

    assert.match(reportApi, /unset\(\$decoded\['combat_snapshot'\]\)/);
    assert.match(replayApi, /unset\(\$detail\['combat_snapshot'\]\)/);
});

test('battle report resource and prize rows use high contrast text and authentic Roman frame styling', () => {
    const css = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastStyle.css'),
        'utf8'
    );
    assert.match(css, /#battel_r_upper\s+\.resource-row\s*\{[^}]*background-image:[^}]*freg-top-left\.png/);
    assert.match(css, /#battel_r_upper\s+\.resource-row\s+li\s*\{[^}]*color:\s*#ffffff\s*!important/);
    assert.match(css, /#battel_r_upper\s+\.resource-row\s+li\s*\{[^}]*text-shadow:/);
    assert.match(css, /\.prize-row\s*\{[^}]*background-image:[^}]*freg-top-left\.png/);
    assert.match(css, /\.prize-row\s+li\s+\.amount\s*\{[^}]*color:\s*#ffffff/);
});

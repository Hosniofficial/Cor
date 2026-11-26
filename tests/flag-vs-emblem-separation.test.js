const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

const itemControllerSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-api', 'controllers', 'ItemController.php'),
    'utf8'
);

const playerControllerSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-api', 'controllers', 'PlayerController.php'),
    'utf8'
);

const wsServerSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-ws', 'ws-server.js'),
    'utf8'
);

test('Backend PlayerController provides changePlayerFlag endpoint validating 3 characters and consuming item flag', () => {
    assert.ok(playerControllerSource.includes('function changePlayerFlag($db)'), 'changePlayerFlag method must exist in PlayerController');
    assert.ok(playerControllerSource.includes("preg_match('/\\A\\p{L}{3}\\z/u', $flag) !== 1"), 'changePlayerFlag must enforce exactly 3 letters');
    assert.ok(playerControllerSource.includes("'flag'"), 'changePlayerFlag must check and deduct item flag');
    assert.ok(playerControllerSource.includes("UPDATE `player` SET `city_flag` = ? WHERE `id_player` = ?"), 'changePlayerFlag must update player.city_flag');
});

test('Backend ItemController useFlag decouples national emblems from 3-letter flag tags', () => {
    assert.ok(itemControllerSource.includes("case 'useFlag':"), 'useFlag must exist in ItemController');
    const useFlagCase = itemControllerSource.slice(
        itemControllerSource.indexOf("case 'useFlag':"),
        itemControllerSource.indexOf("case 'useExpan':")
    );
    assert.ok(!useFlagCase.includes("validateFlagTag"), 'useFlag should not require validateFlagTag');
    assert.ok(!useFlagCase.includes("$_REQUEST['flagTag']"), 'useFlag must not accept a city text tag');
    assert.ok(useFlagCase.includes("`flag_tag` = \\'\\'"), 'useFlag must clear legacy city text tags');
    assert.ok(useFlagCase.includes("7 * 86400"), 'useFlag sets 7 days expiry');
    assert.ok(useFlagCase.includes("`flag_expires_at` = ?"), 'useFlag sets city flag expiry');
});

test('WebSocket server includes player city_flag in world city queries and broadcasts Base.refreshPlayerFlag', () => {
    assert.ok(wsServerSource.includes('p.city_flag AS cf'), 'getWorldCity query in WS must select p.city_flag AS cf');
    assert.ok(wsServerSource.includes('cf: String(r.cf || "")'), 'getWorldCity mapping must include cf');
    assert.ok(wsServerSource.includes("'Player/changePlayerFlag'"), 'WS server must handle Player/changePlayerFlag');
    assert.ok(wsServerSource.includes("classPath: 'Base.refreshPlayerFlag'"), 'WS server must broadcast Base.refreshPlayerFlag');
    assert.ok(wsServerSource.includes('SELECT city_flag FROM player WHERE id_player = ? AND p_token = ? LIMIT 1'), 'player flag broadcast must read the persisted value');
    assert.ok(wsServerSource.includes("city_flag: String(players[0].city_flag || '')"), 'player flag broadcast must ignore client-supplied values');
    assert.ok(wsServerSource.includes('JOIN guild_member gm ON gm.id_guild = g.id_guild AND gm.id_player = p.id_player'), 'guild name broadcast must verify the sender is a guild member');
    assert.ok(wsServerSource.includes('WHERE g.id_guild = ? AND gm.`rank` >= 6 LIMIT 1'), 'guild name broadcast must require guild-leader authorization');
    assert.ok(wsServerSource.includes('guildName !== requestedName'), 'guild name broadcast must match the persisted name');
    assert.ok(wsServerSource.includes('newName: guildName'), 'guild name broadcast must use the persisted name');
    assert.ok(clientSource.includes('escapeHtml(data.ChangeBy)'), 'guild name announcements must escape the player name');
});

test('Client Base.refreshPlayerFlag updates player city_flag across owned and colonized world units', () => {
    const refreshPlayerFlagStart = clientSource.indexOf('Caesar.WsLib.Base.refreshPlayerFlag = function(data) {');
    assert.ok(refreshPlayerFlagStart >= 0, 'Caesar.WsLib.Base.refreshPlayerFlag must be defined');

    const refreshPlayerFlagEnd = clientSource.indexOf("Caesar['WsLib']['Base']['DailyRest']", refreshPlayerFlagStart);
    assert.ok(refreshPlayerFlagEnd > refreshPlayerFlagStart, 'End of refreshPlayerFlag block found');

    const fnSource = clientSource.slice(refreshPlayerFlagStart, refreshPlayerFlagEnd);

    const worldRefreshes = [];
    const ownedUnit = { idPlayer: 42, city_flag: 'OLD' };
    const otherUnit = { idPlayer: 99, city_flag: 'XYZ' };
    const colonizedUnit = { idPlayer: 100, ColonizerIdPlayer: 42, CityColonized: true, CityColonizerFlag: 'OLD' };

    const context = {
        Caesar: {
            worldAllUnits: {
                '1': ownedUnit,
                '2': otherUnit,
                '3': colonizedUnit
            },
            DPlayer: {
                Player: { id_player: 42, city_flag: 'OLD' }
            },
            World: {
                Map: {
                    RefreshWorld: () => worldRefreshes.push('refreshed')
                }
            },
            WsLib: { Base: {} }
        }
    };

    vm.runInNewContext(fnSource, context);
    context.Caesar.WsLib.Base.refreshPlayerFlag({ idPlayer: 42, city_flag: 'NEW' });

    assert.equal(context.Caesar.DPlayer.Player.city_flag, 'NEW');
    assert.equal(ownedUnit.city_flag, 'NEW');
    assert.equal(otherUnit.city_flag, 'XYZ');
    assert.equal(colonizedUnit.CityColonizerFlag, 'NEW');
    assert.equal(worldRefreshes.length, 1);
});

test('Client BaseData items separate item flag (promptChangePlayerFlag) from national emblems (7-day confirmation)', () => {
    assert.ok(clientSource.includes('Caesar.BaseData.Items["flag"][`UseFunc`] = function() {'), 'Item flag must register UseFunc');
    assert.ok(clientSource.includes('return promptChangePlayerFlag();'), 'flag UseFunc must call promptChangePlayerFlag');

    const nationalFlags = [
        'flag_england', 'flag_france', 'flag_germany', 'flag_magul',
        'flag_macdoni', 'flag_roma', 'flag_spain', 'flag_cartaga',
        'flag_egypt', 'flag_greek'
    ];

    for (const nf of nationalFlags) {
        assert.ok(clientSource.includes(`"${nf}"`), `Items list must include ${nf}`);
    }
    assert.ok(clientSource.includes('هل تريد تفعيل '), 'National flags use confirmation dialog without 3-letter input');
});

test('Animation.cityFlag handles both playerFlag 3-letter text and secondary 7-day emblem image', () => {
    const animFlagStart = clientSource.indexOf('Animation.cityFlag = function() {');
    assert.ok(animFlagStart >= 0, 'Animation.cityFlag must exist');
    const animFlagSnippet = clientSource.slice(animFlagStart, animFlagStart + 4500);

    assert.ok(animFlagSnippet.includes('CityFlagTextEntite'), 'Animation.cityFlag must maintain CityFlagTextEntite for 3-letter text');
    assert.ok(animFlagSnippet.includes('SecondaryFlagEntite'), 'Animation.cityFlag must maintain SecondaryFlagEntite for national badge');
    assert.ok(!animFlagSnippet.includes('Unit.CityFlagTag'), 'city emblems must not fall back to a city-specific text tag');
});

test('Animation.cityFlag renders player flag text on first creation and subsequent refreshes', () => {
    const animFlagStart = clientSource.indexOf('Animation.cityFlag = function() {');
    const animFlagEnd = clientSource.indexOf('Animation.cityColonizerFlag = function()', animFlagStart);
    assert.ok(animFlagEnd > animFlagStart, 'End of Animation.cityFlag block found');
    const fnSource = clientSource.slice(animFlagStart, animFlagEnd);

    const textPositions = [];
    const textEntities = [];
    const flagEntity = {
        setOrigin() { return this; },
        play() { return this; },
        setDepth() { return this; },
        destroy() {}
    };
    const unit = {
        ut: 3,
        idPlayer: 42,
        CityFlag: '0',
        CityFlagExpiresAt: 0
    };
    const context = {
        Animation: {},
        Caesar: {
            DPlayer: { Player: { id_player: 42, city_flag: 'ABC' } },
            Guild: { GuildData: null, Allay: [] },
            GE: {
                WorldScene: {
                    children: { list: [{ data: { get: key => key === 'xCoord' ? 1 : 2 } }] },
                    add: {
                        sprite() { return flagEntity; },
                        text(x, y) {
                            textPositions.push([x, y]);
                            const entity = {
                                destroyed: false,
                                setOrigin() { return this; },
                                setDepth() { return this; },
                                setPosition(x, y) {
                                    textPositions.push([x, y]);
                                    return this;
                                },
                                setText() { return this; },
                                destroy() { this.destroyed = true; }
                            };
                            textEntities.push(entity);
                            return entity;
                        }
                    }
                }
            },
            World: { Map: { posX: () => 100, posY: () => 200, posZ: () => 5 } }
        },
        WorldUnit: {
            getWorldUnit: () => unit,
            isCity: () => true
        },
        WUT_CITY_LVL_3: 3,
        clearTimeout() {}
    };

    const clearEntitiesStart = clientSource.indexOf('function clearCityFlagEntities(unit) {');
    assert.ok(clearEntitiesStart >= 0, 'clearCityFlagEntities helper must exist');
    const clearEntitiesEnd = clientSource.indexOf('\n}', clearEntitiesStart) + 2;
    assert.ok(clearEntitiesEnd > clearEntitiesStart, 'clearCityFlagEntities helper must exist');
    const cleanupSource = clientSource.slice(clearEntitiesStart, clearEntitiesEnd);

    vm.runInNewContext(fnSource, context);
    vm.runInNewContext(cleanupSource, context);
    context.Animation.cityFlag();
    context.Animation.cityFlag();
    const firstTextEntity = unit.CityFlagTextEntite;
    context.clearCityFlagEntities(unit);
    context.Animation.cityFlag();

    assert.equal(firstTextEntity.destroyed, true);
    assert.equal(textEntities.length, 2, 'Returning to the world map must create a fresh player flag text entity');
    assert.notEqual(unit.CityFlagTextEntite, firstTextEntity);
    assert.deepEqual(textPositions, [[147, 249], [147, 249], [147, 249]]);
    assert.ok(clientSource.includes('clearCityFlagEntities(Unit);'), 'Evicting world units must clear stale flag entities');
    assert.ok(clientSource.includes('Caesar.GE.switchSceneView("World", "City")'), 'Leaving the world scene must sleep cleanly instead of destroying cached flags');
});

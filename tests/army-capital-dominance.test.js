const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const clientJsPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js');
const worldCtrlPath = path.join(__dirname, '..', 'caesar-api', 'controllers', 'WorldController.php');
const battleResPath = path.join(__dirname, '..', 'caesar-shared', 'battle-resolution.js');
const wsServerPath = path.join(__dirname, '..', 'caesar-ws', 'ws-server.js');

test('Dominant.armyCapital in caesar-client.js renders vacant or dominant data instead of permanently unavailable', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');

    // Verify Dominant.armyCapital exists
    assert.ok(clientJs.includes('Dominant.armyCapital = function()'), 'Dominant.armyCapital should exist');

    // Verify it handles vacant status gracefully with "شاغر" in Arabic or "----"
    assert.ok(clientJs.includes('شاغر') || clientJs.includes('----'), 'Dominant.armyCapital should show vacant status when unclaimed');

    // Verify duration calculation handles days or hours formatting
    assert.ok(
        clientJs.includes('Math.floor(duration / 86400)') || clientJs.includes('durationFormatted'),
        'Dominant.armyCapital should format duration gracefully'
    );
});

test('WorldController getArmyCapitalDominant connects to live guild_dominance and reports dominanceDataAvailable true', () => {
    const worldCtrl = fs.readFileSync(worldCtrlPath, 'utf8');

    // Verify route passes $db
    assert.ok(
        worldCtrl.includes("self::getArmyCapitalDominant($db);"),
        'WorldController route must pass $db to getArmyCapitalDominant'
    );

    // Verify method definition accepts $db
    assert.ok(
        worldCtrl.includes("getArmyCapitalDominant($db = null)"),
        'getArmyCapitalDominant must accept $db'
    );

    // Verify it queries guild_dominance
    assert.ok(
        worldCtrl.includes("FROM guild_dominance gd"),
        'getArmyCapitalDominant must query guild_dominance'
    );

    // Verify dominanceDataAvailable is true
    assert.ok(
        worldCtrl.includes("'dominanceDataAvailable' => true"),
        'getArmyCapitalDominant must return dominanceDataAvailable => true'
    );

    // Verify isGuildDominanceTarget includes capital types (150-155, 105)
    assert.ok(
        worldCtrl.includes('$unitType >= 150 && $unitType <= 155') && worldCtrl.includes('$unitType === 105'),
        'isGuildDominanceTarget in WorldController must include capitals 150-155 and 105'
    );
});

test('battle-resolution and ws-server include capital types in guild dominance checks', () => {
    const battleRes = fs.readFileSync(battleResPath, 'utf8');
    const wsServer = fs.readFileSync(wsServerPath, 'utf8');

    assert.ok(
        battleRes.includes('unitType >= 150 && unitType <= 155') && battleRes.includes('unitType === 105'),
        'battle-resolution.js must include capitals in isGuildConquest'
    );

    assert.ok(
        wsServer.includes('unitType >= 150 && unitType <= 155') && wsServer.includes('unitType === 105'),
        'ws-server.js must include capitals in isGuildDominanceTarget'
    );
});

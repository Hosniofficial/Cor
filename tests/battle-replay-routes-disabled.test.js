const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const assetRoot = path.join(root, 'caesar-assets', 'web');

test('legacy battle replay URLs are disabled', () => {
    const routerSource = fs.readFileSync(
        path.join(root, 'caesar-web', 'router.php'),
        'utf8'
    );
    const clientSource = fs.readFileSync(
        path.join(assetRoot, 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    const replayApiSource = fs.readFileSync(
        path.join(root, 'caesar-web', 'api', 'battelReport.php'),
        'utf8'
    );
    const prefixes = [
        '/getbattle',
        '/getbattle.php',
        '/battlereplay',
        '/battel.php',
        '/battle-replay',
        '/brasset',
        '/res',
        '/src',
        '/cocos2d-html5',
        '/css',
        '/json'
    ];

    for (const prefix of prefixes) {
        const escapedPrefix = prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        assert.match(
            routerSource,
            new RegExp(`['"]${escapedPrefix}['"]`)
        );
    }

    assert.doesNotMatch(routerSource, /battleAssetAliases|require __DIR__ \. '\/GetBattle\.php'/);
    assert.doesNotMatch(clientSource, /battel\.php\?report=|show-battel-animated/);
    assert.match(replayApiSource, /get_battle_replay/);
    assert.match(replayApiSource, /http_response_code\(404\)/);
    assert.doesNotMatch(replayApiSource, /echo json_encode\(\['BattleReplay'/);
    assert.match(clientSource, /url: "api\/battel\.php"/);
    assert.ok(fs.statSync(path.join(root, 'caesar-web', 'api', 'battel.php')).isFile());
});

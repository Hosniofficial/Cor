const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const assetRoot = path.join(root, 'caesar-assets', 'web');

test('battle replay player and routes are active and connected', () => {
    const routerSource = fs.readFileSync(
        path.join(root, 'caesar-web', 'router.php'),
        'utf8'
    );
    const clientSource = fs.readFileSync(
        path.join(assetRoot, 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );

    assert.match(routerSource, /GetBattle\.php/);
    assert.match(routerSource, /battel\.php/);
    assert.match(routerSource, /BattleReplay/);
    assert.match(clientSource, /battel\.php\?report=/);
    assert.match(clientSource, /view-battle-replay/);
    assert.ok(fs.statSync(path.join(root, 'caesar-web', 'battel.php')).isFile());
    assert.ok(fs.statSync(path.join(root, 'caesar-web', 'GetBattle.php')).isFile());
});

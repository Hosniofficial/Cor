const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const clientSource = fs.readFileSync(
    path.join(root, 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
);
const playerController = fs.readFileSync(
    path.join(root, 'caesar-api', 'controllers', 'PlayerController.php'),
    'utf8'
);
const worldController = fs.readFileSync(
    path.join(root, 'caesar-api', 'controllers', 'WorldController.php'),
    'utf8'
);
const itemController = fs.readFileSync(
    path.join(root, 'caesar-api', 'controllers', 'ItemController.php'),
    'utf8'
);

test('backend game data files are outside public asset roots', () => {
    for (const filename of [
        'equipment-power.json',
        'world-unit-prizes.json',
        'item-prices.json',
        'exchange-offers.json'
    ]) {
        assert.equal(fs.existsSync(path.join(root, 'caesar-private', 'data', filename)), true);
        assert.equal(fs.existsSync(path.join(root, 'caesar-assets', 'api', 'data', filename)), false);
    }
});

test('client does not request complete equipment power or prize tables', () => {
    assert.doesNotMatch(clientSource, /APlayerEquip\/getEquipPower/);
    assert.doesNotMatch(clientSource, /AWorld\/getWorldUnitPrize/);
    assert.doesNotMatch(playerController, /APlayerEquip\/getEquipPower/);
    assert.doesNotMatch(worldController, /AWorld\/getWorldUnitPrize/);
    assert.match(itemController, /caesar-private\/data\/item-prices\.json/);
    assert.match(itemController, /caesar-private\/data\/exchange-offers\.json/);
});

test('exchange API omits server-wide offer limits and usage counts', () => {
    assert.match(itemController, /unset\(\$offer\['server_max'\], \$offer\['server_take'\]\);/);
    assert.match(itemController, /\$offer\['take_times'\] = \$usage\[\$id\] \?\? 0;/);
});

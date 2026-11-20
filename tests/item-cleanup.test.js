const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');

test('beginner packs do not award unsupported e-tier resources', () => {
    const itemController = fs.readFileSync(
        path.join(root, 'caesar-api', 'controllers', 'ItemController.php'),
        'utf8'
    );
    const lootStart = itemController.indexOf('$packLoot = [');
    const lootEnd = itemController.indexOf('$baseList =', lootStart);
    assert.ok(lootStart >= 0 && lootEnd > lootStart, 'beginner pack rewards should exist');

    const lootTable = itemController.slice(lootStart, lootEnd);
    for (const resource of ['food', 'wood', 'stone', 'metal', 'coin']) {
        assert.doesNotMatch(lootTable, new RegExp(`['"]Item['"]\\s*=>\\s*['"]${resource}_e['"]`));
    }
});

test('gold bar contribution recipe retains the gold bar piece requirement pending a final decision', () => {
    const contribute = JSON.parse(fs.readFileSync(
        path.join(root, 'caesar-assets', 'api', 'js-0.0.24', 'json', 'contribute.json'),
        'utf8'
    ));
    const goldBarRecipe = contribute['Item.gold_bar'];
    assert.ok(goldBarRecipe, 'gold bar recipe should remain available');
    assert.ok(goldBarRecipe.ListOfNeed.some(requirement =>
        requirement.type === 'promotion' && requirement.promotion === 3
    ), 'the existing promotion requirement should be preserved');
    assert.ok(goldBarRecipe.ListOfNeed.some(requirement =>
        requirement.type === 'item' && requirement.item === 'gold_bar_piece' && requirement.amount === 20
    ), 'the original gold_bar_piece requirement should remain until its source is confirmed');
});

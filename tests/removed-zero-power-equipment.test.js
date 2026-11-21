const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');
const removedSets = ['worrior', 'thunder', 'thor', 'neptune', 'light'];
const removedSetNames = new Set(removedSets);
const catalogPaths = [
    'caesar-assets/api/js-0.0.24/json/equipment/ar.json',
    'caesar-assets/api/js-3.4.113/json/equipment/ar.json',
    'caesar-assets/web/js-3.4.113/json/equipment/ar.json'
];

test('removed zero-power equipment is absent from every game catalog and power table', () => {
    let expectedKeys;
    for (const relativePath of catalogPaths) {
        const catalog = JSON.parse(fs.readFileSync(path.join(root, relativePath), 'utf8'));
        const keys = Object.keys(catalog).sort();

        assert.equal(keys.length, 305, `${relativePath} should contain 305 supported pieces`);
        assert.equal(keys.some(key => removedSetNames.has(key.split('_')[0])), false, relativePath);
        assert.equal(keys.some(key => key.startsWith('mili_')), true, 'the powered mili set remains');
        if (expectedKeys) {
            assert.deepEqual(keys, expectedKeys, `${relativePath} should match the other catalogs`);
        } else {
            expectedKeys = keys;
        }
    }

    const power = JSON.parse(
        fs.readFileSync(path.join(root, 'caesar-private/data/equipment-power.json'), 'utf8')
    );
    assert.equal(power.length, 305);
    assert.equal(power.some(item => removedSetNames.has(item.equip)), false);
    assert.equal(power.filter(item => item.equip === 'mili').length, 5);
    assert.deepEqual(
        power.map(item => `${item.equip}_${item.part}_${item.lvl}`).sort(),
        expectedKeys,
        'server power records should match the supported equipment catalog'
    );

    const legacyPower = JSON.parse(
        fs.readFileSync(path.join(root, 'caesar-web/api/equip.php'), 'utf8')
    );
    assert.equal(legacyPower.length, 305);
    assert.equal(legacyPower.some(item => removedSetNames.has(item.equip)), false);
    assert.equal(legacyPower.filter(item => item.equip === 'mili').length, 5);
    assert.deepEqual(
        legacyPower.map(item => `${item.equip}_${item.part}_${item.lvl}`).sort(),
        expectedKeys,
        'legacy power records should match the supported equipment catalog'
    );
});

test('test-player seed and cleanup migration stay limited to the requested test account', () => {
    const seed = fs.readFileSync(
        path.join(root, 'migrations/20261115_seed_test_player_inventory_and_armies.sql'),
        'utf8'
    );
    const cleanup = fs.readFileSync(
        path.join(root, 'migrations/20261120_remove_zero_power_equipment_from_test_player.sql'),
        'utf8'
    );
    const adminApi = fs.readFileSync(path.join(root, 'caesar-admin/api.php'), 'utf8');
    const directGrant = adminApi.slice(
        adminApi.indexOf("case 'grant_equip':"),
        adminApi.indexOf("case 'delete_equip':")
    );
    const seedCatalog = seed.match(/INSERT INTO _seed_equipment_catalog[\s\S]*?;\s*/);
    assert.ok(seedCatalog, 'test seed equipment catalog should exist');
    assert.equal((seedCatalog[0].match(/\('[^']+',\s*'[^']+',\s*\d+\)/g) || []).length, 305);

    for (const setName of removedSets) {
        assert.doesNotMatch(seed, new RegExp(`\\('${setName}',`));
        assert.match(cleanup, new RegExp(`'${setName}'`));
    }
    assert.match(cleanup, /DATABASE\(\)\s*=\s*'caesar_test'/);
    assert.match(cleanup, /username`\s*=\s*'test2'/);
    assert.doesNotMatch(adminApi, /'(worrior|thunder|thor|neptune|light)'\s*=>/);
    assert.match(directGrant, /\$meta\s*=\s*in_array\(\$part,\s*\$primaryEquipParts,\s*true\)/);
    assert.ok(
        directGrant.indexOf('if ($meta === null)') < directGrant.indexOf('INSERT INTO'),
        'admin must reject equipment missing from the catalog before insertion'
    );
});

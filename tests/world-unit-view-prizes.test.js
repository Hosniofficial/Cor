const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '..');
const clientSource = fs.readFileSync(
    path.join(root, 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
);
const worldControllerSource = fs.readFileSync(
    path.join(root, 'caesar-api', 'controllers', 'WorldController.php'),
    'utf8'
);
const playerControllerSource = fs.readFileSync(
    path.join(root, 'caesar-api', 'controllers', 'PlayerController.php'),
    'utf8'
);

test('secure unit view prizes endpoint is registered in WorldController', () => {
    assert.match(worldControllerSource, /case '\/api\/AWorld\/getUnitViewPrizes':/);
    assert.match(worldControllerSource, /function getUnitViewPrizes\(/);
    // Ensure win_rate is not exposed in the response projection
    assert.match(worldControllerSource, /'amount_max' => \(int\)/);
    assert.doesNotMatch(worldControllerSource, /'win_rate' =>/);
    // Ensure bulk dump is blocked when unitType is missing
    assert.match(worldControllerSource, /error_unit_type_required/);
});

test('client does not preload complete prize table on startup and loads on demand', () => {
    // Startup loader for worldUnitData does not fetch all prizes
    const worldUnitDataBlock = clientSource.slice(
        clientSource.indexOf('json/worldUnitData.json'),
        clientSource.indexOf('json/WorldBarrary.json')
    );
    assert.doesNotMatch(worldUnitDataBlock, /fetchAllPrizes/);
    assert.doesNotMatch(clientSource, /WorldUnit\.prize\.fetchAllPrizes\(\)/);
    // fetchUnitPrizes accepts unitType and requests on demand
    assert.match(clientSource, /WorldUnit\.prize\.fetchUnitPrizes = function\(unitType/);
});

test('failed prize requests log once, clear the loading state, and can be retried', () => {
    const startIdx = clientSource.indexOf('WorldUnit.prize.PrizFor = {');
    const endIdx = clientSource.indexOf('Caesar.World.Map.posX = function', startIdx);
    assert.ok(startIdx > 0 && endIdx > startIdx, 'WorldUnit.prize block found');

    let requestCount = 0;
    let callbackCount = 0;
    const errors = [];
    const context = {
        API_URL: 'http://localhost',
        Caesar: { Config: { AuthToken: 'token', idServer: 1 } },
        WorldUnit: { prize: {} },
        console: { error: (...args) => errors.push(args) },
        $: {
            ajax: options => {
                requestCount++;
                options.error({}, 'error', 'network unavailable');
            }
        }
    };
    vm.runInNewContext(clientSource.slice(startIdx, endIdx), context);

    context.WorldUnit.prize.fetchUnitPrizes(71, () => callbackCount++);
    context.WorldUnit.prize.fetchUnitPrizes(71, () => callbackCount++);

    assert.equal(requestCount, 2, 'a failed request does not retry recursively but remains retryable');
    assert.equal(callbackCount, 0, 'failed requests do not invoke the renderer callback');
    assert.equal(Object.keys(context.WorldUnit.prize._loadingTypes).length, 0);
    assert.equal(errors.length, 2);
});

test('prize IDs, unit types, rounds, and active unit definitions match the raw baseline', () => {
    const curPath = path.join(root, 'caesar-private', 'data', 'world-unit-prizes.json');
    assert.ok(fs.existsSync(curPath), 'world-unit-prizes.json exists');
    const curData = JSON.parse(fs.readFileSync(curPath, 'utf8'));

    const jsVersion = playerControllerSource.match(/'JsVersion'\s*=>\s*'(-[\d.]+)'/);
    assert.ok(jsVersion, 'current API asset version is configured');
    const unitDataPath = path.join(
        root,
        'caesar-assets',
        'api',
        `js${jsVersion[1]}`,
        'json',
        'worldUnitData.json'
    );
    const unitData = JSON.parse(fs.readFileSync(unitDataPath, 'utf8'));

    const rawBucketDigests = {
        Win: '7ac57ba880b55a176966cfc325ae6b338ab99c54647015bf648bf7f84645f10e',
        Lose: '360fae95442885f555bf39955bf5fe7069c64f6e3b9bbe90dd01408992d718df',
        Sp: '76621beec47765a6d2cdc0581b8afe3789273518c95552aee9726eb568572469',
        Plunder: 'f759d344b5c86d6d8ead065be4ac58088c9fa82558c72aa402fcdb2e60b6681b'
    };
    const rowDigest = rows => crypto.createHash('sha256')
        .update(rows.map(row => [
            row.id_prize,
            row.unitType,
            row.lvl,
            row.prize,
            row.amount_min,
            row.amount_max,
            row.win_rate
        ].join('|')).join('\n'))
        .digest('hex');

    for (const [bucket, expectedDigest] of Object.entries(rawBucketDigests)) {
        assert.ok(Array.isArray(curData[bucket]), `${bucket} prize bucket exists`);
        assert.equal(rowDigest(curData[bucket]), expectedDigest, `${bucket} rows match raw IDs and fields`);
    }

    const prizeTypes = [...new Set(curData.Win.map(row => Number(row.unitType)))].sort((a, b) => a - b);
    assert.equal(prizeTypes.length, 91);
    for (const unitType of prizeTypes) {
        assert.ok(unitData[unitType], `unit type ${unitType} exists in the active asset version`);
    }
    const unitIdentity = prizeTypes.map(unitType => {
        const unit = unitData[unitType];
        return [
            unitType,
            unit.Title ?? '',
            unit.maxLvl ?? '',
            unit.lvlChange ?? ''
        ].join('|');
    }).join('\n');
    assert.equal(
        crypto.createHash('sha256').update(unitIdentity).digest('hex'),
        'b5fe34ce0054e19cd0e1edc5ef93ddf78b3db4a422e7cc81b16f57a837a1a021',
        'active unit IDs, names, and level rules match the raw asset version'
    );
});

test('server equipment drop definitions match the active client equipment set', () => {
    const jsVersion = playerControllerSource.match(/'JsVersion'\s*=>\s*'(-[\d.]+)'/);
    assert.ok(jsVersion, 'current API asset version is configured');
    const clientEquipmentPath = path.join(
        root,
        'caesar-assets',
        'api',
        `js${jsVersion[1]}`,
        'json',
        'equipment',
        'ar.json'
    );
    const clientEquipment = JSON.parse(fs.readFileSync(clientEquipmentPath, 'utf8'));
    const serverEquipment = JSON.parse(fs.readFileSync(
        path.join(root, 'caesar-private', 'data', 'equipment-power.json'),
        'utf8'
    ));

    assert.deepEqual(
        serverEquipment.map(item => `${item.equip}_${item.part}_${item.lvl}`).sort(),
        Object.keys(clientEquipment).sort()
    );
});

test('client prize functions render authentic round prizes with quantities and gray-filter', () => {
    // Setup isolated VM context with mock WorldUnit and Matrial
    const context = {
        API_URL: 'http://localhost',
        WorldUnit: {
            getWorldUnit: (x, y) => ({ x, y, ut: 71, l: 10 }),
            prize: {}
        },
        Matrial: {
            image: prize => `images/items/${prize}.png`
        },
        $: {
            ajax: () => {}
        }
    };

    // Extract WorldUnit.prize definitions from caesar-client.js
    const startIdx = clientSource.indexOf('WorldUnit.prize.PrizFor = {');
    const endIdx = clientSource.indexOf('Caesar.World.Map.posX = function', startIdx);
    assert.ok(startIdx > 0 && endIdx > startIdx, 'WorldUnit.prize block found');
    const prizeBlock = clientSource.slice(startIdx, endIdx);

    vm.runInNewContext(prizeBlock, context);

    // Populate with test sanitized prizes for type 71 (Camps)
    context.WorldUnit.prize.PrizeList.Win = [
        { unitType: 71, lvl: 1, prize: 'necklace_4', amount_min: 1, amount_max: 1 },
        { unitType: 71, lvl: 1, prize: 'flag_england', amount_min: 1, amount_max: 3 },
        { unitType: 71, lvl: 10, prize: 'train_acce_30', amount_min: 1, amount_max: 2 },
        { unitType: 71, lvl: 10, prize: 'spartans_relics', amount_min: 1, amount_max: 1 }
    ];

    // 1. getUnitPrize for level 10
    const lvl10Prizes = context.WorldUnit.prize.getUnitPrize(71, 10);
    assert.equal(lvl10Prizes.length, 2);
    assert.equal(lvl10Prizes[0].prize, 'train_acce_30');

    // 2. getUnitAllLvlsPrize for type 71
    const allPrizes = context.WorldUnit.prize.getUnitAllLvlsPrize(71);
    assert.equal(allPrizes.length, 4);

    // 3. prizeList renders HTML for current round 10 with quantities
    const listHtml = context.WorldUnit.prize.prizeList(100, 200);
    assert.match(listHtml, /unit-image/);
    assert.match(listHtml, /train_acce_30\.png/);
    assert.match(listHtml, /\(1-2\)X/);
    assert.match(listHtml, /spartans_relics\.png/);
    assert.match(listHtml, /\(1\)X/);
    assert.doesNotMatch(listHtml, /المكافآت العشوائية/);

    // 4. prizeAllLvlsList applies gray-filter to level 1 prizes and keeps level 10 active
    const allLvlsHtml = context.WorldUnit.prize.prizeAllLvlsList(100, 200);
    assert.match(allLvlsHtml, /unit-image gray-filter[\s\S]*?url\(images\/items\/necklace_4\.png\)/);
    assert.match(allLvlsHtml, /unit-image\s+"[\s\S]*?url\(images\/items\/train_acce_30\.png\)/);
    assert.doesNotMatch(allLvlsHtml, /المكافآت العشوائية/);

    // 5. When an item exists across rounds (e.g. necklace_4 in both round 1 and round 10),
    // it must stay active at round 10 and display the round 10 quantity.
    context.WorldUnit.prize.PrizeList.Win.push({
        unitType: 71, lvl: 10, prize: 'necklace_4', amount_min: 2, amount_max: 2
    });
    const multiLvlHtml = context.WorldUnit.prize.prizeAllLvlsList(100, 200);
    assert.match(multiLvlHtml, /unit-image\s+"[\s\S]*?url\(images\/items\/necklace_4\.png\)[\s\S]*?\(2\)X/);
});

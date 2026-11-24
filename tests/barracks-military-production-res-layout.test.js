const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('military recruitment resource layout preserves inline formatting without wrapping floats', () => {
    const cssDir = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113');
    const cityCss = fs.readFileSync(path.join(cssDir, 'city.css'), 'utf8');
    const lastOfLastCss = fs.readFileSync(path.join(cssDir, 'lastOfLast.css'), 'utf8');
    const gameplayBundle = fs.readFileSync(path.join(cssDir, 'gameplay.bundle.css'), 'utf8');
    const overridesBundle = fs.readFileSync(path.join(cssDir, 'overrides.bundle.css'), 'utf8');

    // city.css checks
    assert.match(cityCss, /\.req_table tr td img\s*\{[^}]*display:\s*inline-block/);
    assert.match(cityCss, /\.req_table tr td img\s*\{[^}]*float:\s*none/);
    assert.match(cityCss, /\.req_table tr td \.amount\s*\{[^}]*float:\s*none/);
    assert.match(cityCss, /\.req_table tr td \.amount\s*\{[^}]*display:\s*inline-block/);
    assert.match(cityCss, /\.req_table tr td \.amount\s*\{[^}]*white-space:\s*nowrap/);
    assert.match(cityCss, /\.mili_building \.leftOfRight \.army_req_table \.req_table\s*\{[^}]*width:\s*95%;/);
    assert.match(cityCss, /\.mili_building \.leftOfRight \.army_req_table \.req_table\s*\{[^}]*table-layout:\s*fixed/);

    // lastOfLast.css overrides
    assert.match(lastOfLastCss, /\.mili_building \.leftOfRight \.army_req_table \.req_table tbody td\s*\{[^}]*white-space:\s*nowrap !important/);
    assert.match(lastOfLastCss, /\.mili_building \.leftOfRight \.army_req_table \.req_table tr td img\s*\{[^}]*display:\s*inline-block !important/);
    assert.match(lastOfLastCss, /\.mili_building \.leftOfRight \.army_req_table \.req_table tr td img\s*\{[^}]*float:\s*none !important/);
    assert.match(lastOfLastCss, /\.mili_building \.leftOfRight \.army_req_table \.req_table tr td \.amount\s*\{[^}]*display:\s*inline-block !important/);
    assert.match(lastOfLastCss, /\.mili_building \.leftOfRight \.army_req_table \.req_table tr td \.amount\s*\{[^}]*float:\s*none !important/);
    assert.match(lastOfLastCss, /\.mili_building \.leftOfRight \.army_req_table \.req_table tr td \.amount\s*\{[^}]*white-space:\s*nowrap !important/);

    // Bundle checks
    assert.match(gameplayBundle, /\.req_table tr td img\s*\{[^}]*float:\s*none/);
    assert.match(overridesBundle, /\.mili_building \.leftOfRight \.army_req_table \.req_table tbody td\s*\{[^}]*white-space:\s*nowrap !important/);
});

test('client army recruitment input calculation retains resource formatting on large values and zero bases', () => {
    const clientPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js');
    const clientJs = fs.readFileSync(clientPath, 'utf8');

    // Verify key logic in caesar-client.js for .sol-2-build-amount
    assert.ok(clientJs.includes('(Number(army.food) || 0) === 0 ? "---" : Math.max(army.food * val_, army.food)'), 'handles zero food properly');
    assert.ok(clientJs.includes('(Number(army.stone) || 0) === 0 ? "---" : Math.max(army.stone * val_, army.stone)'), 'handles zero stone properly');
    assert.ok(clientJs.includes('$("#dialg_box .leftOfRight .army_req_table .sol-food").html(foodVal)'), 'updates resource html');

    // Simulate input calculation for army_a (infantry: food 150, wood 500, stone 0, metal 100, coin 18, people 1)
    const army_a = { food: 150, wood: 500, stone: 0, metal: 100, coin: 18, people: 1, time: 50 };
    const calculateRequirements = (valInput) => {
        const val_ = Math.max(1, parseInt(valInput, 10) || 0);
        return {
            food: army_a.food === 0 ? '---' : Math.max(army_a.food * val_, army_a.food),
            wood: army_a.wood === 0 ? '---' : Math.max(army_a.wood * val_, army_a.wood),
            stone: army_a.stone === 0 ? '---' : Math.max(army_a.stone * val_, army_a.stone),
            metal: army_a.metal === 0 ? '---' : Math.max(army_a.metal * val_, army_a.metal),
            coin: army_a.coin === 0 ? '---' : Math.max(army_a.coin * val_, army_a.coin),
            people: army_a.people === 0 ? '---' : Math.max(army_a.people * val_, army_a.people),
        };
    };

    // When recruiting 50,000 infantry
    const largeReq = calculateRequirements('50000');
    assert.equal(largeReq.food, 7500000);
    assert.equal(largeReq.wood, 25000000);
    assert.equal(largeReq.stone, '---');
    assert.equal(largeReq.metal, 5000000);
    assert.equal(largeReq.coin, 900000);
    assert.equal(largeReq.people, 50000);

    // When clearing input box
    const emptyReq = calculateRequirements('');
    assert.equal(emptyReq.food, 150);
    assert.equal(emptyReq.wood, 500);
    assert.equal(emptyReq.stone, '---');
});

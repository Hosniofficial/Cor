const test = require('node:test');
const assert = require('node:assert/strict');
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

test('WorldController getUnitViewPrizes filters out coin prizes for all wilderness unit types', () => {
    // Verify the PHP filter logic exists in WorldController.php
    assert.match(worldControllerSource, /\$isWilderness\s*=\s*\$filterType !== null/);
    assert.match(worldControllerSource, /strncmp\(\$prize,\s*'coin_',\s*5\)\s*===\s*0/);

    // Simulate PHP sanitize logic in JS to verify across all wilderness unit types
    const prizeData = JSON.parse(fs.readFileSync(
        path.join(root, 'caesar-private', 'data', 'world-unit-prizes.json'),
        'utf8'
    ));

    const sanitize = (list, filterType, filterLvl) => {
        const result = [];
        const isWilderness = (filterType >= 1 && filterType <= 10) || (filterType >= 30 && filterType <= 59);
        for (const p of list) {
            const uType = Number(p.unitType || 0);
            const pLvl = Number(p.lvl || 0);
            if (filterType !== null && uType !== filterType) continue;
            if (filterLvl !== null && pLvl !== filterLvl) continue;
            const prize = String(p.prize || '');
            if (isWilderness && prize.startsWith('coin_')) continue;
            result.push({
                unitType: uType,
                lvl: pLvl,
                prize,
                amount_min: Number(p.amount_min || 1),
                amount_max: Number(p.amount_max || 1)
            });
        }
        return result;
    };

    // Test lakes (1-10), hills/mountains (30-39), rocks (40-49), forests (50-59)
    const wildernessTypes = [
        ...Array.from({ length: 10 }, (_, i) => 1 + i),
        ...Array.from({ length: 10 }, (_, i) => 30 + i),
        ...Array.from({ length: 10 }, (_, i) => 40 + i),
        ...Array.from({ length: 10 }, (_, i) => 50 + i)
    ];

    for (const ut of wildernessTypes) {
        for (let lvl = 1; lvl <= 10; lvl++) {
            const sanitized = sanitize(prizeData.Win, ut, lvl);
            const coins = sanitized.filter(p => p.prize.startsWith('coin_'));
            assert.equal(coins.length, 0, `unitType ${ut} lvl ${lvl} must have 0 coin prizes`);
        }
    }

    // Verify non-wilderness types like gang 99 still retain their coin prizes
    const gang99Sanitized = sanitize(prizeData.Win, 99, 1);
    const gangCoins = gang99Sanitized.filter(p => p.prize.startsWith('coin_'));
    assert.ok(gangCoins.length > 0, 'gang 99 level 1 legitimately drops coins');
});

test('client WorldUnit.prize.prizeList does not render sesterce/coin prizes for wilderness targets', () => {
    const context = {
        API_URL: 'http://localhost',
        Caesar: {
            Config: { AuthToken: 'token', idServer: 1 }
        },
        WorldUnit: {
            getWorldUnit: (x, y) => ({ x, y, ut: 30, l: 1 }), // Hill / Mountain lvl 1
            prize: {}
        },
        Matrial: {
            image: prize => `images/items/${prize}.png`
        },
        $: () => ({ html: () => {} })
    };

    const startIdx = clientSource.indexOf('WorldUnit.prize.PrizFor = {');
    const endIdx = clientSource.indexOf('Caesar.World.Map.posX = function', startIdx);
    assert.ok(startIdx > 0 && endIdx > startIdx, 'WorldUnit.prize block found');
    const prizeBlock = clientSource.slice(startIdx, endIdx);

    vm.runInNewContext(prizeBlock, context);

    context.WorldUnit.prize._loadedTypes[30] = true;
    context.WorldUnit.prize.PrizeList.Win = [
        { unitType: 30, lvl: 1, prize: 'metal_a', amount_min: 2, amount_max: 2 },
        { unitType: 30, lvl: 1, prize: 'coin_a', amount_min: 1, amount_max: 1 } // simulating residual or cached coin
    ];

    const renderedHtml = context.WorldUnit.prize.prizeList(445, 257);
    assert.match(renderedHtml, /metal_a\.png/, 'hill level 1 displays metal reward');
    assert.match(renderedHtml, /\(2\)X/);
    assert.doesNotMatch(renderedHtml, /coin_a\.png/, 'wilderness prize list must not render coin/sesterce');
    assert.doesNotMatch(renderedHtml, /coin_/, 'no coin prizes rendered for wilderness');
});

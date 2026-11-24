const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

test('TradeCenter pagination logic prevents NaN under all missing or undefined conditions', () => {
    const clientJs = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );

    // 1. Verify TradeCenter.totalCount is initialized to 0
    assert.match(clientJs, /TradeCenter\.totalCount\s*=\s*0;/, 'TradeCenter.totalCount must be initialized to 0');

    // 2. Verify TradeCenter.getTradeListTotalCount safely parses item_count with default 0
    assert.match(clientJs, /TradeCenter\.totalCount\s*=\s*Number\(res\s*&&\s*res\.item_count/, 'totalCount parsing must guard undefined/null');

    // 3. Verify TradeCenter.TradeListContent safely computes page numbers
    assert.match(clientJs, /var\s+curOffset\s*=\s*Math\.max\(0,\s*Number\(offset\)\s*\|\|\s*0\);/);
    assert.match(clientJs, /var\s+totalCount\s*=\s*Math\.max\(0,\s*Number\(TradeCenter\.totalCount\)\s*\|\|\s*0\);/);
    assert.match(clientJs, /var\s+totalPages\s*=\s*Math\.max\(1,\s*Math\.ceil\(totalCount\s*\/\s*7\)\s*\|\|\s*1\);/);
    assert.match(clientJs, /var\s+currentPage\s*=\s*Math\.min\(totalPages,\s*Math\.max\(1,\s*Math\.floor\(curOffset\s*\/\s*7\)\s*\+\s*1\)\);/);
    assert.match(clientJs, /<label>\$\{currentPage\}\/\$\{totalPages\}<\/label>/);

    // 4. Verify navigation handler does not produce NaN on decrement (no missing .data('offset'))
    assert.doesNotMatch(clientJs, /var\s+firstOffset\s*=\s*\$\("#trade-list\s+\.tr:first-child"\);[\s\r\n]+if\s*\(firstOffset\s*<=\s*0\)/, 'firstOffset must not be a bare jQuery object');

    // 5. Simulate pagination calculation logic directly
    function calcPagination(offset, totalCount) {
        var curOffset = Math.max(0, Number(offset) || 0);
        var total = Math.max(0, Number(totalCount) || 0);
        var totalPages = Math.max(1, Math.ceil(total / 7) || 1);
        var currentPage = Math.min(totalPages, Math.max(1, Math.floor(curOffset / 7) + 1));
        return `${currentPage}/${totalPages}`;
    }

    assert.equal(calcPagination(undefined, undefined), '1/1');
    assert.equal(calcPagination(null, null), '1/1');
    assert.equal(calcPagination(NaN, NaN), '1/1');
    assert.equal(calcPagination(0, 0), '1/1');
    assert.equal(calcPagination(0, 7), '1/1');
    assert.equal(calcPagination(0, 8), '1/2');
    assert.equal(calcPagination(7, 8), '2/2');
    assert.equal(calcPagination(14, 21), '3/3');
    assert.equal(calcPagination(50, 8), '2/2', 'Current page is clamped to totalPages');
});

test('caesar-web/api/tradeCenter.php responds with valid JSON for GET_TOTAL_COUNT and list requests', () => {
    const apiFile = path.join(__dirname, '..', 'caesar-web', 'api', 'tradeCenter.php');
    assert.ok(fs.existsSync(apiFile), 'tradeCenter.php should exist');

    // Test GET_TOTAL_COUNT via php CLI
    const countRun = spawnSync('php', ['-r', `
        $_GET['GET_TOTAL_COUNT'] = true;
        require '${apiFile.replace(/\\/g, '/')}';
    `]);
    assert.equal(countRun.status, 0);
    const countJson = JSON.parse(countRun.stdout.toString().trim());
    assert.equal(countJson.item_count, 0, 'GET_TOTAL_COUNT must return { item_count: 0 }');

    // Test GET_FORBIDDEN_ITEM via php CLI
    const forbidRun = spawnSync('php', ['-r', `
        $_GET['GET_FORBIDDEN_ITEM'] = true;
        require '${apiFile.replace(/\\/g, '/')}';
    `]);
    assert.equal(forbidRun.status, 0);
    const forbidJson = JSON.parse(forbidRun.stdout.toString().trim());
    assert.ok(Array.isArray(forbidJson), 'GET_FORBIDDEN_ITEM must return an array');
    assert.ok(forbidJson.length > 50, 'Forbidden list should contain all restricted items');

    // Test GET_TRADE_LIST via php CLI
    const listRun = spawnSync('php', ['-r', `
        $_GET['GET_TRADE_LIST'] = true;
        require '${apiFile.replace(/\\/g, '/')}';
    `]);
    assert.equal(listRun.status, 0);
    const listJson = JSON.parse(listRun.stdout.toString().trim());
    assert.ok(Array.isArray(listJson), 'GET_TRADE_LIST must return an array');
});

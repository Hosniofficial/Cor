const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('Store (building_store) table has clean non-bold font and compact percentage input boxes', () => {
    const cssDir = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113');
    const finishingCss = fs.readFileSync(path.join(cssDir, 'finishing.css'), 'utf8');
    const lastOfLastCss = fs.readFileSync(path.join(cssDir, 'lastOfLast.css'), 'utf8');
    const overridesBundleCss = fs.readFileSync(path.join(cssDir, 'overrides.bundle.css'), 'utf8');
    const clientJs = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );

    // 1. Verify CSS styles in finishing.css
    assert.match(finishingCss, /\.building_store\s+\.stat-table[\s\S]*?font-weight:\s*normal;/, 'Store table must use normal font weight');
    assert.match(finishingCss, /\.building_store\s+\.stat-table\s+ul\s+\.stor-percent\s+input[\s\S]*?width:\s*48px;/, 'Input must be compact (48px)');
    assert.match(finishingCss, /\.building_store\s+\.stat-table\s+ul\s+\.stor-percent\s+input[\s\S]*?height:\s*23px;/, 'Input must have controlled height (23px)');

    // 2. Verify overrides in lastOfLast.css
    assert.match(lastOfLastCss, /\.building_store\s+\.stat-table[\s\S]*?font-weight:\s*normal\s*!important;/, 'Store table font-weight normal must be enforced');
    assert.match(lastOfLastCss, /\.building_store\s+\.stat-table\s+ul\s+\.stor-percent\s+input[\s\S]*?width:\s*48px\s*!important;/, 'Compact input width must be enforced');

    // 3. Verify compiled bundle overrides.bundle.css
    assert.match(overridesBundleCss, /\.building_store\s+\.stat-table[\s\S]*?font-weight:\s*normal\s*!important;/, 'Overrides bundle must contain store font rules');
    assert.match(overridesBundleCss, /\.building_store\s+\.stat-table\s+ul\s+\.stor-percent\s+input[\s\S]*?width:\s*48px\s*!important;/, 'Overrides bundle must contain store input rules');

    // 4. Verify client JS store table markup
    assert.match(clientJs, /<li\s+class="store-row">[\s\S]*?cap-food-input/, 'Store rows must use clean store-row class');
    assert.doesNotMatch(clientJs, /<li\s+class="font-2">[\s\S]*?cap-food-input/, 'Store rows must not use bulky font-2 class');
    assert.doesNotMatch(clientJs, /<li\s+class="font-2\s+total-storage">/, 'Total storage must not use bulky font-2 class');
});

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('House of Help (Worship) helper selection buttons have responsive font, flex layout, and non-overflowing text', () => {
    const cssDir = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113');
    const lastOfLastCss = fs.readFileSync(path.join(cssDir, 'lastOfLast.css'), 'utf8');
    const overridesBundleCss = fs.readFileSync(path.join(cssDir, 'overrides.bundle.css'), 'utf8');
    const clientJs = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );

    // 1. Verify CSS styles in lastOfLast.css
    assert.match(lastOfLastCss, /#helper-choose\s+li[\s\S]*?display:\s*flex;/, 'li must use flex layout');
    assert.match(lastOfLastCss, /#helper-choose\s+li\s+h1[\s\S]*?font-size:\s*12\.5px/, 'h1 must have reduced font size');
    assert.match(lastOfLastCss, /#helper-choose\s+li\s+h1[\s\S]*?overflow:\s*hidden/, 'h1 must prevent text overflow');
    assert.match(lastOfLastCss, /#helper-choose\s+li\s+h1[\s\S]*?text-overflow:\s*ellipsis/, 'h1 must use ellipsis for long text');
    assert.match(lastOfLastCss, /#helper-choose\s+\.selected[\s\S]*?select-row\.png/, 'selected helper must have highlight image');

    // 2. Verify compiled overrides bundle
    assert.match(overridesBundleCss, /#helper-choose\s+li\s+h1[\s\S]*?font-size:\s*12\.5px/, 'overrides bundle must contain updated font size');
    assert.match(overridesBundleCss, /#helper-choose\s+li\s+h1[\s\S]*?text-overflow:\s*ellipsis/, 'overrides bundle must contain ellipsis');

    // 3. Verify caesar-client.js structure for all three helpers
    assert.match(clientJs, /<li\s+index="0"\s+helper-type="1"[\s\S]*?المساعد الاول/);
    assert.match(clientJs, /<li\s+index="1"\s+helper-type="2"[\s\S]*?المساعد الثانى/);
    assert.match(clientJs, /<li\s+index="2"\s+helper-type="3"[\s\S]*?المساعد الثالث/);
    assert.doesNotMatch(clientJs, /<h1\s+class="header-1">[\s\S]*?المساعد الثالث/, 'Helper 3 must not use inconsistent header-1 class');
});

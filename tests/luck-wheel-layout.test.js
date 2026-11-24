const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const clientJsPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js');
const lastOfLastCssPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastOfLast.css');
const finishingCssPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'finishing.css');
const overridesBundlePath = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'overrides.bundle.css');

test('luck wheel control panel is geometrically centered and does not offset to top-left', () => {
    const lastOfLast = fs.readFileSync(lastOfLastCssPath, 'utf8');
    const overrides = fs.readFileSync(overridesBundlePath, 'utf8');

    // Control should be centered at 50% / 50% with translate(-50%, -50%)
    assert.match(lastOfLast, /#luck-wheel\s+\.control\s*\{[^}]*top:\s*50%/i);
    assert.match(lastOfLast, /#luck-wheel\s+\.control\s*\{[^}]*left:\s*50%/i);
    assert.match(lastOfLast, /#luck-wheel\s+\.control\s*\{[^}]*transform:\s*translate\(-50%,\s*-50%\)/i);

    // Old conflicting top: 20% / left: 21% must be removed
    assert.doesNotMatch(lastOfLast, /#luck-wheel\s+\.control\s*\{[^}]*top:\s*20%/i);
    assert.doesNotMatch(lastOfLast, /#luck-wheel\s+\.control\s*\{[^}]*left:\s*21%/i);

    // Overrides bundle should carry the centered rule
    assert.match(overrides, /#luck-wheel\s+\.control\s*\{[^}]*top:\s*50%/i);
});

test('luck wheel client does not corrupt luck-wheel-btn inline position upon show or hide', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');

    // LuckWheel.hide must not animate luck-wheel-btn to hardcoded 213px
    assert.doesNotMatch(clientJs, /\$\("#luck-wheel-btn"\)\.animate\(\{\s*bottom:\s*"213px"/);

    // luck-wheel-btn click must not animate bottom: 180px
    assert.doesNotMatch(clientJs, /\$\("#luck-wheel-btn"\)\.off\("click"\)\.on\("click",\s*function\([^)]*\)\s*\{[\s\S]*?animate\(\{\s*bottom:\s*"180px"/);

    // LuckWheel.show must clean up existing overlays
    assert.match(clientJs, /LuckWheel\.show\s*=\s*function\(\)\s*\{[\s\S]*?\$\("#luck-wheel-over-lay"\)\.remove\(\);/);
});

test('luck-wheel-btn coordinates match the action icons row across responsive breakpoints', () => {
    const finishingCss = fs.readFileSync(finishingCssPath, 'utf8');
    const lastOfLastCss = fs.readFileSync(lastOfLastCssPath, 'utf8');

    // Base position should align next to PlayerTeamWrapper at bottom: 212px
    assert.match(finishingCss, /#luck-wheel-btn\s*\{[^}]*bottom:\s*212px;/);
    assert.match(finishingCss, /#luck-wheel-btn\s*\{[^}]*left:\s*270px;/);

    // Under max-width 760px / max-height 650px: bottom 160px, scale 0.78
    assert.match(lastOfLastCss, /#luck-wheel-btn\s*\{[^}]*bottom:\s*160px;[\s\S]*?transform:\s*scale\(0?\.78\);/);

    // Under max-width 600px: bottom 144px, scale 0.70
    assert.match(lastOfLastCss, /#luck-wheel-btn\s*\{[^}]*bottom:\s*144px;[\s\S]*?transform:\s*scale\(0?\.70\);/);

    // Under max-width 460px: bottom 134px, scale 0.65
    assert.match(lastOfLastCss, /#luck-wheel-btn\s*\{[^}]*bottom:\s*134px;[\s\S]*?transform:\s*scale\(0?\.65\);/);
});

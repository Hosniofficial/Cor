const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const clientJsPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js');
const worldCssPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'world.css');
const lastStyleCssPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastStyle.css');
const gameplayBundlePath = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'gameplay.bundle.css');
const overridesBundlePath = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'overrides.bundle.css');

test('world map unit popup renders clean coord-info layout without inline margin hacks', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');

    // Check WorldUnit.getSmallBox
    assert.match(
        clientJs,
        /<div class="coord-info">\s*<div class="coords"[^>]*>\[\$\{y_coord\} , \$\{x_coord\}\]<\/div>\s*<div class="mile"[^>]*>\$\{getDistance\(x_coord, y_coord\)\} ميل<\/div>\s*<\/div>/,
        'WorldUnit.getSmallBox must wrap coords and mile inside coord-info'
    );

    // Check empty field review
    assert.match(
        clientJs,
        /<div class="coord-info">\s*<div class="coords"[^>]*>\[\$\{Unit\.y\} , \$\{Unit\.x\}\]<\/div>\s*<div class="mile"[^>]*>\$\{getDistance\(Unit\.x, Unit\.y\)\} ميل<\/div>\s*<\/div>/,
        'Empty field review must wrap coords and mile inside coord-info'
    );

    // Confirm inline margin hack is removed from dist
    assert.doesNotMatch(
        clientJs,
        /<div class="coords" style="margin-right:\s*37px">/,
        'Inline margin-right: 37px must be removed from coords'
    );
});

test('world map popup coords and distance have reduced font size and clean flex styling', () => {
    const worldCss = fs.readFileSync(worldCssPath, 'utf8');
    const lastStyleCss = fs.readFileSync(lastStyleCssPath, 'utf8');
    const gameplayBundle = fs.readFileSync(gameplayBundlePath, 'utf8');
    const overridesBundle = fs.readFileSync(overridesBundlePath, 'utf8');

    // Font size should be reduced (12px for coords, 11px for mile)
    assert.match(worldCss, /#unit_review \.right_cont \.dist \.coords[^{]*\{[^}]*font-size:\s*12px;/);
    assert.match(worldCss, /#unit_review \.right_cont \.dist \.mile[^{]*\{[^}]*font-size:\s*11px;/);

    // Banner padding to clear scroll ribbons cleanly
    assert.match(worldCss, /#unit_review \.right_cont \.dist\s*\{[^}]*padding:\s*0 34px 0 28px;/);
    assert.match(worldCss, /#unit_review \.right_cont \.dist\s*\{[^}]*display:\s*flex;/);

    // Overrides bundle must carry the styling
    assert.match(overridesBundle, /#unit_review \.right_cont \.dist \.coords/);
    assert.match(overridesBundle, /#unit_review \.right_cont \.dist \.mile/);
    assert.match(gameplayBundle, /#unit_review \.right_cont \.dist \.coord-info/);
});

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('palace contract builders button has reduced font size, non-overflowing text styling and enhanced container', () => {
    const cssDir = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113');
    const cityCss = fs.readFileSync(path.join(cssDir, 'city.css'), 'utf8');
    const lastOfLastCss = fs.readFileSync(path.join(cssDir, 'lastOfLast.css'), 'utf8');
    const gameplayBundleCss = fs.readFileSync(path.join(cssDir, 'gameplay.bundle.css'), 'utf8');
    const overridesBundleCss = fs.readFileSync(path.join(cssDir, 'overrides.bundle.css'), 'utf8');
    const clientJs = fs.readFileSync(path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'), 'utf8');

    // 1. Verify CSS rules in source files
    assert.match(cityCss, /#add-motiv-palace[\s\S]*?font-size:\s*11px/, 'city.css should define font-size 11px for #add-motiv-palace');
    assert.match(cityCss, /#add-motiv-palace[\s\S]*?overflow:\s*hidden/, 'city.css should define overflow hidden for #add-motiv-palace');
    assert.match(cityCss, /#add-motiv-palace[\s\S]*?text-overflow:\s*ellipsis/, 'city.css should define ellipsis for #add-motiv-palace');
    assert.match(cityCss, /#palace_content\s+\.left-content\s+\.btn_2[\s\S]*?width:\s*84%/, 'city.css should enhance btn_2 width inside palace left-content');

    assert.match(lastOfLastCss, /#add-motiv-palace[\s\S]*?font-size:\s*11px\s*!important/, 'lastOfLast.css should enforce font-size 11px with !important');
    assert.match(lastOfLastCss, /#add-motiv-palace[\s\S]*?text-overflow:\s*ellipsis\s*!important/, 'lastOfLast.css should enforce ellipsis with !important');
    assert.match(lastOfLastCss, /#palace_content\s+\.left-content\s+\.btn_2[\s\S]*?width:\s*84%\s*!important/, 'lastOfLast.css should enforce btn_2 width with !important');

    // 2. Verify compiled bundles have the rules
    assert.match(gameplayBundleCss, /#add-motiv-palace[\s\S]*?font-size:\s*11px/, 'gameplay.bundle.css must include the button styles');
    assert.match(overridesBundleCss, /#add-motiv-palace[\s\S]*?font-size:\s*11px\s*!important/, 'overrides.bundle.css must include the override styles');

    // 3. Verify client JS Palace markup
    assert.match(clientJs, /id="add-motiv-palace"\s+title=/, 'caesar-client.js should provide title attribute on #add-motiv-palace for clean accessibility/tooltip');
    assert.match(clientJs, /Translate\.Button\.Building\.ContractBuilders\[UserLag\.language\]/, 'caesar-client.js should use ContractBuilders translation');
});

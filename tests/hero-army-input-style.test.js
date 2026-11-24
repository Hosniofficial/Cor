const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('Hero army input dialog has compact, coordinated styling in CSS bundles', () => {
    const finishingCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'finishing.css'),
        'utf8'
    );
    const overridesBundleCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'overrides.bundle.css'),
        'utf8'
    );

    for (const css of [finishingCss, overridesBundleCss]) {
        // Must contain centered flex row-2
        assert.ok(css.includes('.alert_for_hero_trade .row-2'), 'Contains .alert_for_hero_trade .row-2 rule');
        assert.ok(css.includes('display: flex'), 'row-2 uses flex layout');
        assert.ok(css.includes('justify-content: center'), 'row-2 centers elements');

        // Must define compact, neat styling for #input-army-move
        assert.ok(css.includes('.alert_for_hero_trade #input-army-move'), 'Contains .alert_for_hero_trade #input-army-move rule');
        assert.ok(css.includes('height: 26px !important'), 'input has compact 26px height');
        assert.ok(css.includes('text-align: center'), 'input text is centered');

        // Must define aligned arrow wrapper
        assert.ok(css.includes('.alert_for_hero_trade .number-arrow-wrapper'), 'Contains .alert_for_hero_trade .number-arrow-wrapper rule');
        assert.ok(css.includes('margin-left: -23px !important'), 'number arrows sit neatly within input padding');

        // Must define row-1 slider layout
        assert.ok(css.includes('.alert_for_hero_trade .row-1'), 'Contains .alert_for_hero_trade .row-1 rule');
    }
});

test('caesar-client.js defines hero army trade dialog cleanly without conflicting inline margins or floats', () => {
    const clientJs = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );

    // Check alert_content_army definition
    assert.ok(clientJs.includes('alert_content_army'), 'alert_content_army exists in client JS');
    assert.ok(!clientJs.includes('id="input-army-move"  class="amount-input only_num input numeric pull-L"'), 'No pull-L float on input-army-move');
    assert.ok(!clientJs.includes('style="margin-left: -31px; margin-top: 4.5px;"'), 'No hardcoded inline margins on number-arrow-wrapper in hero trade');
    assert.ok(!clientJs.includes('style="line-height: 38px;"'), 'No hardcoded 38px line-height on max label in hero trade');

    // Check StartDialogBox definition
    assert.ok(clientJs.includes('Caesar.HeroArmy.StartDialogBox'), 'Caesar.HeroArmy.StartDialogBox exists in client JS');

    // Check real-time range slider synchronization
    assert.ok(clientJs.includes('$(document).on("keydown keyup input change", ".amount-input"'), 'amount-input syncs on keydown, keyup, input, and change');
});

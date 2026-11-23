const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const adminSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-admin', 'index.php'),
    'utf8'
);

test('battle analyzer escapes report data before rendering HTML', () => {
    const helper = adminSource.match(/function escapeBattleHtml\(value\) \{[\s\S]*?\n\}/);
    assert.ok(helper, 'battle analyzer should define its HTML escaping helper');

    const sandbox = { module: { exports: {} } };
    vm.runInNewContext(`${helper[0]}; module.exports = escapeBattleHtml;`, sandbox);
    assert.equal(
        sandbox.module.exports(`<img src=x onerror="alert('x')">&`),
        '&lt;img src=x onerror=&quot;alert(&#39;x&#39;)&quot;&gt;&amp;'
    );

    assert.doesNotMatch(
        adminSource,
        /\$\{(?:b\.(?:hero_name|enemy_name)|v\.(?:title|summary)|data\.(?:time_stamp|player\.name|attacker_hero\.name|defender_hero\.name)|r\.(?:def_detail|atk_detail|raw_text)|json\.message)\}/
    );
});

test('battle verdict separates report-owner outcome from winning side', () => {
    const renderer = adminSource.match(/function renderBattleVerdict\(data\) \{[\s\S]*?\n\}/);
    assert.ok(renderer, 'battle analyzer should define its verdict renderer');

    const card = { style: {}, innerHTML: '' };
    const sandbox = {
        document: { getElementById: () => card },
        escapeBattleHtml: value => String(value),
    };
    vm.runInNewContext(`${renderer[0]}; renderBattleVerdict`, sandbox);
    sandbox.renderBattleVerdict({
        player_won: true,
        attacker_won: false,
        report_id: 1,
        time_stamp: 'now',
        coords: { x: 0, y: 0 },
        verdict: { title: 'انتصار صاحب التقرير', summary: 'فاز المدافع' },
    });

    assert.match(card.style.borderColor, /emerald/);
    assert.match(card.innerHTML, /انتصار المدافع/);
});

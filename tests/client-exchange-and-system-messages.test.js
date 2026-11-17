const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

function extractFunction(startMarker, endMarker, endOffset = 0) {
    const start = clientSource.indexOf(startMarker);
    const end = clientSource.indexOf(endMarker, start);
    assert.ok(start >= 0 && end > start, `Could not locate ${startMarker}`);
    return clientSource.slice(start, end + endOffset);
}

test('exchange rank is checked by the server, not stale client rank data', () => {
    const requests = [];
    const alerts = [];
    const exchange = {
        id_ex: 1,
        player_porm: 8,
        req: '[]',
        reword: JSON.stringify({ type: 'matrial', matrial: 'gift', amount: 1 }),
        max_to_have: 10
    };
    const context = {
        API_URL: 'http://localhost',
        EXCHANGE_ITEM: [exchange],
        Caesar: {
            DPlayer: { Player: { porm: 3 } },
            CurrentCity: { City: { id_city: 42 } },
            Config: { AuthToken: 'token', idServer: 1 },
            LBase: { isJson: value => {
                try {
                    JSON.parse(value);
                    return true;
                } catch (error) {
                    return false;
                }
            } }
        },
        Matrial: { getPlayerAmount: () => 0 },
        alert_box: { confirmMessage: message => alerts.push(message) },
        waitCursor() {},
        unwaitCursor() {},
        $: selector => ({
            val: () => selector === '#amount_to_trade' ? '1' : '',
            attr() { return this; },
            click() { return this; }
        }),
        jQuery: null,
        console
    };
    context.$.ajax = options => requests.push(options);

    vm.runInNewContext(
        extractFunction('function buyTradeMatral(index) {', '\n}\n\n//<button', 2),
        context
    );
    context.buyTradeMatral(1);

    assert.equal(requests.length, 1);
    assert.equal(requests[0].data.idExchange, 1);
    requests[0].error({ responseText: JSON.stringify({ state: 'error_porm' }) });
    assert.deepEqual(alerts, ['رتبتك الحالية لا تسمح بهذا التبادل']);
});

test('system event messages render troop icons, counts, and escaped names', () => {
    const context = {
        Caesar: {
            CurrentCity: { City: { id_city: 7, name: 'روما' } },
            DPlayer: {
                Heros: [{
                    Hero: { id_hero: 19, id_city: 7, name: 'قائد', avatar: 2, loyal: 76, in_city: 1 }
                }]
            },
            BaseData: {
                HeroAvatar: [
                    'images/hero/faceA1.jpg',
                    'images/hero/faceA2.jpg',
                    'images/hero/faceA3.jpg'
                ],
                Army: {
                    army_a: { image: 'images/tech/soldier01.jpg', ar_title: 'مشاة' },
                    army_b: { image: 'images/tech/soldier02.jpg', ar_title: 'رماة' }
                }
            }
        },
        escapeHtml: value => String(value).replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[character]),
        extractUrl: value => `legacy:${value}`
    };
    vm.runInNewContext(
        extractFunction('function parseSystemMessageAmount(value) {', '\n\nvar message = {'),
        context
    );

    const desertion = context.renderSystemEventMessageBody(JSON.stringify({
        format: 'system_event_v1',
        type: 'desertion',
        cityName: '<مدينة>',
        total: 12,
        units: [{ type: 'army_a', name: 'مشاة', amount: 12 }]
    }));
    assert.match(desertion, /images\/tech\/soldier01\.jpg/);
    assert.match(desertion, /12/);
    assert.match(desertion, /&lt;مدينة&gt;/);

    const loyalty = context.renderSystemEventMessageBody(JSON.stringify({
        format: 'system_event_v1',
        type: 'hero_loyalty',
        cityName: 'روما',
        heroes: [{ name: '<قائد>', avatar: 2, loyalty: 0, deserted: true }]
    }));
    assert.match(loyalty, /images\/hero\/faceA3\.jpg/);
    assert.match(loyalty, /&lt;قائد&gt;/);
    assert.match(loyalty, /غادر المدينة لانعدام الولاء/);

    const legacyDesertion = context.renderSystemEventMessageBody(
        'نفد مخزون الغذاء في مدينة [روما]. هرب 35 جندي.\n\nتفاصيل القوات الهاربة:\n- مشاة خفيف: 20 مقاتل\n- رماة سهام: 15 مقاتل\n\nسارع بتوفير الغذاء',
        'هروب القوات بسبب نقص الغذاء في مدينة [روما]'
    );
    assert.match(legacyDesertion, /images\/tech\/soldier01\.jpg/);
    assert.match(legacyDesertion, /images\/tech\/soldier02\.jpg/);

    const legacyLoyalty = context.renderSystemEventMessageBody(
        'نفد مخزون الذهب في مدينة [روما] مما أدى إلى تراجع ولائهم بمقدار نقطتين.',
        'انخفاض معنويات وولاء الأبطال في مدينة [روما]'
    );
    assert.match(legacyLoyalty, /images\/hero\/faceA3\.jpg/);
    assert.match(legacyLoyalty, /الولاء الحالي/);
    assert.match(legacyLoyalty, /لا تحفظ حالة الأبطال وقت الحدث/);

    assert.equal(
        context.renderSystemEventMessageBody('رسالة قديمة', 'موضوع آخر'),
        '<p class="selectable">legacy:رسالة قديمة</p>'
    );
});

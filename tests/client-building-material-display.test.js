const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

test('building downgrade does not require builders and refreshes the returned building level', () => {
    const start = clientSource.indexOf('/*DOWNGRADE BULDING*/');
    const end = clientSource.indexOf('/*downgradde building*/', start);
    assert.ok(start >= 0 && end > start, 'building downgrade handler should exist');
    const handler = clientSource.slice(start, end);

    assert.doesNotMatch(handler, /upgrading_num|motiv_60|motiv_7/);
    assert.match(handler, /json_data\.building\.lvl/);
    assert.match(handler, /تعذر الاتصال بالخادم لخفض مستوى المبنى/);
});

test('production rates render negative values with one minus sign', () => {
    const start = clientSource.indexOf('Animation.cityProductionRate = function() {');
    const end = clientSource.indexOf('\nAnimation.FarmAnimation = function()', start);
    assert.ok(start >= 0 && end > start, 'city production rate animation should exist');

    const values = [];
    const context = {
        Animation: {
            ProductioRats: [],
            ProductionTweens: [],
            PluseProFontStyle: {},
            MinusProFontStyle: []
        },
        Caesar: {
            CurrentCity: {
                City: {
                    coin_in: 0, coin_out: 1800,
                    food_in: 0, food_out: 3600,
                    wood_in: 0, wood_out: 5400,
                    stone_in: 0,
                    metal_in: 0
                }
            },
            GE: {
                CityScene: {
                    add: {
                        text: (x, y, value) => {
                            values.push(value);
                            return { setDepth() { return this; }, setOrigin() { return this; } };
                        },
                    },
                    tweens: { add: () => ({ remove() {} }) }
                }
            }
        },
        BuildingOnFloor: {
            palace: { x: 0, y: 0 },
            farm: { x: 0, y: 0 },
            wood: { x: 0, y: 0 },
            stone: { x: 0, y: 0 },
            mine: { x: 0, y: 0 }
        },
        X_GRID: 1,
        Y_GRID: 1
    };
    vm.runInNewContext(clientSource.slice(start, end), context);
    context.Animation.cityProductionRate();

    assert.deepEqual(values, ['-1', '-2', '-3', '+0', '+0']);
});

test('quest tab labels do not contain a literal n after the notification element', () => {
    const start = clientSource.indexOf('NavBar.Quest = [');
    const end = clientSource.indexOf('\nNavBar.Mail =', start);
    assert.ok(start >= 0 && end > start, 'quest navigation labels should exist');
    assert.doesNotMatch(clientSource.slice(start, end), /<\/div>n\s*<lable>/);
});

test('mall purchase rejects quantities above the remaining item limit', () => {
    const start = clientSource.indexOf('function buyMatrial(matrial, amount) {');
    const end = clientSource.indexOf('\n\n\n\nfunction successfulUse', start);
    assert.ok(start >= 0 && end > start, 'mall purchase handler should exist');

    const alerts = [];
    let ajaxCalls = 0;
    const context = {
        Caesar: {
            BaseData: { Items: { gift_box: { gold: 80, prizeLimit: 3 } } },
            DPlayer: { Player: { gold: 1000 } },
            Config: {}
        },
        Matrial: { getPlayerAmount: () => 2 },
        isInt: Number.isInteger,
        getArabicNumbers: String,
        alert_box: { failMessage: message => alerts.push(message) },
        $: () => ({ val: () => '1' }),
        WS_HOST: 'localhost',
        WS_PORT: 8092
    };
    context.$.ajax = () => { ajaxCalls++; };
    vm.runInNewContext(clientSource.slice(start, end), context);

    context.buyMatrial('gift_box', 2);
    assert.equal(ajaxCalls, 0);
    assert.match(alerts[0], /المتاحة للشراء/);

    context.buyMatrial('gift_box', 1);
    assert.equal(ajaxCalls, 1);
});

test('exchange checks the full reward quantity against the maximum', () => {
    assert.match(
        clientSource,
        /Number\(Matrial\.getPlayerAmount\(reword\.matrial\)\)\s*\+\s*Number\(reword\.amount\)\s*\*\s*amountToTrade/
    );
    assert.match(
        clientSource,
        /Number\(Equipment\.getPlayerAmount\(reword\.Equip, reword\.Part, reword\.lvl\)\)\s*\+\s*Number\(reword\.amount\)\s*\*\s*amountToTrade/
    );
});

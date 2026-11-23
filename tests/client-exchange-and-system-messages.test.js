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
test('exchange dialog hides the buy action when combined requirements are insufficient', () => {
    let dialogHandler;
    let renderedDialog = '';
    const offer = {
        id_ex: 2,
        player_max: 5,
        take_times: 0,
        max_to_have: 10,
        req: JSON.stringify([
            { type: 'resource', resource_type: 'coin', amount: 4 },
            { type: 'resource', resource_type: 'coin', amount: 4 }
        ]),
        reword: JSON.stringify({ type: 'matrial', matrial: 'gift', amount: 1 })
    };
    const document = {};
    let ownedGiftAmount = 10;
    const context = {
        document,
        API_URL: 'http://localhost',
        EXCHANGE_ITEM: [offer],
        RESOURCE_IMAGE: { coin: 'coin.png' },
        Caesar: { CurrentCity: { City: { coin: 7 } } },
        Matrial: {
            image: () => 'gift.png',
            getMatrialName: () => 'Gift',
            getPlayerAmount: () => ownedGiftAmount,
            getMatrial: () => ({ long_desc: 'Gift description' })
        },
        Equipment: {},
        Translate: { Button: { MenuList: { Buy: { en: 'Buy' } } } },
        UserLag: { language: 'en' },
        escapeHtml: String,
        $: function(target) {
            if (target === document) {
                return {
                    on(_event, selector, handler) {
                        if (selector === '#dialg_box .for_mat_trade .matrial_unit') {
                            dialogHandler = handler;
                        }
                    }
                };
            }
            if (target === 'body') {
                return { append: html => { renderedDialog = html; } };
            }
            return {
                attr: name => name === 'matrial_type' ? 'gift' : undefined,
                data: name => name === 'index' ? 2 : undefined
            };
        }
    };
    const start = clientSource.indexOf('$(document).on("click", "#dialg_box .for_mat_trade .matrial_unit", function() {');
    const end = clientSource.indexOf('\n});\n\n\nfunction buyTradeMatral', start);
    assert.ok(start >= 0 && end > start, 'exchange dialog handler should exist');
    const availabilityStart = clientSource.indexOf('function getExchangePurchaseAvailability(exchange, requirements, reward, amount) {');
    const availabilityEnd = clientSource.indexOf('\n}\n\n$(document).on("click", "#dialg_box', availabilityStart);
    assert.ok(availabilityStart >= 0 && availabilityEnd > availabilityStart, 'exchange availability check should exist');
    vm.runInNewContext(clientSource.slice(availabilityStart, availabilityEnd + 2), context);
    vm.runInNewContext(clientSource.slice(start, end + 4), context);

    dialogHandler.call({});
    assert.doesNotMatch(renderedDialog, /id="buyTradeItem"/);
    assert.match(renderedDialog, /لا تمتلك كمية كافية/);
    assert.match(renderedDialog, /وصلت إلى الحد الأقصى للجائزة/);
    assert.match(renderedDialog, /class="confim-btn has-purchase-status"/);
    assert.ok(
        renderedDialog.indexOf('لا تمتلك كمية كافية') < renderedDialog.indexOf('id="amount_to_trade"'),
        'exchange warning should precede the quantity control in the same row'
    );

    context.Caesar.CurrentCity.City.coin = 8;
    ownedGiftAmount = 0;
    dialogHandler.call({});
    assert.match(renderedDialog, /id="buyTradeItem"/);
});

test('exchange purchase-limit warning is visible without scrolling and aligned in RTL', () => {
    const styles = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastOfLast.css'),
        'utf8'
    );
    const dialogRule = styles.match(
        /#matral-box-use \.matrial-trade-show \.row-3 \.confim-btn\.has-purchase-status\s*\{([^}]*)\}/
    );
    assert.ok(dialogRule, 'exchange dialog confirmation area should have a scoped style');
    assert.match(dialogRule[1], /display:\s*flex/);
    assert.match(dialogRule[1], /align-items:\s*center/);
    assert.match(dialogRule[1], /height:\s*auto/);
    assert.match(dialogRule[1], /overflow:\s*visible/);
    assert.match(dialogRule[1], /direction:\s*rtl/);
    assert.match(dialogRule[1], /justify-content:\s*space-between/);

    const statusRule = styles.match(
        /#matral-box-use \.matrial-trade-show \.confim-btn\.has-purchase-status \.exchange-purchase-status\s*\{([^}]*)\}/
    );
    assert.ok(statusRule, 'status message should have scoped alignment');
    assert.match(statusRule[1], /text-align:\s*right/);

    const wrapperRule = styles.match(
        /#matral-box-use \.matrial-trade-show \.confim-btn\.has-purchase-status \.trade-amount-wrapper\s*\{([^}]*)\}/
    );
    assert.ok(wrapperRule, 'trade amount wrapper should maintain LTR controls');
    assert.match(wrapperRule[1], /direction:\s*ltr/);
    assert.match(wrapperRule[1], /display:\s*inline-flex/);
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
                    army_b: { image: 'images/tech/soldier02.jpg', ar_title: 'فرسان' },
                    army_c: { image: 'images/tech/soldier03.jpg', ar_title: 'مدرعون' },
                    army_d: { image: 'images/tech/soldier04.jpg', ar_title: 'رماة سهام' },
                    army_e: { image: 'images/tech/soldier05.jpg', ar_title: 'مقاليع' },
                    army_f: { image: 'images/tech/soldier06.jpg', ar_title: 'مجانيق' }
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
    assert.match(legacyDesertion, /images\/tech\/soldier04\.jpg/);

    const oldMismatchedDesertion = context.renderSystemEventMessageBody(JSON.stringify({
        format: 'system_event_v1',
        type: 'desertion',
        cityName: 'روما',
        total: 50,
        units: [
            { type: 'army_b', name: 'رماة سهام', amount: 30 },
            { type: 'army_d', name: 'مدرعون', amount: 20 }
        ]
    }));
    assert.match(oldMismatchedDesertion, /images\/tech\/soldier02\.jpg/);
    assert.match(oldMismatchedDesertion, /فرسان/);
    assert.match(oldMismatchedDesertion, /images\/tech\/soldier04\.jpg/);
    assert.match(oldMismatchedDesertion, /رماة سهام/);

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

    const styles = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastStyle.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const listStyle = styles.match(/\.system-event-message__list\s*\{([\s\S]*?)\}/);
    assert.ok(listStyle, 'system event message list should have grid styling');
    assert.match(listStyle[1], /grid-template-columns:\s*repeat\(auto-fill,\s*minmax\(230px,\s*1fr\)\)/);
    assert.match(listStyle[1], /direction:\s*rtl/);

    const rowStyle = styles.match(/\.system-event-message__row\s*\{([\s\S]*?)\}/);
    assert.ok(rowStyle, 'system event message row should have card styling');
    assert.match(rowStyle[1], /display:\s*flex/);
    assert.match(rowStyle[1], /border:/);
});

test('inbox and outbox message headers display date and subject on a single line without wrapping', () => {
    assert.match(clientSource, /id="msg_income_detail"[\s\S]*?class="msg-meta-bar"/);
    assert.match(clientSource, /id="msg_out_detail"[\s\S]*?class="msg-meta-bar"/);
    assert.match(clientSource, /class="msg-meta-bar__date"[\s\S]*?<span>التاريخ :<\/span>/);
    assert.match(clientSource, /class="msg-meta-bar__subject"[\s\S]*?<span>الموضوع :<\/span>/);

    const styles = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastStyle.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');

    const metaBar = styles.match(/#msg_income_detail \.upper ol\.msg-meta-bar,\s*#msg_out_detail \.upper ol\.msg-meta-bar\s*\{([\s\S]*?)\}/);
    assert.ok(metaBar, 'message meta bar should have scoped flex styling');
    assert.match(metaBar[1], /display:\s*flex/);
    assert.match(metaBar[1], /direction:\s*rtl/);
    assert.match(metaBar[1], /overflow:\s*hidden/);

    const dateCol = styles.match(/#msg_income_detail \.upper ol\.msg-meta-bar \.msg-meta-bar__date,\s*#msg_out_detail \.upper ol\.msg-meta-bar \.msg-meta-bar__date\s*\{([\s\S]*?)\}/);
    assert.ok(dateCol, 'date column should have dedicated non-wrapping width');
    assert.match(dateCol[1], /flex:\s*0\s+0\s+240px/);

    const subjectCol = styles.match(/#msg_income_detail \.upper ol\.msg-meta-bar \.msg-meta-bar__subject,\s*#msg_out_detail \.upper ol\.msg-meta-bar \.msg-meta-bar__subject\s*\{([\s\S]*?)\}/);
    assert.ok(subjectCol, 'subject column should expand to available space');
    assert.match(subjectCol[1], /flex:\s*1\s+1\s+auto/);

    const spanText = styles.match(/#msg_income_detail \.upper ol\.msg-meta-bar li span:last-child,\s*#msg_out_detail \.upper ol\.msg-meta-bar li span:last-child\s*\{([\s\S]*?)\}/);
    assert.ok(spanText, 'meta text values should prevent line wrapping');
    assert.match(spanText[1], /white-space:\s*nowrap/);
    assert.match(spanText[1], /text-overflow:\s*ellipsis/);
});

test('battle report prize row places rounds and honor on the right and prizes on the left', () => {
    assert.match(
        clientSource,
        /<div class="prize-row flex">[\s\S]*?<p class="battle-report-rounds-honor">جولات:[\s\S]*?<\/ul>/,
        'markup should place rounds and honor ahead of the prize list'
    );

    const styles = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastStyle.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');

    const prizeRow = styles.match(/\.prize-row\s*\{([\s\S]*?)\}/);
    assert.ok(prizeRow, 'prize-row should have flex layout');
    assert.match(prizeRow[1], /display:\s*flex/);
    assert.match(prizeRow[1], /direction:\s*rtl/);
    assert.match(prizeRow[1], /justify-content:\s*space-between/);

    const roundsHonor = styles.match(/\.prize-row p\s*\{([\s\S]*?)\}/);
    assert.ok(roundsHonor, 'rounds and honor text should be pinned to the right side');
    assert.match(roundsHonor[1], /order:\s*1/);
    assert.match(roundsHonor[1], /text-align:\s*right/);

    const prizeList = styles.match(/\.prize-row ul\s*\{([\s\S]*?)\}/);
    assert.ok(prizeList, 'prizes list should be ordered to the left side');
    assert.match(prizeList[1], /order:\s*2/);
});

test('battle report subject panel uses the exchange panel frame background', () => {
    const reportStyles = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastStyle.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const reportPanel = reportStyles.match(
        /\.for_report #reports_list\s*\{([\s\S]*?)\}/
    );

    assert.ok(reportPanel, 'report subject panel should have a dedicated frame style');
    assert.match(reportPanel[1], /background-image:\s*url\(\.\.\/images\/background\/back-shape\/left-content\.png\)/);
    assert.match(reportPanel[1], /background-size:\s*100%\s+100%/);
    assert.match(reportPanel[1], /border:\s*6px\s+solid\s+transparent/);
    assert.match(reportPanel[1], /background-clip:\s*border-box/);
});

test('guild information panel uses the exchange panel frame background', () => {
    const styles = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastStyle.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const guildFrame = styles.match(
        /#guild-guild_data\.for_guild \.left-content \.upper\s*\{([\s\S]*?)\}/
    );

    assert.ok(guildFrame, 'guild information panel should use the frame background');
    assert.match(guildFrame[1], /background-image:\s*url\(\.\.\/images\/background\/back-shape\/left-content\.png\)/);
    assert.match(guildFrame[1], /background-size:\s*100%\s+100%/);
});

test('quest details and guild relations panels use the exchange panel frame background', () => {
    const styles = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastStyle.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const panels = styles.match(
        /\.for_quest \.right-content,\s*\.for_guild \.right-content #rightofright\s*\{([\s\S]*?)\}/
    );

    assert.ok(panels, 'quest details and guild relations panels should share a frame style');
    assert.match(panels[1], /background-image:\s*url\(\.\.\/images\/background\/back-shape\/left-content\.png\)/);
    assert.match(panels[1], /background-size:\s*100%\s+100%/);
    assert.match(panels[1], /border:\s*6px\s+solid\s+transparent/);
});

test('battle report scrollbar is only initialized when its list overflows', () => {
    let scrollInitializations = 0;
    let scrollRemovals = 0;
    let currentScroll;
    const reportsList = {
        0: { scrollHeight: 100, clientHeight: 100 },
        length: 1,
        getNiceScroll: () => currentScroll,
        niceScroll: () => {
            scrollInitializations++;
            currentScroll = { remove: () => {
                scrollRemovals++;
                currentScroll = undefined;
            } };
        }
    };
    const context = {
        $: selector => {
            assert.equal(selector, '#reports_list');
            return reportsList;
        },
        SCROLL_BAR_PROP: {}
    };
    vm.runInNewContext(
        extractFunction(
            'function refreshBattleReportListScroll() {',
            '\n}\n\nBattelField.battelModel',
            2
        ),
        context
    );

    context.refreshBattleReportListScroll();
    assert.equal(scrollInitializations, 0);

    reportsList[0].scrollHeight = 120;
    context.refreshBattleReportListScroll();
    assert.equal(scrollInitializations, 1);

    reportsList[0].scrollHeight = 100;
    context.refreshBattleReportListScroll();
    assert.equal(scrollRemovals, 1);
    assert.equal(scrollInitializations, 1);
});

test('battlefield report list omits empty rows and scrolls only when content overflows', () => {
    const builderStart = clientSource.indexOf('battelField: function(x_coord, y_coord, title, unit_type) {');
    const builderEnd = clientSource.indexOf('refreshBattaelField: function() {', builderStart);
    assert.ok(builderStart >= 0 && builderEnd > builderStart, 'battlefield report panel builder should exist');
    assert.doesNotMatch(
        clientSource.slice(builderStart, builderEnd),
        /<div class="tr">\s*<\/div>/,
        'initial panel should not add placeholder rows'
    );

    const refreshStart = builderEnd;
    const refreshEnd = clientSource.indexOf('refreshBattelDetail: function() {', refreshStart);
    assert.ok(refreshEnd > refreshStart, 'battlefield report refresh handler should exist');
    assert.doesNotMatch(
        clientSource.slice(refreshStart, refreshEnd),
        /<div class="tr"\s*>\s*<\/div>/,
        'empty response rows should not be padded into the list'
    );

    const styles = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastStyle.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const listStyle = styles.match(/#reports_list_BF\s*\{([^}]*)\}/);
    assert.ok(listStyle, 'battlefield report list should have its own overflow behavior');
    assert.match(listStyle[1], /overflow-y:\s*auto/);

    const headersStart = clientSource.indexOf('BattelField.getBattelHeaders = function(battels, selectedBattelId, myBattels) {');
    const headersEnd = clientSource.indexOf('BattelField.getBattelForWorldUnit', headersStart);
    assert.ok(headersStart >= 0 && headersEnd > headersStart, 'BattelField.getBattelHeaders should exist');
    const headersContext = {
        Number: Number,
        Array: Array,
        getArabicNumbers: (n) => String(n),
        BattelField: {}
    };
    vm.runInNewContext(clientSource.slice(headersStart, headersEnd).trim(), headersContext);
    assert.equal(headersContext.BattelField.getBattelHeaders([], 0, false), '', 'empty battles should return empty string without dummy rows');
    assert.doesNotMatch(headersContext.BattelField.getBattelHeaders([], 0, false), /<div class="tr">/);
    const mockBattels = [{ id_battel: 1, battle_pending: 1, PlayerName: 'قائد', x_coord: 10, y_coord: 20, CityName: 'روما' }];
    const renderedHeaders = headersContext.BattelField.getBattelHeaders(mockBattels, 1, false);
    assert.match(renderedHeaders, /class="tr has_battel selected"/);
    assert.doesNotMatch(renderedHeaders, /<div class="tr"><\/div>/);
});

test('UI refinements: palace inputs, guild close control, intro edit, alert dialogs, deposit section, and guild stats', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    const lastStyleCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastStyle.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const finishingCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'finishing.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const uiCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'ui.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');

    // 1. Palace inputs follow the production section logic and styling
    assert.match(clientSource, /case "city_name":[\s\S]*?<input type="text" style="width: 60%; height: 28px; line-height: 26px; text-align: center; display: block; margin: 10px auto;" value="\${escapeHtml\(Caesar\.CurrentCity\.City\.name\)}" id="city-name-val" class="input"\/>/);
    assert.match(clientSource, /case "taxs":[\s\S]*?<input type="text" max="100" min="0" step="1" style="width: 60%; height: 28px; line-height: 26px; text-align: center; display: block; margin: 10px auto;" value="\${Caesar\.CurrentCity\.City\.taxs}" class="only_num input" id="city-name-val"\/>/);
    assert.match(clientSource, /id="msg_out_detail"[\s\S]*?<span>المستلم :<\/span>/);

    // 2. Guild dialog close control is inside right-nav with static nav_icon and wrapped lead menu
    assert.match(clientSource, /<div class="guild_lead_wrapper"/);
    assert.match(uiCss, /#dialg_box\.guild_dialog_box \.nav_icon\s*\{[\s\S]*?position:\s*static/);

    // 3. Edit guild word modal has dedicated class, no scrollbar, and description offset
    assert.match(clientSource, /<div id="select_from" class="edit-guild-word-modal">/);
    assert.match(lastStyleCss, /\.edit-guild-word-modal\s*\{[\s\S]*?overflow:\s*hidden\s*!important/);
    assert.match(lastStyleCss, /\.edit-guild-word-modal #rank-review/);
    assert.match(lastStyleCss, /#rank-review:not\(\.player-review\)\s*\.down\s*p\s*\{[\s\S]*?padding-top:\s*22px/);

    // 4 & 5. Friends/Enemies and Invite King modals have compact 340px width, no gap in row-2, and no scrollbar in confim-btn
    assert.match(finishingCss, /#guild-alert-box\s*\{[\s\S]*?width:\s*340px/);
    assert.match(finishingCss, /#guild-alert-box\s*\.F_E-list\s*\.row-2\s*\{[\s\S]*?height:\s*auto\s*!important/);
    assert.match(finishingCss, /#guild-alert-box\s*\.confim-btn,[\s\S]*?overflow:\s*visible\s*!important/);

    // 6. Guild upgrade deposit section has structured cards, proper table header, and deposit button on right
    assert.match(clientSource, /class="right-content-footer up_guild_footer"/);
    assert.match(clientSource, /class="guild-deposit-section"/);
    assert.match(clientSource, /class="guild-deposit-btn-wrap"[\s\S]*?<ol id="input-guild-donate"/);
    assert.match(clientSource, /"ar":\s*"ايداع"/);
    assert.match(clientSource, /<th colspan="6" class="th guild-upgrade-table-header">/);
    assert.match(lastStyleCss, /\.for_guild \.right-content \.left table thead th/);
    assert.match(lastStyleCss, /\.up_guild \.right-content-footer\.up_guild_footer/);

    // 7. Guild info upper section balances emblem on left and stats on right with zero scroll, and intro starts on 2nd line
    assert.match(
        lastStyleCss,
        /#guild-guild_data \.left-content \.upper \.table\s*\{[\s\S]*?display:\s*flex/
    );
    assert.match(
        lastStyleCss,
        /#guild-guild_data \.left-content \.upper\s*\{[\s\S]*?overflow:\s*hidden\s*!important/
    );
    assert.match(
        lastStyleCss,
        /#guild-guild_data \.left-content \.upper \.table[\s\S]*?guild-emblem-wrap[\s\S]*?order:\s*2/
    );
    assert.match(
        lastStyleCss,
        /#guild-guild_data \.left-content \.upper \.table[\s\S]*?guild-stats-wrap[\s\S]*?order:\s*1/
    );
    assert.match(
        lastStyleCss,
        /#guild-guild_data \.left-content \.upper \.table[\s\S]*?guild-name-wrap[\s\S]*?margin-top:\s*12px/
    );
    assert.match(
        lastStyleCss,
        /#guild-guild_data \.down p\s*\{[\s\S]*?padding-top:\s*24px/
    );
});

test('ranking player profile displays details, removes soon/chat, renames and wires send message and add friend buttons', () => {
    // 1. Check caesar-client.js
    const profileFunc = clientSource.slice(
        clientSource.indexOf('function showPlayerProfile(id_player) {'),
        clientSource.indexOf('$(document).on("click", "#send-g-msg"')
    );

    // Soon button removed
    assert.doesNotMatch(profileFunc, /Translate\.Button\.General\.Soon/);
    assert.doesNotMatch(profileFunc, />قريبا</);

    // Chat button removed
    assert.doesNotMatch(profileFunc, />شات</);

    // Send message and add friend buttons with proper IDs, labels and handlers
    assert.match(profileFunc, /id="profile-send-msg"[\s\S]*?ارسال رسالة/);
    assert.match(profileFunc, /id="profile-add-friend"[\s\S]*?اضافة صديق/);
    assert.match(profileFunc, /\$\("#profile-send-msg"\)\.attr\("data-player-name",\s*json_data\.name/);
    assert.match(profileFunc, /\$\("#profile-add-friend"\)\.attr\("data-player-name",\s*json_data\.name/);
    assert.match(profileFunc, /\$\(document\)\.on\("click",\s*"#profile-send-msg"/);
    assert.match(profileFunc, /message\.dialogBoxcontent_msgWrite\(\{/);
    assert.match(profileFunc, /\$\(document\)\.on\("click",\s*"#profile-add-friend"/);
    assert.match(profileFunc, /add_friend:\s*true/);

    // 2. Check CSS styling in lastStyle.css
    const lastStyleCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastStyle.css'),
        'utf8'
    );
    assert.match(lastStyleCss, /\.player-review \.down \.buttons\s*\{[\s\S]*?display:\s*flex/);
    assert.match(lastStyleCss, /\.player-review \.down \.buttons \.li-d\s*\{[\s\S]*?width:\s*130px/);

    // 3. Check caesar-web/api/player.php
    const playerPhp = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-web', 'api', 'player.php'),
        'utf8'
    );
    assert.match(playerPhp, /GET_PLAYER_DETAIL/);
    assert.match(playerPhp, /SELECT[\s\S]*?p\.id_player, p\.name, p\.avatar, p\.prestige, p\.honor, p\.porm/);
    assert.match(playerPhp, /add_friend/);
    assert.match(playerPhp, /CREATE TABLE IF NOT EXISTS `player_friend`/);
    assert.match(playerPhp, /INSERT INTO `msg_income`/);
});

test('quest checkComplete and refrehQuestNotif guard against undefined city data without throwing', () => {
    const start = clientSource.indexOf('checkComplete: function(idQuest) {');
    const end = clientSource.indexOf('\n    givePrize: function(reward) {', start);
    assert.ok(start >= 0 && end > start, 'checkComplete and refrehQuestNotif should exist');

    const context = {
        QUESTS_LIST: {
            101: {
                ListOfNeed: [{ type: 'population', amount: 500 }]
            },
            102: {
                ListOfNeed: [{ type: 'resource', resourceType: 'coin', amount: 1000 }]
            }
        },
        PLAYER_QUEST_DONE: { 101: 0, 102: 0 },
        ALL_AVAIL_QUEST: {
            QuestGrowth: { 1: [101] },
            QuestDaily: { 1: [102] },
            QuestTrade: {}
        },
        Caesar: {
            CurrentCity: undefined, // Simulates early unpopulated city state
            DPlayer: { Player: { prestige: 100, porm: 2 } }
        },
        $: () => ({ html: () => {} })
    };

    const questModule = vm.runInNewContext(
        `({ ${clientSource.slice(start, end)} })`,
        context
    );
    context.Quest = questModule;

    // Must not throw TypeError: Cannot read properties of undefined (reading 'pop')
    assert.doesNotThrow(() => {
        const completed = questModule.checkComplete(101);
        assert.equal(completed, false);
    });

    assert.doesNotThrow(() => {
        const completedRes = questModule.checkComplete(102);
        assert.equal(completedRes, false);
    });

    assert.doesNotThrow(() => {
        questModule.refrehQuestNotif();
    });

    // When city is populated, population and resource quests evaluate properly
    context.Caesar.CurrentCity = { City: { pop: 600, coin: 2000 } };
    assert.equal(questModule.checkComplete(101), true);
    assert.equal(questModule.checkComplete(102), true);
});

test('market resource transport lists real player cities and excludes current city without dummy names', () => {
    // 1. Verify hardcoded mock cities are removed from client script
    assert.doesNotMatch(clientSource, /title:\s*["']mohamed["']/);
    assert.doesNotMatch(clientSource, /title:\s*["']Mosta["']/);
    assert.doesNotMatch(clientSource, /title:\s*["']morad["']/);
    assert.doesNotMatch(clientSource, /title:\s*["']نغم["']/);

    // 2. Extract market select-city-trans-dis handler from client script
    const handlerMatch = clientSource.match(/\$\(document\)\.on\("click",\s*"#select-city-trans-dis",\s*function\(\)\s*\{([\s\S]*?)\}\);/);
    assert.ok(handlerMatch, 'select-city-trans-dis handler should exist');

    let olHtml = '';
    let toggled = false;
    const fake$ = (selector) => {
        if (selector === '#select_city_trans ol') {
            return {
                html: (content) => { olHtml = content; }
            };
        }
        if (selector === '#select_city_trans') {
            return {
                toggle: () => { toggled = true; }
            };
        }
        return {
            html: () => {},
            toggle: () => {}
        };
    };

    const runHandler = (context) => {
        olHtml = '';
        toggled = false;
        const fn = new Function('Caesar', '$', 'escapeHtml', handlerMatch[1]);
        fn(context.Caesar, fake$, (s) => String(s));
    };

    // Scenario A: Player with 3 cities, currently in city 1
    const testCaesar = {
        CurrentCity: { City: { id_city: 1, name: 'العاصمة 1', x: 100, y: 150 } },
        DPlayer: {
            City: {
                1: { City: { id_city: 1, name: 'العاصمة 1', x: 100, y: 150 } },
                2: { City: { id_city: 2, name: 'الإسكندرية', x: 250, y: 300 } },
                3: { City: { id_city: 3, name: 'روما الشرقية', x: 400, y: 450 } }
            }
        }
    };

    runHandler({ Caesar: testCaesar });
    assert.equal(toggled, true);
    assert.match(olHtml, /الإسكندرية \[250 , 300\]/);
    assert.match(olHtml, /روما الشرقية \[400 , 450\]/);
    assert.doesNotMatch(olHtml, /العاصمة 1/); // Current city must be excluded

    // Scenario B: Player with only 1 city (the current city)
    const singleCityCaesar = {
        CurrentCity: { City: { id_city: 1, name: 'العاصمة الوحيدة', x: 100, y: 150 } },
        DPlayer: {
            City: {
                1: { City: { id_city: 1, name: 'العاصمة الوحيدة', x: 100, y: 150 } }
            }
        }
    };

    runHandler({ Caesar: singleCityCaesar });
    assert.match(olHtml, /لا توجد مدن أخرى/);
});

test('market resource transport UI reflects active porters count and returning porters label', () => {
    // 1. Verify porters counter markup in transportRes
    assert.match(clientSource, /id="trans-porters-info"/);
    assert.match(clientSource, /id="trans-active-out-count"/);

    // 2. Verify TransListHt handles returning transports
    assert.match(clientSource, /Trans\.status\s*===\s*['"]returning['"]/);
    assert.match(clientSource, /عودة النقالين/);

    // 3. Verify transportedResourcesList parses transports and updates active out counter
    assert.match(clientSource, /\$\("#trans-active-out-count"\)\.text\(Market\.TransList\.out\.length\)/);
});

test('favorite coordinates (مفضلة الإحداثيات) UI, navigation button, and market integration', () => {
    // 1. Verify navigator bar button in caesar-web/server.php
    const serverPhp = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-web', 'server.php'),
        'utf8'
    );
    assert.match(serverPhp, /id="fav-btn"/);
    assert.match(serverPhp, /id="btn-show-favorites"/);

    // 2. Verify NavBar.FavoriteCoords and FavoriteCoord module in clientSource
    assert.match(clientSource, /NavBar\.FavoriteCoords/);
    assert.match(clientSource, /var\s+FavoriteCoord\s*=/);
    assert.match(clientSource, /getFavoriteCoords/);
    assert.match(clientSource, /addFavoriteCoord/);
    assert.match(clientSource, /id_city:\s*Number\(currentCity\.id_city\s*\|\|\s*currentCity\.idCity\s*\|\|\s*0\)/);
    assert.match(clientSource, /deleteFavoriteCoord/);
    assert.match(clientSource, /modifyFavoriteCoordTitle/);

    // 3. Verify add-fav-coord-btn in map unit popups
    assert.match(clientSource, /class="add-fav-coord-btn"/);

    // 4. Verify Market favorite selector in transportRes
    assert.match(clientSource, /id="select-fav-trans-dis"/);
    assert.match(clientSource, /id="select_fav_trans"/);

    // 5. Verify CSS styling in lastStyle.css
    const lastStyleCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastStyle.css'),
        'utf8'
    );
    assert.match(lastStyleCss, /\.for_fav_coords/);
    assert.match(lastStyleCss, /\.add-fav-coord-btn/);
    assert.match(lastStyleCss, /\.my-fav-distin/);
});

test('city move 24h attack cooldown: error handling, websocket check, and stat bar countdown badge', () => {
    // 1. Verify ws-server checks city.attack_cooldown_until before launching battle
    const wsServerSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-ws', 'ws-server.js'),
        'utf8'
    );
    assert.match(wsServerSource, /city\.attack_cooldown_until/);
    assert.match(wsServerSource, /city_move_cooldown/);

    // 2. Verify client handles city_move_cooldown in Battel.StartFailed
    assert.match(clientSource, /data\.state\s*===\s*["']city_move_cooldown["']/);

    // 3. Verify client handles error_army_outside in certain_move and random_move UseFunc
    assert.match(clientSource, /JsonObject\.state\s*===\s*["']error_army_outside["']/);
    assert.match(clientSource, /JsonData\['state'\]\s*===\s*'error_army_outside'/);

    // 4. Verify Fixed.refresePlayerStateList displays attack_cooldown_until countdown
    assert.match(clientSource, /Caesar\.CurrentCity\.City\.attack_cooldown_until/);
    assert.match(clientSource, /item008\.jpg/);
});

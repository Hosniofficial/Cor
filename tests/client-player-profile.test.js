const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

test('editable player profile renders only identity, guild, requested stats, and controls', () => {
    const start = clientSource.indexOf('function showEditablePlayerProfile() {');
    const end = clientSource.indexOf('\nfunction refreshEditablePlayerProfileFlag()', start);
    assert.ok(start >= 0 && end > start, 'editable profile renderer should exist');

    let markup = '';
    const context = {
        Caesar: {
            DPlayer: {
                Player: {
                    id_player: 7,
                    name: 'Test King',
                    avatar: 1,
                    prestige: 1200,
                    honor: 340,
                    rank: 2,
                    porm: 3
                },
                GuildData: { name: 'Test Guild' }
            },
            BaseData: { HeroAvatar: ['', 'avatar.jpg'], Items: {} },
            CurrentCity: { City: null }
        },
        getArabicNumbers: String,
        getPromotionTitle: () => 'Promotion',
        Translate: {
            Title: {
                TH: {
                    League: { ar: 'Guild' },
                    NobleRank: { ar: 'Title' },
                    Ranking: { ar: 'Rank' },
                    Info: { ar: 'Info' }
                }
            }
        },
        UserLag: { language: 'ar' },
        refreshEditablePlayerProfileFlag() {},
        $: selector => {
            if (selector === 'body') {
                return { append(html) { markup = html; } };
            }
            return {
                remove() { return this; },
                text() { return this; },
                val() { return this; },
                attr() { return this; },
                prop() { return this; },
                toggle() { return this; }
            };
        }
    };

    vm.runInNewContext(clientSource.slice(start, end), context);
    context.showEditablePlayerProfile();

    for (const required of [
        'class="player-profile-overlay"',
        'id="rank-review" class="player-review"',
        'class="upper"',
        'class="player-avatar"',
        'class="left-btn pull-L"',
        'class="right-btn pull-R"',
        'id="A-A-P-name"',
        'id="A-A-P-guild"',
        'id="A-A-P-prestige"',
        'id="A-A-P-honor"',
        'id="A-A-P-rank"',
        'class="player-profile-name-form"',
        'id="confirm-player-new-img"',
        'id="profile-change-flag"',
        'id="save-player-name-btn"'
    ]) {
        assert.ok(markup.includes(required), `profile should include ${required}`);
    }
    assert.equal((markup.match(/class="close close_use_menu"/g) || []).length, 1, 'profile should render one close control');

    for (const excluded of ['change-player-password', 'images/icons/message.png', 'images/icons/chat.png', 'images/icons/friend.png']) {
        assert.equal(markup.includes(excluded), false, `profile should omit ${excluded}`);
    }
});

test('hero and player profile modals replace prior instances and render one close control', () => {
    const heroStart = clientSource.indexOf('function showHero(hero, recruit) {');
    const heroEnd = clientSource.indexOf('\nvar Hero = {', heroStart);
    const profileStart = clientSource.indexOf('function showPlayerProfile(id_player) {');
    const profileEnd = clientSource.indexOf('\nfunction showEditablePlayerProfile()', profileStart);
    assert.ok(heroStart >= 0 && heroEnd > heroStart, 'hero profile renderer should exist');
    assert.ok(profileStart >= 0 && profileEnd > profileStart, 'player profile renderer should exist');

    const heroRenderer = clientSource.slice(heroStart, heroEnd);
    const playerRenderer = clientSource.slice(profileStart, profileEnd);
    for (const [name, renderer, marker] of [
        ['hero', heroRenderer, 'hero-profile-overlay'],
        ['player', playerRenderer, 'player-profile-overlay']
    ]) {
        assert.ok(renderer.includes(`class="close close_use_menu"`), `${name} profile should have a close button`);
        assert.equal((renderer.match(/class="close close_use_menu"/g) || []).length, 1, `${name} profile should have exactly one close button`);
        assert.ok(renderer.includes(`class="${marker}"`), `${name} profile should have a unique overlay marker`);
        assert.ok(renderer.includes(`$(".${marker}").remove()`), `${name} profile should remove its previous instance`);
    }
});

test('player profile information panel is opaque and world close control uses the original close image', () => {
    const lastOfLastCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastOfLast.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const worldCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'world.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const playerPanel = lastOfLastCss.match(
        /\.player-review \.down\s*\{([\s\S]*?)\}/
    );

    assert.ok(playerPanel, 'player information panel should have an opaque frame style');
    assert.match(playerPanel[1], /background:\s*url\(\.\.\/images\/icons\/left-content\.png\)/);
    assert.match(playerPanel[1], /100%\s+100%/);

    assert.match(clientSource, /class="close_RB"|close_RB/);
    assert.doesNotMatch(worldCss, /close-RB-button/);
    assert.match(clientSource, /class="close_dialog"/);
});

test('saving player name validates item ownership, confirms via alert_box, and deducts the item', () => {
    const handlerStart = clientSource.indexOf('$(document).on("click", "#save-player-name-btn"');
    const handlerEnd = clientSource.indexOf('});\n\n/*', handlerStart);
    assert.ok(handlerStart >= 0 && handlerEnd > handlerStart, 'save-player-name handler should exist');

    let registeredHandler = null;
    let confirmPrompt = null;
    let failPrompt = null;
    let successPrompt = null;
    let ajaxConfig = null;
    let deductedItem = null;
    let deductedAmount = 0;

    const mockJQuery = selector => ({
        val() { return 'KingTwo'; },
        prop() { return this; },
        text() { return this; },
        on(event, childSelector, handler) {
            if (event === 'click' && childSelector === '#save-player-name-btn') {
                registeredHandler = handler;
            }
        }
    });

    const context = {
        document: {},
        $: Object.assign(mockJQuery, {
            ajax(config) {
                ajaxConfig = config;
                return {
                    done(cb) {
                        cb(JSON.stringify({ state: 'ok', name: 'NewKingName' }));
                        return this;
                    },
                    fail() { return this; }
                };
            }
        }),
        API_URL: 'http://test.local',
        Caesar: {
            DPlayer: { Player: { id_player: 1, name: 'OldKingName' } },
            BaseData: { Items: { change_name: { name: 'تغير اسم الملك', playerAmount: 2 } } },
            Config: { AuthToken: 'tok', idServer: 1 },
            LBase: { isJson: () => true }
        },
        Matrial: {
            getPlayerAmount(item) {
                const items = context.Caesar && context.Caesar.BaseData && context.Caesar.BaseData.Items;
                return item === 'change_name' && items && items.change_name ? (items.change_name.playerAmount || 0) : 0;
            },
            takeFrom(item, amt) {
                deductedItem = item;
                deductedAmount = amt;
            }
        },
        alert_box: {
            confirmMessage() {},
            failMessage(msg) { failPrompt = msg; },
            succesMessage(msg) { successPrompt = msg; },
            confirmDialog(msg, callback) {
                confirmPrompt = msg;
                callback();
            }
        },
        Player_profile: { refreshMatrialBox() {} }
    };
    context.Caesar.BaseData.Items.change_name.playerAmount = 2;

    vm.runInNewContext(clientSource.slice(handlerStart, handlerEnd + 3), context);
    assert.ok(typeof registeredHandler === 'function', 'handler should be registered');

    // Case 1: When user has no change_name item
    context.Caesar.BaseData.Items.change_name.playerAmount = 0;
    registeredHandler.call({});
    assert.match(failPrompt, /لا تمتلك غرض/);
    assert.equal(confirmPrompt, null, 'should not prompt confirmation when lacking item');

    // Case 2: When user has change_name item
    failPrompt = null;
    context.Caesar.BaseData.Items.change_name.playerAmount = 1;
    registeredHandler.call({});
    assert.ok(confirmPrompt, 'should prompt confirmation');
    assert.match(confirmPrompt, /KingTwo/);
    assert.match(confirmPrompt, /تغير اسم الملك/);
    assert.equal(deductedItem, 'change_name');
    assert.equal(deductedAmount, 1);
    assert.match(successPrompt, /تم تغيير الاسم واستخدام غرض/);
});


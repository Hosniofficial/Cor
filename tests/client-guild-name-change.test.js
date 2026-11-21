const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

const functionStart = clientSource.indexOf('function useMatrial(user_for, matrial, other) {');
const functionEnd = clientSource.indexOf('function useMatrialBox(matrial_name) {', functionStart);
assert.ok(functionStart >= 0 && functionEnd > functionStart, 'Could not locate useMatrial');
const useMatrialSource = clientSource.slice(functionStart, functionEnd);

function createContext() {
    const requests = [];
    const state = {
        amount: 1,
        refreshViewCalls: 0,
        guildRefreshCalls: 0,
        selectedTabRefreshCalls: 0,
        successMessages: [],
        failureMessages: [],
        takeFromCalls: 0,
        selectors: {
            '.guild_slogan img:first': '4',
            '.guild_slogan img:last': '7',
            '.guild_slogan img:nth-child(2)': '2'
        }
    };
    const context = {
        WS_HOST: 'localhost',
        WS_PORT: 8080,
        Caesar: {
            Config: { AuthToken: 'guild-token', idServer: 1 },
            CurrentCity: { City: { id_city: 10 } },
            Guild: {
                GuildData: { name: 'Old Guild', slog_top: '1', slog_cnt: '1', slog_btm: '1' },
                getGuildData() {
                    state.guildRefreshCalls++;
                }
            },
            DPlayer: {
                GuildData: { name: 'Old Guild', guild_name: 'Old Guild' },
                Player: { guild: 'Old Guild' }
            },
            LBase: {
                isJson(value) {
                    try {
                        JSON.parse(value);
                        return true;
                    } catch (error) {
                        return false;
                    }
                },
                Error(error) {
                    throw new Error(String(error));
                }
            }
        },
        Matrial: {
            getPlayerAmount: () => state.amount,
            setPlayerAmount(_item, amount) {
                state.amount = Number(amount);
            },
            takeFrom() {
                state.takeFromCalls++;
            }
        },
        Player_profile: {
            refresh_view() {
                state.refreshViewCalls++;
            }
        },
        Guild: null,
        alert_box: {
            succesMessage(message) {
                state.successMessages.push(message);
            },
            failMessage(message) {
                state.failureMessages.push(message);
            }
        },
        $: selector => ({
            attr: name => name === 'data-cur_image' ? state.selectors[selector] : undefined,
            val(value) {
                if (arguments.length > 0) state.newName = value;
                return selector === '#newGuildNameInput' ? 'New Guild' : state.newName;
            },
            attrSet() { return this; },
            removeAttr() { return this; },
            click() {
                if (selector === '.guild_dialog_box .nav_bar .left-nav .selected') {
                    state.selectedTabRefreshCalls++;
                }
                return this;
            },
            remove() { return this; }
        })
    };
    context.Guild = context.Caesar.Guild;
    context.$.ajax = options => requests.push(options);
    return { context, requests, state };
}

test('guild emblem update uses the server item balance and refreshes displayed guild state', () => {
    const { context, requests, state } = createContext();
    vm.runInNewContext(useMatrialSource, context);

    context.useMatrial('change_g_slog', 'family_slogan');
    assert.equal(requests.length, 1);
    assert.equal(requests[0].data.newGuildName, 'New Guild');
    assert.equal(requests[0].data.slog_top, '7');
    assert.equal(requests[0].data.slog_cnt, '2');
    assert.equal(requests[0].data.slog_btm, '4');

    requests[0].success(JSON.stringify({
        state: 'ok',
        newName: 'New Guild',
        slogTop: '7',
        slogCnt: '2',
        slogBtm: '4',
        familySloganAmount: 0
    }));

    assert.equal(state.amount, 0);
    assert.equal(state.takeFromCalls, 0);
    assert.equal(context.Caesar.Guild.GuildData.name, 'New Guild');
    assert.equal(context.Caesar.Guild.GuildData.slog_top, '7');
    assert.equal(context.Caesar.DPlayer.GuildData.name, 'New Guild');
    assert.equal(context.Caesar.DPlayer.Player.guild, 'New Guild');
    assert.equal(state.refreshViewCalls, 1);
    assert.equal(state.selectedTabRefreshCalls, 1);
    assert.equal(state.guildRefreshCalls, 1);
});

test('guild name failure does not consume the local family-slogan balance', () => {
    const { context, requests, state } = createContext();
    vm.runInNewContext(useMatrialSource, context);

    context.useMatrial('change_g_slog', 'family_slogan');
    requests[0].success(JSON.stringify({ state: 'error_3' }));

    assert.equal(state.amount, 1);
    assert.equal(state.takeFromCalls, 0);
    assert.equal(state.failureMessages.length, 1);
});

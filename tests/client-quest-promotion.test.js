const assert = require('node:assert/strict');
const fs = require('node:fs');
const test = require('node:test');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    'caesar-assets/web/js-3.4.113/caesar-client.js',
    'utf8'
);
const start = clientSource.indexOf('    givePrize: function(reward) {');
const end = clientSource.indexOf('    takeNeeds: function(listOfNeed) {', start);

if (start < 0 || end < 0) {
    throw new Error('Could not locate Quest.givePrize in the client');
}

const givePrizeMethod = clientSource.slice(start, end).replace(/,\s*$/, '');

test('promotion quest rewards update the local rank before selecting the next quest', () => {
    const calls = [];
    const context = {
        Caesar: {
            DPlayer: { Player: { porm: 3 } }
        },
        Player_profile: {
            refresh_view() {
                calls.push('refresh_view');
            },
            getPlayerBaseData() {
                calls.push('get_player_base_data');
            }
        }
    };

    vm.runInNewContext(
        `const Quest = { ${givePrizeMethod} }; Quest.givePrize([{ type: "promotion" }]);`,
        context
    );

    assert.equal(context.Caesar.DPlayer.Player.porm, 4);
    assert.deepEqual(calls, ['refresh_view', 'get_player_base_data']);
});

test('successful quest claims refresh spent requirements before applying rewards', () => {
    const acceptHandler = clientSource.indexOf('$(document).on("click", "#accept_quest"');
    const successPath = clientSource.indexOf('if (jsonData.state === "ok") {', acceptHandler);
    const consumeNeeds = clientSource.indexOf('Quest.takeNeeds(QUESTS_LIST[idQuest].ListOfNeed);', successPath);
    const grantRewards = clientSource.indexOf('Quest.givePrize(QUESTS_LIST[idQuest].Reword);', successPath);

    assert.ok(acceptHandler >= 0 && successPath > acceptHandler);
    assert.ok(consumeNeeds > successPath && consumeNeeds < grantRewards);
});

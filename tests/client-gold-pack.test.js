const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('gold-pack handlers use the current city and stop on invalid API responses', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf("    if (Caesar['BaseData']['Items']['gold_5'])");
    const end = clientSource.indexOf('    Caesar.BaseData.Items[`arena_attempt_1`][`UseFunc`] = function(amount) {', start);
    assert.ok(start >= 0 && end > start, 'gold-pack handlers should exist');

    const goldItems = ['gold_5', 'gold_1', 'gold_10', 'gold_25', 'gold_75', 'gold_100', 'gold_500', 'gold_1000'];
    const errors = [];
    const requests = [];
    let profileRefreshCount = 0;
    const context = {
        Caesar: {
            BaseData: { Items: Object.fromEntries(goldItems.map(item => [item, {}])) },
            CurrentCity: { City: { id_city: 42 } },
            Config: { AuthToken: 'test-token', idServer: 3 },
            LBase: {
                isJson: text => {
                    try {
                        JSON.parse(text);
                        return true;
                    } catch {
                        return false;
                    }
                },
                Error: response => {
                    errors.push(response);
                    return response;
                }
            }
        },
        NODE_URL: 'http://localhost:8092',
        $: {
            ajax: options => {
                requests.push(options);
                return options;
            }
        },
        Player_profile: { getPlayerBaseData: () => { profileRefreshCount++; } }
    };
    vm.runInNewContext(clientSource.slice(start, end), context);

    for (const item of goldItems) {
        const request = context.Caesar.BaseData.Items[item].UseFunc(3);
        assert.equal(request.url, 'http://localhost:8092/api/AItemUse/useGoldPack');
        assert.equal(request.data.Item, item);
        assert.equal(request.data.amount, 3);
        assert.equal(request.data.idCity, 42);
        assert.equal(request.data.token, 'test-token');
        assert.equal(request.data.server, 3);

        const rawError = `<html>${item} failed</html>`;
        assert.equal(request.success(rawError, 'success', {}), rawError);
        assert.equal(errors.at(-1), rawError);

        request.success('{"state":"ok"}', 'success', {});
    }

    assert.equal(requests.length, goldItems.length);
    assert.equal(errors.length, goldItems.length);
    assert.equal(profileRefreshCount, goldItems.length);
});

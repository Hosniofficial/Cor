const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

test('contribute list loads only after the player response configures its JSON version', () => {
    const loginRequest = clientSource.indexOf('url: API_URL + "/home/HLogIn/playerEnterServerWeb"');
    const versionAssignment = clientSource.indexOf(
        "Caesar['Config']['JsVersion'] = JsonData['JsVersion'];",
        loginRequest
    );
    const loadList = clientSource.indexOf("Caesar['Contribute']['getList']();");
    const playerReady = clientSource.indexOf("$('html')['trigger']('PlayerReady');", loginRequest);

    assert.ok(loginRequest >= 0, 'player login request should exist');
    assert.ok(versionAssignment > loginRequest, 'JSON version should come from the player response');
    assert.ok(loadList > versionAssignment, 'contribute list should load after the version is set');
    assert.ok(playerReady > loadList, 'contribute list should start loading before PlayerReady');
    assert.equal(
        (clientSource.match(/Caesar\['Contribute'\]\['getList'\]\(\);/g) || []).length,
        1,
        'contribute list should have one controlled load point'
    );
});

test('contribute list URL includes the configured JSON version', () => {
    const start = clientSource.indexOf('Caesar.Contribute.getList = function() {');
    const end = clientSource.indexOf('\n};', start);
    assert.ok(start >= 0 && end > start, 'contribute list loader should exist');

    let request;
    const context = {
        API_URL: 'http://localhost:8092',
        Caesar: { Config: { JsVersion: '-0.0.24' }, Contribute: {} },
        $: { ajax: options => { request = options; } }
    };

    vm.runInNewContext(clientSource.slice(start, end + 3), context);
    context.Caesar.Contribute.getList();

    assert.equal(request.url, 'http://localhost:8092/js-0.0.24/json/contribute.json');
});

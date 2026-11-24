const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const clientJsPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js');
const worldCtrlPath = path.join(__dirname, '..', 'caesar-api', 'controllers', 'WorldController.php');
const reportPhpPath = path.join(__dirname, '..', 'caesar-web', 'api', 'battelReport.php');

test('fillCityWithBuilding defends against undefined Caesar.CurrentCity.City', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');

    // Must not directly call Number(Caesar.CurrentCity.City.lvl) without null guard
    assert.ok(
        !clientJs.includes('if (Number(Caesar.CurrentCity.City.lvl) > 0)'),
        'fillCityWithBuilding must not access Caesar.CurrentCity.City.lvl directly'
    );
    assert.ok(
        clientJs.includes('currentCity ? Number(currentCity.lvl || 0) : 0') ||
        clientJs.includes('currentCity.lvl'),
        'fillCityWithBuilding must safely resolve currentCity and fallback to 0'
    );
});

test('SPY.sendSpy safely handles server response and does not overwrite City with undefined', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');

    // SPY.sendSpy must verify JsonObject.City before assignment
    assert.ok(
        clientJs.includes('if (JsonObject.City && typeof JsonObject.City === "object")'),
        'SPY.sendSpy and cancelSpy must guard against undefined JsonObject.City'
    );
});

test('WorldController implements startSpy and cancelSpy returning updated City object', () => {
    const worldCtrl = fs.readFileSync(worldCtrlPath, 'utf8');

    assert.ok(
        worldCtrl.includes("case '/api/ASpy/start':\n                self::startSpy($db);") ||
        worldCtrl.includes("case '/api/ASpy/start':\r\n                self::startSpy($db);"),
        'WorldController must route /api/ASpy/start to startSpy'
    );
    assert.ok(
        worldCtrl.includes("public static function startSpy($db)"),
        'WorldController must implement startSpy'
    );
    assert.ok(
        worldCtrl.includes("public static function cancelSpy($db)"),
        'WorldController must implement cancelSpy'
    );
    assert.ok(
        worldCtrl.includes("'City'  => $updatedCity") || worldCtrl.includes("'City' => $updatedCity"),
        'startSpy must return updated City in response'
    );
});

test('Wilderness spying is authentic and fully supported in client and report APIs', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');
    const reportPhp = fs.readFileSync(reportPhpPath, 'utf8');

    // BarrayAttack includes MapIconSpy
    assert.ok(
        clientJs.includes('BarrayAttack: [{') && clientJs.includes('Icon: "MapIconSpy"'),
        'BarrayAttack on world map must include MapIconSpy'
    );

    // Reports support barrary
    assert.ok(
        clientJs.includes('message.contentForSpyReportBarray'),
        'Client must contain contentForSpyReportBarray for wilderness scouting'
    );
    assert.ok(
        clientJs.includes('data_obj.spy_for === "barrary"'),
        'Client show_spy_report must handle spy_for === "barrary"'
    );
    assert.ok(
        reportPhp.includes('spy_for'),
        'battelReport.php must select and support spy_for'
    );
});

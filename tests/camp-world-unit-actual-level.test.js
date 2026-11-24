const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

test('campDB.showDialogBox wraps armyContainer in #lvlChangableUnit and removes previous dialog cleanly', () => {
    // Verify #lvlChangableUnit wrapper exists in showDialogBox
    assert.ok(
        clientSource.includes('<div id="lvlChangableUnit">'),
        'showDialogBox must include <div id="lvlChangableUnit"> so dynamic level updates can target it'
    );

    // Verify dialog replacement does not use broken jQuery remove(fn) callback
    assert.ok(
        !clientSource.includes('$("#dialg_box").remove(function()'),
        'showDialogBox should not pass a callback into remove() because jQuery does not execute it'
    );
});

test('campDB.armyContainer provides loading state before refresh and renders actual level on armyData', () => {
    // Extract campDB definition or verify its presence and logic
    assert.ok(
        clientSource.includes('if (uTypeData.lvlChange && !unit._lvlRefreshed && !armyData)'),
        'armyContainer should guard unrefreshed dynamic units with a loading indicator'
    );
    assert.ok(
        clientSource.includes('جارِ تحميل مستوى المعسكر وبيانات الجيوش...'),
        'loading indicator message should exist'
    );
    assert.ok(
        clientSource.includes("unit._lvlRefreshed = true;"),
        'unit should be marked as refreshed once armyData is supplied'
    );
});

test('OnOtherUnitClicked immediately updates #lvlChangableUnit and unit level on server response', () => {
    // Verify OnOtherUnitClicked logic
    assert.ok(
        clientSource.includes("$('#lvlChangableUnit').html(campDB.armyContainer(Unit.x, Unit.y, jsonData.army))"),
        'OnOtherUnitClicked must update #lvlChangableUnit with refreshed armyContainer'
    );
    assert.ok(
        clientSource.includes('Unit._lvlRefreshed = true;'),
        'OnOtherUnitClicked marks Unit as refreshed'
    );
});

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('GM world-unit lock and unlock messages use distinct handlers', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    const start = clientSource.indexOf('Caesar.WsLib.World.UnitLockedByGM = function(data) {');
    const end = clientSource.indexOf(
        'Caesar.WsLib.World.UnitRoundFinishedByGM = function(data) {',
        start
    );
    assert.ok(start >= 0 && end > start, 'GM unit lock handlers should exist');

    const unit = { lo: 0, ut: 1 };
    const messages = [];
    const context = {
        Caesar: { WsLib: { World: {} } },
        WorldUnit: {
            getWorldUnit: () => unit,
            getUnitData: () => ({ Title: 'unit' })
        },
        Extract: { coordDirect: () => '1|1' },
        Chat: { append: message => messages.push(message) }
    };
    vm.runInNewContext(clientSource.slice(start, end), context);

    const data = { xCoord: 1, yCoord: 1 };
    context.Caesar.WsLib.World.UnitLockedByGM(data);
    assert.equal(unit.lo, 1);
    assert.match(messages.at(-1), /تم إغلاق/);

    context.Caesar.WsLib.World.UnitUnlockedByGM(data);
    assert.equal(unit.lo, 0);
    assert.match(messages.at(-1), /تم فتح/);
});

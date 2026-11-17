const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('owned-barracks lookup matches coordinates across player cities', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf('function isMyBarr(');
    const end = clientSource.indexOf('\n\nfunction battelStart()', start);
    assert.ok(start >= 0 && end > start, 'owned-barracks lookup should exist');

    const context = {
        Caesar: {
            DPlayer: {
                City: [
                    { Barray: [{ x_coord: 3, y_coord: 8 }] },
                    { Barray: [{ x_coord: 11, y_coord: 4 }] }
                ]
            }
        }
    };
    const isMyBarr = vm.runInNewContext(`${clientSource.slice(start, end)}; isMyBarr`, context);

    assert.equal(isMyBarr(11, 4), true);
    assert.equal(isMyBarr(11, 5), false);
});

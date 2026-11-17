const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('unit requirements remain renderable when item metadata is missing', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf('    get_req: function(type) {');
    const end = clientSource.indexOf('\n    },', start) + 6;
    assert.ok(start >= 0 && end > start, 'unit requirement renderer should exist');

    const context = {
        Caesar: {
            World: {
                UnitTypeData: {
                    7: {
                        MakeReq: [{ Item: 'gold_5', amount: 3 }],
                        JoinReq: [{ Item: 'unknown_item', amount: 1 }]
                    }
                }
            },
            BaseData: { Items: {} }
        },
        Matrial: { getPlayerAmount: () => 0 }
    };
    const getRequirements = vm.runInNewContext(
        `({${clientSource.slice(start, end)}}).get_req`,
        context
    );

    const html = getRequirements(7);
    assert.match(html, /3 من\s+gold_5/);
    assert.match(html, /1 من\s+unknown_item/);
});

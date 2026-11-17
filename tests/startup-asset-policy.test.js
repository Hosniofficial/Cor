'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const { handleRequiredAssetError } = require('../caesar-shared/startup-asset-policy');

test('required asset failures abort production startup but remain visible in development', () => {
    const messages = [];
    const logger = { error: (message) => messages.push(message) };
    const error = new Error('file missing');

    assert.doesNotThrow(() => handleRequiredAssetError('WorldBarrary.json', error, false, logger));
    assert.throws(
        () => handleRequiredAssetError('WorldBarrary.json', error, true, logger),
        /Failed to load WorldBarrary\.json: file missing/
    );
    assert.deepEqual(messages, [
        '[Asset Error] Failed to load WorldBarrary.json: file missing',
        '[Asset Error] Failed to load WorldBarrary.json: file missing'
    ]);
});

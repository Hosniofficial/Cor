'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const { isAuthorizedInternalRequest } = require('../caesar-shared/internal-api-auth');

test('internal API accepts only authenticated POST requests', () => {
    const token = 'server-secret-token';
    const request = {
        method: 'POST',
        headers: { authorization: `Bearer ${token}` }
    };

    assert.equal(isAuthorizedInternalRequest(request, token), true);
    assert.equal(isAuthorizedInternalRequest({ ...request, method: 'GET' }, token), false);
    assert.equal(isAuthorizedInternalRequest({ method: 'POST', headers: {} }, token), false);
    assert.equal(isAuthorizedInternalRequest({
        method: 'POST',
        headers: { authorization: 'Bearer wrong-token' }
    }, token), false);
    assert.equal(isAuthorizedInternalRequest(request, ''), false);
});

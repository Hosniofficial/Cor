'use strict';

const { timingSafeEqual } = require('node:crypto');

function isAuthorizedInternalRequest(req, expectedToken) {
    if (req?.method !== 'POST'
        || typeof expectedToken !== 'string'
        || expectedToken.length === 0) {
        return false;
    }

    const authorization = req.headers?.authorization;
    if (typeof authorization !== 'string' || !authorization.startsWith('Bearer ')) {
        return false;
    }

    const providedToken = Buffer.from(authorization.slice('Bearer '.length));
    const expectedTokenBuffer = Buffer.from(expectedToken);
    return providedToken.length === expectedTokenBuffer.length
        && timingSafeEqual(providedToken, expectedTokenBuffer);
}

module.exports = { isAuthorizedInternalRequest };

// Validate the local PFX configuration for Node.js TLS.
// Run with ELKAISAR_TLS_PASSPHRASE set in the environment.

const fs   = require('fs');
const path = require('path');
const BASE = __dirname;

const tls  = require('tls');

const passphrase = process.env.ELKAISAR_TLS_PASSPHRASE;
if (!passphrase) {
    throw new Error('Set ELKAISAR_TLS_PASSPHRASE in the environment');
}

const pfxBuf = fs.readFileSync(path.join(BASE, 'ssl-cert.pfx'));

tls.createSecureContext({
    pfx: pfxBuf,
    passphrase,
});

console.log('PFX TLS context validated.');

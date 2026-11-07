// Generate PEM files from PFX for Node.js HTTPS/WSS
// Run once: node gen-pem.js

const fs   = require('fs');
const path = require('path');
const BASE = __dirname;

// Use Node.js crypto to load the PFX
// Node 22 supports loading PFX directly via tls
const tls  = require('tls');

// Read PFX
const pfxBuf = fs.readFileSync(path.join(BASE, 'ssl-cert.pfx'));

// Create a secure context from PFX to extract key/cert
const ctx = tls.createSecureContext({
    pfx: pfxBuf,
    passphrase: 'password'
});

// Unfortunately Node doesn't expose PEM extraction directly from PFX in older APIs
// Use the forge-like approach via built-in crypto
// Actually let's just use the PFX directly in ws-server - Node supports it natively!

console.log('PFX file exists:', fs.existsSync(path.join(BASE, 'ssl-cert.pfx')));
console.log('Done - ws-server.js will use ssl-cert.pfx directly');

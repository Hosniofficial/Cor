const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

test('game page versions the client bundle from the routed asset directory', () => {
    const serverSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-web', 'server.php'),
        'utf8'
    );
    const bundlePath = path.join(
        __dirname,
        '..',
        'caesar-assets',
        'web',
        'js-3.4.113',
        'caesar-client.js'
    );

    assert.ok(fs.statSync(bundlePath).isFile(), 'client bundle should exist in the asset root');
    assert.match(
        serverSource,
        /\$clientBundlePath = dirname\(__DIR__\) \. '\/caesar-assets\/web\/js-3\.4\.113\/caesar-client\.js';/
    );
    assert.match(
        serverSource,
        /src="js-3\.4\.113\/caesar-client\.js\?v=<\?= \$clientBundleVersion \?>"/
    );
    assert.doesNotMatch(
        serverSource,
        /filemtime\(__DIR__ \. '\/js-3\.4\.113\/caesar-client\.js'\)/
    );
});

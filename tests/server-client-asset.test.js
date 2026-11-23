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

test('game page loads only active game scripts and never logs HTML response bodies', () => {
    const serverSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-web', 'server.php'),
        'utf8'
    );
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    const gameScripts = Array.from(
        serverSource.matchAll(/<script\b[^>]*\bsrc="([^"]+)"/g),
        match => match[1].split('?')[0]
    ).filter(src => src.startsWith('js-3.4.113/'));

    assert.deepEqual(gameScripts, [
        'js-3.4.113/lib/jquery-3.2.1.min.js',
        'js-3.4.113/lib/jquery.nicescroll.min.js',
        'js-3.4.113/lib/jquery-ui.min.js',
        'js-3.4.113/lib/phaser.min.js',
        'js-3.4.113/caesar-client.js'
    ]);
    for (const script of gameScripts) {
        assert.ok(
            fs.existsSync(path.join(__dirname, '..', 'caesar-assets', 'web', script)),
            `${script} should exist in the public asset root`
        );
    }
    assert.match(serverSource, /console\.warn\('\[HTML alert suppressed\]'\)/);
    assert.doesNotMatch(serverSource, /console\.(?:log|warn)\([^;\n]*\bmsg\b/);
    assert.doesNotMatch(clientSource, /console\.log\s*\(/);
});

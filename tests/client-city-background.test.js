const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

test('city level-zero background reuses the identical preloaded city floor asset', () => {
    const imagesPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'images', 'city');
    assert.ok(fs.existsSync(path.join(imagesPath, 'city_floor.jpg')));
    assert.equal(fs.existsSync(path.join(imagesPath, 'background_townBackground0.jpg')), false);
    assert.match(clientSource, /load\.image\('city_floor', BASE_ASSET_BATH \+ 'images\/city\/city_floor\.jpg'\)/);
    assert.doesNotMatch(clientSource, /load\.image\('town_bg_0'/);
    assert.match(clientSource, /var bgKey = "city_floor";/);
    assert.match(clientSource, /var curBgKey = "city_floor";/);
});

test('city harbor and lighthouse assets remain available to the Phaser preload', () => {
    const imagesPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'images', 'city');

    for (const [key, filename] of [
        ['seaport', '_seaport.png'],
        ['lighthouse', '_lighthouse.png']
    ]) {
        assert.match(clientSource, new RegExp(`load\\.image\\('${key}'.*${filename.replace('.', '\\.')}'\\)`));
        assert.ok(fs.existsSync(path.join(imagesPath, filename)), `${filename} is required by the client`);
    }
});

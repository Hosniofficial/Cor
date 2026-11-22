const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const stylesheetDirectory = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113');
const imageDirectory = path.join(__dirname, '..', 'caesar-assets', 'web', 'images');

test('game stylesheets only reference existing local assets', () => {
    const missingAssets = [];

    for (const filename of fs.readdirSync(stylesheetDirectory).filter(name => name.endsWith('.css'))) {
        const stylesheetPath = path.join(stylesheetDirectory, filename);
        const stylesheet = fs.readFileSync(stylesheetPath, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');

        for (const match of stylesheet.matchAll(/url\(\s*(['"]?)(.*?)\1\s*\)/gi)) {
            const reference = match[2].trim();
            if (!reference || /^(data:|https?:|\/\/|#|var\()/i.test(reference)) continue;

            const assetPath = path.resolve(
                path.dirname(stylesheetPath),
                decodeURIComponent(reference.split(/[?#]/)[0])
            );
            if (!fs.existsSync(assetPath)) {
                missingAssets.push(`${filename}: ${reference}`);
            }
        }
    }

    assert.deepEqual(missingAssets, []);
});

test('dynamic player, guild, and city slogans retain every referenced frame', () => {
    const sloganFrames = [
        ...Array.from({ length: 21 }, (_, index) => `top-${index + 1}.png`),
        ...Array.from({ length: 5 }, (_, index) => `bottom-${index + 1}.png`),
        ...Array.from({ length: 4 }, (_, index) => `central-${index + 1}.png`)
    ];

    for (const filename of sloganFrames) {
        assert.ok(fs.existsSync(path.join(imageDirectory, 'style', filename)), `${filename} is used by dynamic slogan URLs`);
    }
});

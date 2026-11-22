const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const stylesheetDirectory = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113');
const imageDirectory = path.join(__dirname, '..', 'caesar-assets', 'web', 'images');
const stylesheetBundles = {
    'base.bundle.css': ['reset-min.css', 'fonts-min.css', 'base.css', 'city.css', 'world.css'],
    'overrides.bundle.css': ['lastStyle.css', 'lastOfLast.css', 'finishing.css', 'tooltips.css', 'animation.css'],
    'features.bundle.css': ['hero.css', 'godPoints.css', 'ui.css', 'team.css']
};

test('game stylesheet bundles preserve their source order and contents', () => {
    for (const [bundleName, sourceFiles] of Object.entries(stylesheetBundles)) {
        const expected = sourceFiles
            .map(filename => fs.readFileSync(path.join(stylesheetDirectory, filename), 'utf8'))
            .join('\n');
        const actual = fs.readFileSync(path.join(stylesheetDirectory, bundleName), 'utf8');
        assert.equal(actual, expected, `${bundleName} must be rebuilt from the ordered source files`);
    }

    const server = fs.readFileSync(path.join(__dirname, '..', 'caesar-web', 'server.php'), 'utf8');
    assert.equal((server.match(/<link rel="stylesheet" href="css-3\.4\.113\/[^"]+\.bundle\.css\?v=/g) || []).length, 3);
});

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

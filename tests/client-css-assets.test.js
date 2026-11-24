const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const stylesheetDirectory = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113');
const imageDirectory = path.join(__dirname, '..', 'caesar-assets', 'web', 'images');
const stylesheetBundles = {
    'core.bundle.css': ['reset-min.css', 'fonts-min.css', 'base.css', 'animation.css'],
    'gameplay.bundle.css': ['city.css', 'world.css', 'tooltips.css', 'hero.css', 'godPoints.css', 'ui.css', 'team.css'],
    'overrides.bundle.css': ['finishing.css', 'lastStyle.css', 'lastOfLast.css']
};

test('game stylesheet bundles preserve their source order and contents', () => {
    for (const [bundleName, sourceFiles] of Object.entries(stylesheetBundles)) {
        const expected = sourceFiles
            .map(filename => fs.readFileSync(path.join(stylesheetDirectory, filename), 'utf8'))
            .join('\n')
            .replace(/\s+$/, '') + '\n';
        const actual = fs.readFileSync(path.join(stylesheetDirectory, bundleName), 'utf8');
        assert.equal(actual, expected, `${bundleName} must be rebuilt from the ordered source files`);
    }

    const generatedBundles = fs.readdirSync(stylesheetDirectory)
        .filter(filename => filename.endsWith('.bundle.css'))
        .sort();
    assert.deepEqual(generatedBundles, Object.keys(stylesheetBundles).sort());

    const server = fs.readFileSync(path.join(__dirname, '..', 'caesar-web', 'server.php'), 'utf8');
    const loadedBundles = Array.from(
        server.matchAll(/<link rel="stylesheet" href="css-3\.4\.113\/([^"]+\.bundle\.css)\?v=/g),
        match => match[1]
    );
    assert.deepEqual(loadedBundles, Object.keys(stylesheetBundles));
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

test('button and skin assets use the consolidated image directories', () => {
    for (const filename of [
        'btns/numberStepUp.png',
        'btns/numberStepDown.png',
        'btns/closeDownN.png',
        'btns/closeDownH.png',
        'btns/closeDownA.png',
        'background/SolidBg.png',
        'background/solidRect.png',
        'background/solidSquare.png'
    ]) {
        assert.ok(fs.existsSync(path.join(imageDirectory, filename)), `${filename} should exist`);
    }

    for (const directory of ['btn-small', 'BtnCover', 'skins']) {
        assert.equal(
            fs.existsSync(path.join(imageDirectory, directory)),
            false,
            `${directory} should be merged into its canonical image directory`
        );
    }

    for (const filename of fs.readdirSync(stylesheetDirectory).filter(name => name.endsWith('.css'))) {
        const stylesheet = fs.readFileSync(path.join(stylesheetDirectory, filename), 'utf8');
        assert.doesNotMatch(stylesheet, /images\/(?:btn-small|BtnCover|skins)\//);
    }

    const clientBundle = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    assert.doesNotMatch(clientBundle, /images\/(?:btn-small|skins)\//);
});

test('remaining UI image folders are consolidated by asset usage', () => {
    for (const filename of [
        'world/wildlands/field-wildlands-01.jpg',
        'world/wildlands/field-wildlands-05.jpg',
        'icons/hero/eq-list-armor.png',
        'icons/hero/eq-list-boot.png',
        'icons/hero/eq-list-helmet.png',
        'icons/hero/eq-list-shield.png',
        'icons/hero/eq-list-sword.png',
        'icons/hero/lock.png',
        'icons/navigate/m_l_n.png',
        'icons/navigate/m_r_h.png',
        'input/select-row.png',
        'input/select-row-3x.png',
        'background/roof.png',
        'background/back-shape/rect.png',
        'background/back-shape/left-content.png'
    ]) {
        assert.ok(fs.existsSync(path.join(imageDirectory, filename)), `${filename} should exist`);
    }
    assert.ok(fs.existsSync(path.join(imageDirectory, 'background', 'back-shape')));
    assert.equal(
        fs.existsSync(path.join(imageDirectory, 'city', 'wildlands')),
        false,
        'wildland thumbnails should be stored under world'
    );
    assert.equal(
        fs.existsSync(path.join(imageDirectory, 'world', 'back-shape')),
        false,
        'back-shape assets should be stored under background'
    );

    for (const directory of ['selelct', 'raw-ui', 'box', 'border', 'arrow', 'back-shape']) {
        assert.equal(
            fs.existsSync(path.join(imageDirectory, directory)),
            false,
            `${directory} should be merged into a usage-based directory`
        );
    }

    for (const filename of fs.readdirSync(stylesheetDirectory).filter(name => name.endsWith('.css'))) {
        const stylesheet = fs.readFileSync(path.join(stylesheetDirectory, filename), 'utf8');
        assert.doesNotMatch(stylesheet, /images\/(?:selelct|box|border|arrow|back-shape)\//);
        assert.doesNotMatch(stylesheet, /images\/world\/back-shape\//);
        assert.doesNotMatch(stylesheet, /images\/icons\/left-content\.png/);
    }

    const clientBundle = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    assert.doesNotMatch(clientBundle, /images\/(?:raw-ui|box)\//);
});

test('all game panel backgrounds share one optimized image without removing palace artwork', () => {
    const sharedFrame = path.join(imageDirectory, 'background', 'roman_panel_frame.jpg');
    const replayStylesheetDirectory = path.join(
        __dirname,
        '..',
        'caesar-assets',
        'web',
        'battle-replay',
        'css'
    );
    const obsoleteFrames = [
        path.join(imageDirectory, 'skins', 'palace.png'),
        path.join(imageDirectory, 'background', 'player_profile_banner.png'),
        path.join(imageDirectory, 'background', 'bg_equip.jpg'),
        path.join(imageDirectory, 'background', 'bg_h_equip.jpg')
    ];
    const oldReferences = [
        '../images/skins/palace.png',
        '../images/background/player_profile_banner.png',
        '../images/background/bg_equip.jpg',
        '../images/background/bg_h_equip.jpg'
    ];
    const gameStylesheets = fs.readdirSync(stylesheetDirectory)
        .filter(filename => filename.endsWith('.css'));
    const clientBundle = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );

    assert.ok(fs.existsSync(sharedFrame), 'the canonical frame image should exist');
    assert.ok(
        fs.existsSync(path.join(imageDirectory, 'city', 'palace.png')),
        'the distinct palace building artwork must remain'
    );
    for (const obsoleteFrame of obsoleteFrames) {
        assert.equal(fs.existsSync(obsoleteFrame), false, `${path.basename(obsoleteFrame)} should be removed`);
    }
    for (const filename of gameStylesheets) {
        const stylesheet = fs.readFileSync(path.join(stylesheetDirectory, filename), 'utf8');
        for (const reference of oldReferences) {
            assert.equal(stylesheet.includes(reference), false, `${filename} should use the canonical frame`);
        }
    }
    assert.match(clientBundle, /images\/background\/roman_panel_frame\.jpg/);
    assert.match(clientBundle, /images\/city\/palace\.png/);
    assert.doesNotMatch(clientBundle, /images\/skins\/palace\.png/);

    for (const filename of [
        'battle.all.bundle.css',
        'battle.all.bundle.b8ba79c4-fdab-48ef-b0f1-323a482311e8.css'
    ]) {
        const replayStylesheet = fs.readFileSync(
            path.join(replayStylesheetDirectory, filename),
            'utf8'
        );
        assert.equal(
            replayStylesheet.split('/images/background/roman_panel_frame.jpg').length - 1,
            10,
            `${filename} should use the shared frame in every matching replay style`
        );
        assert.doesNotMatch(replayStylesheet, /\.\.\/Res\/(?:background\/(?:player_profile_banner\.png|bg_equip\.jpg|bg_h_equip\.jpg)|skins\/palace\.png)/);
    }
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

test('unused and byte-identical images are removed or shared', () => {
    for (const filename of [
        'background/left-content.png',
        'background/lvl_lable/lable_3.png',
        'background/lvl_lable/lable_4.png',
        'btns/up.png',
        'btns/PlayerTeamBtn-h.png',
        'icons/list/attack.png',
        'icons/list/deff.png',
        'icons/list/peace.jpg',
        'icons/list/peace.png',
        'style/population.png',
        'icons/gold.png',
        'items/sellVoucher.jpg',
        'icons/chat.png',
        'world/forces.png',
        'world/c_forces.png',
        'icons/hero/armor.png',
        'icons/hero/boots.png',
        'icons/hero/helmet.png',
        'icons/hero/shield.png',
        'icons/hero/sword.png',
        'bars/green.png',
        'bars/prog_left.png',
        'bars/prog_right.png',
        'icons/war-icon/collection.png',
        'panner/study_lvl_l.png',
        'sitenav-bar-bg.jpg',
        'world/map-icon/icon_cw_fx.png',
        'world/map-icon/icon_small_wood.png',
        'world/map-icon/icon_zbfx_fz.png',
        'world/map-icon/province-castle.png',
        'world/provinceImage/province_border.png',
        'world/MapUI/timer_counter.png',
        'world/MapUI/Bg/frame_main_common.png',
        'world/MapUI/Bg/frame_map_09.png',
        'world/MapUI/Bg/frame_map_line_coord.png',
        'world/MapUI/Bg/frame_SJDT_kuang01.png',
        'world/MapUI/Bg/frame_SJDT_kuang02.png',
        'world/MapUI/Frame/frame_map_08.png',
        'world/MapUI/Frame/frame_map_18.png',
        'world/MapUI/Icon/btn_map_favorite.png',
        'world/MapUI/Icon/icon_alliance_mineral_construct.png',
        'world/MapUI/Icon/icon_alliance_territory_relationship.png',
        'world/MapUI/Icon/icon_map_armyreturn.png'
    ]) {
        assert.equal(fs.existsSync(path.join(imageDirectory, filename)), false, `${filename} should not be shipped`);
    }

    const finishingStyles = fs.readFileSync(path.join(stylesheetDirectory, 'finishing.css'), 'utf8');
    assert.match(finishingStyles, /\.building_lvl_lable_4\s*\{[^}]*images\/panner\/study_lvl_h\.png/s);

    const teamStyles = fs.readFileSync(path.join(stylesheetDirectory, 'team.css'), 'utf8');
    assert.doesNotMatch(teamStyles, /PlayerTeamBtn-h\.png/);
    assert.match(teamStyles, /PlayerTeamBtn-a\.png/);

    const clientBundle = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    assert.match(clientBundle, /building_lvl_lable_4'[^;]*images\/panner\/study_lvl_h\.png/);
    assert.match(clientBundle, /images\/icons\/army\/attack\.png/);
    assert.match(clientBundle, /images\/icons\/army\/defence\.png/);
    assert.match(clientBundle, /images\/icons\/army\/pop\.png/);
    assert.match(clientBundle, /images\/icons\/resource\/gold\.png/);
    assert.match(clientBundle, /images\/items\/PeaceFlag\.jpg/);
    assert.ok(fs.existsSync(path.join(imageDirectory, 'world', 'snap-shot', 'c_forces.png')));

    const tooltipStyles = fs.readFileSync(path.join(stylesheetDirectory, 'tooltips.css'), 'utf8');
    assert.match(tooltipStyles, /images\/background\/back-shape\/left-content\.png/);

    for (const versionedItemBase of [
        path.join(__dirname, '..', 'caesar-assets', 'api', 'js-0.0.24', 'json', 'itemBase.json'),
        path.join(__dirname, '..', 'caesar-assets', 'api', 'js-3.4.113', 'json', 'itemBase.json'),
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'json', 'itemBase.json')
    ]) {
        const itemBase = JSON.parse(fs.readFileSync(versionedItemBase, 'utf8'));
        assert.equal(itemBase.sell_voucher.image, 'images/items/SecretLetter.jpg');
    }
});

test('hero and level label images live in their semantic directories', () => {
    const heroIcons = ['h_s_attack_2.png', 'h_s_console.png', 'h_s_incity.png', 'h_s_support.png', 'hero_army.png'];
    const levelLabels = ['lable_1.png', 'lable_2.png', 'lable_5.png'];
    const clientBundle = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    const finishingStyles = fs.readFileSync(path.join(stylesheetDirectory, 'finishing.css'), 'utf8');
    const overridesBundle = fs.readFileSync(path.join(stylesheetDirectory, 'overrides.bundle.css'), 'utf8');

    for (const filename of heroIcons) {
        assert.ok(fs.existsSync(path.join(imageDirectory, 'icons', 'hero', filename)), `${filename} should be in icons/hero`);
        assert.equal(fs.existsSync(path.join(imageDirectory, 'icons', filename)), false, `${filename} should not remain in icons`);
        assert.ok(clientBundle.includes(`images/icons/hero/${filename}`), `${filename} should use the hero path`);
        assert.equal(clientBundle.includes(`images/icons/${filename}`), false, `${filename} should not use the old path`);
    }

    for (const filename of levelLabels) {
        assert.ok(fs.existsSync(path.join(imageDirectory, 'background', filename)), `${filename} should be in background`);
        assert.ok(clientBundle.includes(`images/background/${filename}`), `${filename} should use the new client path`);
        assert.ok(finishingStyles.includes(`../images/background/${filename}`), `${filename} should use the new CSS path`);
        assert.ok(overridesBundle.includes(`../images/background/${filename}`), `${filename} should use the new bundled CSS path`);
        assert.equal(
            fs.existsSync(path.join(imageDirectory, 'background', 'lvl_lable', filename)),
            false,
            `${filename} should not remain in background/lvl_lable`
        );
    }

    assert.equal(fs.existsSync(path.join(imageDirectory, 'background', 'lvl_lable')), false, 'the empty lvl_lable directory should be removed');
});

test('guild UI reuses the shared guild icon path', () => {
    const sharedIcon = path.join(imageDirectory, 'icons', 'guild.png');
    const duplicateIcon = path.join(imageDirectory, 'icons', 'chat', 'guild.png');
    const clientBundle = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    const server = fs.readFileSync(path.join(__dirname, '..', 'caesar-web', 'server.php'), 'utf8');

    assert.ok(fs.existsSync(sharedIcon), 'the shared guild icon should remain in icons');
    assert.equal(fs.existsSync(duplicateIcon), false, 'the duplicate guild icon should be removed from icons/chat');
    assert.ok(clientBundle.includes('images/icons/guild.png'), 'the client should use the shared guild icon');
    assert.ok(server.includes('images/icons/guild.png'), 'the server should use the shared guild icon');
    assert.equal(clientBundle.includes('images/icons/chat/guild.png'), false, 'the client should not use the duplicate path');
    assert.equal(server.includes('images/icons/chat/guild.png'), false, 'the server should not use the duplicate path');
});

test('all resource icons use the dedicated resource directory', () => {
    const resourceDirectory = path.join(imageDirectory, 'icons', 'resource');
    const clientBundle = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    );
    const resourceImageMap = clientBundle.match(/const RESOURCE_IMAGE = \{([\s\S]*?)\n\};/);

    assert.ok(resourceImageMap, 'resource image map should exist');
    for (const [resource, filename] of [
        ['coin', 'coin.png'],
        ['food', 'food.png'],
        ['wood', 'wood.png'],
        ['stone', 'stone.png'],
        ['metal', 'metal.png'],
        ['gold', 'gold.png']
    ]) {
        assert.ok(fs.existsSync(path.join(resourceDirectory, filename)), `${filename} should be in icons/resource`);
        assert.match(resourceImageMap[1], new RegExp(`${resource}:\\s*["']images/icons/resource/${filename.replace('.', '\\.')}["']`));
    }

    assert.doesNotMatch(
        clientBundle,
        /images\/(?:style\/(?:coin|food|wood|stone|iron)|icons\/list\/(?:wood|stone|metal))\.png/
    );
});

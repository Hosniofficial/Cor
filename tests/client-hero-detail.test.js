const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('hero detail renders the matching equipment, army, and hero data', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf("Caesar['Hero']['showHeroDetail'] = function(heroData) {");
    const end = clientSource.indexOf('\n};\nCaesar.HeroArmy', start);
    assert.ok(start >= 0 && end > start, 'hero detail renderer should exist');

    const equipmentParts = ['sword', 'helmet', 'boot', 'armor', 'shield', 'belt', 'necklace', 'pendant', 'ring', 'steed'];
    const troopSlots = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
    const hero = {
        Equip: Object.fromEntries(equipmentParts.map(part => [part, part])),
        Army: Object.fromEntries(troopSlots.flatMap((slot, index) => [
            [`${slot}_type`, index + 1],
            [`${slot}_num`, (index + 1) * 10]
        ])),
        Hero: {
            name: 'Test Hero',
            avatar: 'avatar-key',
            lvl: 7,
            point_a: 1,
            point_a_plus: 2,
            point_b: 3,
            point_b_plus: 4,
            point_c: 5,
            point_c_plus: 6
        }
    };
    let renderedTitle;
    let renderedHtml;
    const context = {
        Caesar: {
            Hero: {},
            CurrentHero: hero,
            BaseData: { HeroAvatar: { 'avatar-key': 'avatar.png' } }
        },
        getEquipData: equipment => ({ image: `gear-${equipment}` }),
        getHeroCap: () => 12,
        getHeroMaxCap: () => 48,
        army_typs: Object.fromEntries(troopSlots.map((_, index) => [index + 1, `troop-${index + 1}.png`])),
        Fixed: { getArmyAmountColor: amount => `amount-${amount}` },
        alert_box: {
            alert3X2: (title, html) => {
                renderedTitle = title;
                renderedHtml = html;
            }
        },
        Translate: { Title: { Alert: { ShowHero: { en: 'Hero details' } } } },
        UserLag: { language: 'en' }
    };
    vm.runInNewContext(clientSource.slice(start, end + 3), context);

    context.Caesar.Hero.showHeroDetail();

    assert.equal(renderedTitle, 'Hero details');
    assert.match(renderedHtml, /Test Hero/);
    assert.match(renderedHtml, /avatar\.png/);
    assert.match(renderedHtml, /<div class="lvl">7<\/div>/);
    assert.match(renderedHtml, /12\/48/);
    assert.match(renderedHtml, /class="pull-L col-1">الفيلق<\/div>/);
    assert.doesNotMatch(renderedHtml, /الجنود- الفيلق/);
    assert.match(renderedHtml, /width: 25%/);
    assert.match(renderedHtml, /1 \+ 2/);
    assert.match(renderedHtml, /3 \+ 4/);
    assert.match(renderedHtml, /5 \+ 6/);

    const displayedEquipment = ['helmet', 'necklace', 'armor', 'boot', 'sword', 'shield', 'pendant', 'belt', 'ring', 'steed'];
    let previousEquipmentIndex = -1;
    for (const part of displayedEquipment) {
        const equipmentIndex = renderedHtml.indexOf(`background-image: url(gear-${part})`);
        assert.ok(equipmentIndex > previousEquipmentIndex, `${part} should appear in its original slot order`);
        previousEquipmentIndex = equipmentIndex;
    }

    for (let index = 1; index <= troopSlots.length; index++) {
        assert.match(renderedHtml, new RegExp(`background-image: url\\(images/tech/troop-${index}\\.png\\)`));
        assert.match(renderedHtml, new RegExp(`class="amount amount-${index * 10}"> ${index * 10}<`));
    }
});

test('activating a hero medal updates the client inventory only on success', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf('else if (user_for === "add_medal") {');
    const end = clientSource.indexOf('\n    /**************************************************************************/', start);
    assert.ok(start >= 0 && end > start, 'hero medal activation handler should exist');
    const handler = clientSource.slice(start, end);

    assert.match(handler, /if \(JsonObject\.state === "ok"\)[\s\S]*?Matrial\.takeFrom\(matrial, 1\);/);
    assert.match(handler, /JsonObject\.state === "error_1" \|\| JsonObject\.state === "error_no_item"/);
    assert.match(handler, /\$\("\#hero-wrapper"\)\.replaceWith\(army\.dialogBoxContent_forHeroRight\(Caesar\.CurrentHero\)\)/);
    assert.match(handler, /storedHero\.Medal = JsonObject\.HeroMedal/);
});

test('hero medal expiry text uses a compact font to avoid overlap', () => {
    const baseCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'base.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const medalHeader = baseCss.match(
        /\.medal \.header-2\s*\{([\s\S]*?)\}/
    );
    const medalCounter = baseCss.match(
        /\.medal \.header-2 \.time_counter\s*\{([\s\S]*?)\}/
    );

    assert.ok(medalHeader, 'hero medal header styles should exist');
    assert.match(medalHeader[1], /font-size:\s*12px/);
    assert.ok(medalCounter, 'hero medal expiry counter styles should exist');
    assert.match(medalCounter[1], /font-size:\s*12px/);
});

test('hero equipment preview stays inside its row above the army section', () => {
    const heroCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'hero.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const equipmentRow = heroCss.match(
        /\.box_content \.right-content \.equi_preveiw\s*\{([\s\S]*?)\}/
    );
    const equipmentList = heroCss.match(
        /\.box_content \.right-content \.equi_preveiw ul\s*\{([\s\S]*?)\}/
    );

    assert.ok(equipmentRow, 'equipment row styles should exist');
    assert.ok(equipmentList, 'equipment list styles should exist');
    assert.match(equipmentRow[1], /height:\s*90px/);
    assert.match(equipmentRow[1], /overflow:\s*hidden/);
    assert.match(equipmentRow[1], /background-image:\s*none/);
    assert.match(equipmentList[1], /display:\s*flex/);
    assert.match(equipmentList[1], /flex-wrap:\s*wrap/);
    assert.match(equipmentList[1], /height:\s*72px/);
});

test('hero overview equipment panel has no background image above the legion heading', () => {
    const tooltipCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'tooltips.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const equipmentPanel = tooltipCss.match(
        /#hero-over-view \.right \.equip-review\s*\{([\s\S]*?)\}/
    );

    assert.ok(equipmentPanel, 'hero overview equipment panel styles should exist');
    assert.doesNotMatch(equipmentPanel[1], /background(?:-image)?\s*:/);
});

test('hero equipment and army management panels use the exchange panel frame background', () => {
    const heroCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'hero.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const managementPanels = heroCss.match(
        /#hero-dial-equip #HeroEquipBox,\s*#hero-dial-equip #after-ajax-hero-army,\s*#hero-dial-camp #after-ajax-hero-army\s*\{([\s\S]*?)\}/
    );

    assert.ok(managementPanels, 'equipment and army management panels should share a frame style');
    assert.match(managementPanels[1], /background-image:\s*url\(\.\.\/images\/icons\/left-content\.png\)/);
    assert.match(managementPanels[1], /background-size:\s*100%\s+100%/);
    assert.match(managementPanels[1], /border:\s*6px\s+solid\s+transparent/);
});

test('hero profile data, equipment, and army panels use the frame without extending into the legion row', () => {
    const heroCss = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'hero.css'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const overviewPanels = heroCss.match(
        /#hero-dial-over-view \.hero-profile > \.part-1,\s*#hero-dial-over-view \.hero-profile > \.part-2,\s*#hero-dial-over-view \.hero-profile > \.equi_preveiw,\s*#hero-dial-over-view #after-ajax-hero-army\s*\{([\s\S]*?)\}/
    );
    const equipmentPanel = heroCss.match(
        /#hero-dial-over-view \.hero-profile > \.equi_preveiw\s*\{([\s\S]*?)\}/
    );

    assert.ok(overviewPanels, 'hero overview panels should share the frame style');
    assert.match(overviewPanels[1], /background-image:\s*url\(\.\.\/images\/icons\/left-content\.png\)/);
    assert.match(overviewPanels[1], /background-size:\s*100%\s+100%/);
    assert.ok(equipmentPanel, 'hero equipment preview should be contained within its row');
    assert.match(equipmentPanel[1], /overflow:\s*hidden/);
});

test('hero level 255 hides level-up and experience controls', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const startMarker = 'middle_content: function(hero) {';
    const endMarker = '\n    },\n    getCurrentArmy';
    const start = clientSource.indexOf(startMarker);
    const end = clientSource.indexOf(endMarker, start);
    assert.ok(start >= 0 && end > start, 'hero profile renderer should exist');

    function renderAtLevel(level) {
        const hero = {
            Hero: {
                id_hero: 12,
                id_city: 22,
                console: 0,
                in_city: 0,
                name: 'Test Hero',
                avatar: 'hero',
                ultra_p: 0,
                lvl: level,
                exp: 999,
                loyal: 100,
                points: 0,
                point_a: 10,
                point_a_plus: 0,
                point_b: 10,
                point_b_plus: 0,
                point_c: 10,
                point_c_plus: 0,
                power: 50
            },
            Medal: {
                medal_ceasro: 0,
                medal_den: 0,
                medal_leo: 0
            }
        };
        const context = {
            Caesar: {
                CurrentHero: hero,
                CurrentCity: { City: { console: 99 } },
                Hero: { HeroState: { HERO_IN_BATTEL: 1, HERO_IN_GARISON: 2 } },
                BaseData: { HeroAvatar: { hero: 'hero.png' } },
                DPlayer: { PlayerEdu: { scholership: 0 } }
            },
            Translate: {
                Button: {
                    Hero: {
                        AppointConsul: { en: 'Appoint consul' },
                        DismissConsul: { en: 'Dismiss consul' },
                        Exercise: { en: 'Exercise' },
                        Dismiss: { en: 'Dismiss' },
                        Reverse: { en: 'Reverse' },
                        Reset: { en: 'Reset' },
                        Save: { en: 'Save' }
                    },
                    General: { Upgrade: { en: 'Upgrade' } }
                }
            },
            UserLag: { language: 'en' },
            getArabicNumbers: String,
            getReqHeroXp: () => 100,
            getMaxPower: () => 100,
            Math,
            Number,
            parseInt,
            Date
        };
        const render = vm.runInNewContext(
            `(${clientSource.slice(start + 'middle_content: '.length, end + '\n    }'.length)})`,
            context
        );
        return render(hero);
    }

    const atCap = renderAtLevel(255);
    assert.doesNotMatch(atCap, /hero_up_lvl|add_xp|الخبرة/);

    const belowCap = renderAtLevel(254);
    assert.match(belowCap, /hero_up_lvl/);
    assert.match(belowCap, /add_xp/);
    assert.match(belowCap, /الخبرة/);
});

test('hero capacity and experience thresholds match the verified raw formulas', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const extractFunction = name => {
        const start = clientSource.indexOf(`function ${name}(`);
        const end = clientSource.indexOf('\n}', start) + 2;
        assert.ok(start >= 0 && end > start, `${name} should exist`);
        return clientSource.slice(start, end);
    };

    const now = 1_000_000;
    const context = {
        Caesar: { DPlayer: { PlayerEdu: { leader: 1, scholership: 0 } } },
        Date: { now: () => now * 1000 },
        Math,
        Number
    };
    vm.runInNewContext(extractFunction('getHeroMaxCap'), context);
    const capacity = context.getHeroMaxCap({
        Hero: { point_a: 22, point_a_plus: 0 },
        Medal: { medal_ceasro: now + 100, ceaser_eagle: now + 100 }
    });
    assert.equal(capacity, 56320);

    vm.runInNewContext(extractFunction('getReqHeroXp'), context);
    assert.equal(context.getReqHeroXp(1), 100);
    assert.equal(context.getReqHeroXp(2), 400);
    context.Caesar.DPlayer.PlayerEdu.scholership = 10;
    assert.equal(context.getReqHeroXp(2), 280);
});

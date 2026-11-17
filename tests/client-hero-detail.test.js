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

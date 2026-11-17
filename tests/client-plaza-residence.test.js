const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('plaza residence view renders stationed armies and disables unavailable returns', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf('    dialogBoxContent_forResidenceArmy: function(place, offset) {');
    const end = clientSource.indexOf('\n    },\n    // content  dialogbox of empty places', start) + 6;
    assert.ok(start >= 0 && end > start, 'plaza residence renderer should exist');

    const context = {
        BUILDING_TYPS: { HOSPITAL: 11 },
        Caesar: {
            City: {
                getCity: () => ({
                    BuildingLvl: { plaza: 4 },
                    Garrison: [
                        {
                            id_hero: 19,
                            avatar: 1,
                            HeroName: '<Hero>',
                            LordName: 'Player One',
                            troop_count: 27,
                            status: 'stationed',
                            isAlly: true,
                            Army: { f_1_type: 1, f_1_num: 27 }
                        },
                        {
                            id_hero: 20,
                            avatar: 2,
                            HeroName: 'Returning Hero',
                            LordName: 'Player Two',
                            troop_count: 4,
                            status: 'returning',
                            isAlly: true
                        },
                        {
                            id_hero: 21,
                            avatar: 3,
                            HeroName: 'Local Hero',
                            LordName: 'Host Player',
                            troop_count: 9,
                            status: 'stationed',
                            isAlly: false
                        }
                    ]
                })
            },
            BaseData: { HeroAvatar: { 1: 'hero-1.png', 2: 'hero-2.png' } }
        },
        escapeHtml: value => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;'),
        getArabicNumbers: String
    };
    const renderResidence = vm.runInNewContext(
        `({${clientSource.slice(start, end)}}).dialogBoxContent_forResidenceArmy`,
        context
    );

    const html = renderResidence('plaza', 0);
    assert.match(html, /data-building-place="plaza"/);
    assert.match(html, /data-building-lvl="4"/);
    assert.match(html, /&lt;Hero&gt;/);
    assert.match(html, />27</);
    assert.match(html, /data-id-hero="19"/);
    assert.match(html, /plaza-residence-army-review/);
    assert.doesNotMatch(html, /Local Hero/);
    assert.match(html, /data-id-hero="20"[^>]*disabled|disabled[^>]*data-id-hero="20"/);
    assert.doesNotMatch(html, />undefined</);
});

test('palace garrison view keeps owner heroes and excludes allied garrisons', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const assignment = 'Palace.cityGarrison = ';
    const start = clientSource.indexOf(`${assignment}function(offset, id_hero) {`);
    const end = clientSource.indexOf('\n};\n\nPalace.cityGarrisonHeros = [];', start) + 2;
    assert.ok(start >= 0 && end > start, 'palace garrison renderer should exist');

    const garrison = Array.from({ length: 8 }, (_, index) => ({
        id_hero: index + 1,
        HeroName: `Owner Hero ${index}`,
        LordName: 'Host Player',
        avatar: 1,
        lvl: index + 1,
        isAlly: false
    }));
    garrison.push({
        id_hero: 20,
        HeroName: 'Allied Hero',
        LordName: 'Guest Player',
        avatar: 2,
        lvl: 10,
        isAlly: true
    });

    const context = {
        Caesar: {
            City: { getCity: () => ({ Garrison: garrison }) },
            BaseData: { HeroAvatar: { 1: 'owner.png', 2: 'guest.png' } }
        },
        Translate: {
            Title: { TH: { Hero: { en: 'Hero' }, Lord: { en: 'Lord' }, Lvl: { en: 'Level' } } },
            Button: { General: { Action: { en: 'Action' } }, Building: { AddHero: { en: 'Add hero' } } }
        },
        UserLag: { language: 'en' },
        escapeHtml: value => String(value)
    };
    const renderPalaceGarrison = vm.runInNewContext(
        `({cityGarrison: ${clientSource.slice(start + assignment.length, end)}}).cityGarrison`,
        context
    );

    const html = renderPalaceGarrison(0);
    assert.match(html, /Owner Hero 0/);
    assert.doesNotMatch(html, /Allied Hero|Guest Player/);
    assert.match(html, /data-page-offset="0"/);
    assert.match(html, /1\/2/);

    garrison.splice(7, 1);
    const lastPageAfterRemoval = renderPalaceGarrison(7);
    assert.match(lastPageAfterRemoval, /data-page-offset="0"/);
    assert.match(lastPageAfterRemoval, /1\/1/);
});

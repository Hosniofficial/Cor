const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('hero ranking only shows guild preview for players with a guild', () => {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf('Caesar.Rank.HeroRow = function(offset, HeroList) {');
    const end = clientSource.indexOf('\nCaesar.Rank.CityGeneralRank = function(offset)', start);
    assert.ok(start >= 0 && end > start, 'hero ranking renderer should exist');

    let rendered = '';
    const context = {
        Caesar: {
            Rank: {},
            BaseData: { HeroAvatar: { 1: 'hero.png' } }
        },
        Translate: {
            Title: {
                MenuList: { Ranking: { en: 'Ranking' } },
                TH: {
                    Hero: { en: 'Hero' },
                    Lord: { en: 'Lord' },
                    Lvl: { en: 'Level' },
                    Sway: { en: 'Sway' },
                    Bravery: { en: 'Bravery' },
                    Parry: { en: 'Parry' }
                }
            },
            Button: {
                General: { Action: { en: 'Action' } },
                MenuList: { View: { en: 'View' } }
            }
        },
        UserLag: { language: 'en' },
        SERVER_DATA: { hero_num: 2 },
        getArabicNumbers: String,
        $: selector => ({
            html: value => {
                if (selector === '#RankList .RankRows') rendered = value;
            }
        })
    };
    vm.runInNewContext(clientSource.slice(start, end), context);

    const hero = {
        heroAvatar: 1,
        avatar: 1,
        name: 'Hero',
        lord_name: 'Player',
        lvl: 10,
        point_a: 1,
        point_b: 2,
        point_c: 3
    };
    context.Caesar.Rank.HeroRow(0, [{ ...hero, id_guild: 0 }, { ...hero, id_guild: 7 }]);

    assert.match(rendered, /-----/);
    assert.doesNotMatch(rendered, /data-id-guild="0"/);
    assert.match(rendered, /data-id-guild="7"/);
    assert.equal((rendered.match(/show-guild-prev/g) || []).length, 1);
});

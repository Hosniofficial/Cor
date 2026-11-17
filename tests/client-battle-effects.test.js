const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

test('battle sound honors the toggle and loads the configured local sound asset', () => {
    let soundState = 'on';
    let soundPath;
    let playCount = 0;
    let pauseCount = 0;

    function AudioMock(src) {
        soundPath = src;
        this.currentTime = 0;
        this.play = () => {
            playCount++;
            return { catch: () => {} };
        };
        this.pause = () => { pauseCount++; };
    }

    const soundStart = clientSource.indexOf('var battleWarSound = null;');
    const soundEnd = clientSource.indexOf('\n\nvar WsBattel = {};', soundStart);
    assert.ok(soundStart >= 0 && soundEnd > soundStart, 'battle sound helper should exist');

    const context = {
        Audio: AudioMock,
        BASE_ASSET_BATH: '/assets/',
        document: {
            getElementById: () => ({
                getAttribute: () => soundState
            })
        },
        console
    };
    vm.runInNewContext(clientSource.slice(soundStart, soundEnd), context);

    context.playBattleWarSound();
    assert.equal(soundPath, '/assets/sounds/war_sound.mp3');
    assert.equal(playCount, 1);

    soundState = 'off';
    context.playBattleWarSound();
    assert.equal(playCount, 1);
    assert.equal(pauseCount, 0);
});

test('starting an attack immediately lights the target and plays its battle sound', () => {
    const start = clientSource.indexOf('WsBattel.started = function(battel) {');
    const end = clientSource.indexOf('\n\nWsBattel.effectedPlayer =', start);
    assert.ok(start >= 0 && end > start, 'battle-start event handler should exist');

    const effects = [];
    const worldUnit = { s: 0, entite: { t: 101 } };
    const context = {
        Caesar: {
            Hero: { getHero: () => null },
            Battel: { Battels: [] }
        },
        $: () => ({ click: () => {} }),
        Hero: { heroAttackProc: () => {} },
        PLAYER_NOTIF: { battel_number: 0, hero_in_battel: 0 },
        Fixed: { refreshPlayerNotif: () => {} },
        WorldUnit: { getWorldUnit: () => worldUnit },
        Animation: { fireWorldUnit: (x, y) => effects.push(['fire', x, y]) },
        playBattleWarSound: () => effects.push(['sound']),
        city_profile: { refresh_hero_view: () => {} },
        Matrial: { takeNeedsForAttack: () => {} },
        battel_data: { x_coord: 10, y_coord: 15 },
        CaesarBattel: {}
    };
    vm.runInNewContext(`var WsBattel = {};\n${clientSource.slice(start, end)}`, context);

    context.WsBattel.started({
        id_battel: 4,
        idHero: 7,
        xCoord: 10,
        yCoord: 15,
        Battel: {},
        StartingPrice: []
    });

    assert.equal(worldUnit.s, 1);
    assert.deepEqual(effects, [['fire', 10, 15], ['sound']]);
});

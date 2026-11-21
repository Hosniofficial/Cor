'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

const cssSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'world.css'),
    'utf8'
).replace(/\r\n/g, '\n');

test('Caesar.City.getCityLogo maps expansion levels 0-3 correctly and clamps properly', () => {
    const sandbox = {
        Caesar: { City: {} }
    };
    vm.runInNewContext(
        `
        ${clientSource.slice(
            clientSource.indexOf('Caesar.City.getCityLogo = function(lvl) {'),
            clientSource.indexOf('\nCaesar.City.refreshBtnList = function() {')
        )}
        `,
        sandbox
    );

    assert.equal(sandbox.Caesar.City.getCityLogo(0), 'images/world/city-list/city_citylogo_01.jpg');
    assert.equal(sandbox.Caesar.City.getCityLogo(1), 'images/world/city-list/city_citylogo_02.jpg');
    assert.equal(sandbox.Caesar.City.getCityLogo(2), 'images/world/city-list/city_citylogo_03.jpg');
    assert.equal(sandbox.Caesar.City.getCityLogo(3), 'images/world/city-list/city_citylogo_04.jpg');

    // Edge cases and boundaries
    assert.equal(sandbox.Caesar.City.getCityLogo(undefined), 'images/world/city-list/city_citylogo_01.jpg');
    assert.equal(sandbox.Caesar.City.getCityLogo(null), 'images/world/city-list/city_citylogo_01.jpg');
    assert.equal(sandbox.Caesar.City.getCityLogo(-1), 'images/world/city-list/city_citylogo_01.jpg');
    assert.equal(sandbox.Caesar.City.getCityLogo(4), 'images/world/city-list/city_citylogo_04.jpg');
    assert.equal(sandbox.Caesar.City.getCityLogo(99), 'images/world/city-list/city_citylogo_04.jpg');
});

test('Caesar.City.refreshBtnList renders expansion logos for non-console cities and avatar for console cities', () => {
    let renderedHtml = '';
    const sandbox = {
        Caesar: {
            CurrentCity: { City: { id_city: 2, lvl: 1 } },
            DPlayer: {
                City: {
                    1: { City: { id_city: 1, name: 'المدينة الأولى', lvl: 0, console: null, x: 100, y: 150 } },
                    2: { City: { id_city: 2, name: 'المدينة الثانية', lvl: 1, console: 10, x: 200, y: 250 } },
                    3: { City: { id_city: 3, name: 'المدينة الثالثة', lvl: 2, console: null, x: 300, y: 350 } },
                    4: { City: { id_city: 4, name: 'المدينة الرابعة', lvl: 3, console: null, x: 400, y: 450 } }
                }
            },
            Hero: {
                getHero: (id) => (id === 10 ? { Hero: { id_hero: 10, avatar: 2 } } : null)
            },
            BaseData: {
                HeroAvatar: {
                    2: 'images/hero/faceA3.jpg'
                }
            },
            City: {}
        },
        escapeHtml: (s) => String(s),
        $: (selector) => ({
            html: (content) => { renderedHtml = content; },
            length: 1,
            addClass: () => {}
        })
    };

    const getLogoCode = clientSource.slice(
        clientSource.indexOf('Caesar.City.getCityLogo = function(lvl) {'),
        clientSource.indexOf('\nCaesar.City.prepareCity = function(idCity) {')
    );
    vm.runInNewContext(getLogoCode, sandbox);

    sandbox.Caesar.City.refreshBtnList();

    // Verify City 1 (lvl 0, no console) has city_citylogo_01.jpg
    assert.match(renderedHtml, /data-id-city="1"[^>]*>[\s\S]*?images\/world\/city-list\/city_citylogo_01\.jpg/);

    // Verify City 2 (lvl 1, console with avatar 2) has hero faceA3.jpg AND class="selected"
    assert.match(renderedHtml, /data-id-city="2"[^>]*class="selected"[\s\S]*?images\/hero\/faceA3\.jpg/);

    // Verify City 3 (lvl 2, no console) has city_citylogo_03.jpg and NOT selected
    assert.match(renderedHtml, /data-id-city="3"[^>]*class=""[\s\S]*?images\/world\/city-list\/city_citylogo_03\.jpg/);

    // Verify City 4 (lvl 3, no console) has city_citylogo_04.jpg
    assert.match(renderedHtml, /data-id-city="4"[^>]*>[\s\S]*?images\/world\/city-list\/city_citylogo_04\.jpg/);
});

test('world.css styles city list slots with full natural dimensions without outer frame', () => {
    // Check li base has transparent background (no outer frame around city image or console)
    assert.match(cssSource, /#player-city-list\s+ul\s+li\s*\{[^}]*background:\s*transparent;/);
    assert.doesNotMatch(cssSource, /#player-city-list\s+ul\s+li\s*\{[^}]*background-image:\s*url\(\.\.\/images\/world\/city-list\/no-console\.png\)/);

    // Check li.selected uses selected.png to highlight the active city
    assert.match(cssSource, /#player-city-list\s+ul\s+(?:li\.selected|\.selected)[^}]*background-image:\s*url\(\.\.\/images\/world\/city-list\/selected\.png\)/);
    assert.match(cssSource, /#player-city-list\s+ul\s+(?:li\.selected|\.selected)[^}]*background-size:\s*57px\s+28px/);

    // Check console is full natural size (65px x 27px) filling the button area
    assert.match(cssSource, /#player-city-list\s+ul\s+\.console\s*\{[^}]*width:\s*65px/);
    assert.match(cssSource, /#player-city-list\s+ul\s+\.console\s*\{[^}]*height:\s*27px/);
    assert.match(cssSource, /#player-city-list\s+ul\s+\.console\s*\{[^}]*background-size:\s*cover/);
});

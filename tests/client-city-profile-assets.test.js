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

function renderWildView(fields, unitTypes) {
    const start = clientSource.indexOf('    wild_veiw: function() {');
    const end = clientSource.indexOf('\n    refresh_resource_view:', start);
    assert.ok(start >= 0 && end > start, 'city wild view should exist');

    const context = {
        Caesar: {
            City: { getCity: () => ({ Barray: fields }) },
            World: { UnitTypeData: unitTypes }
        },
        $: () => ({ attr() { return this; }, html() { return this; } })
    };

    return vm.runInNewContext(`({${clientSource.slice(start, end)}\n}).wild_veiw()`, context);
}

test('city wild view uses raw terrain thumbnails for configured wildland types', () => {
    const html = renderWildView(
        [{ Type: 50 }, { Type: 30 }, { Type: 40 }, { Type: 1 }],
        {
            1: { tileName: 'river_1', WSnapShoot: 'river_3.png' },
            30: { tileName: 'm_1', WSnapShoot: 'mountain_1.png' },
            40: { tileName: 'd_1', WSnapShoot: 'desert_1.png' },
            50: { tileName: 'w_1', WSnapShoot: 'wood_1.png' }
        }
    );

    for (const image of [
        'field-wildlands-01.jpg',
        'field-wildlands-02.jpg',
        'field-wildlands-03.jpg',
        'field-wildlands-04.jpg'
    ]) {
        assert.match(html, new RegExp(`images/world/wildlands/${image.replace('.', '\\.')}`));
        assert.ok(
            fs.existsSync(path.join(
                __dirname,
                '..',
                'caesar-assets',
                'web',
                'images',
                'world',
                'wildlands',
                image
            )),
            `${image} should be available in the city wildland assets`
        );
    }
});

test('city wild view keeps map snapshots for non-field units and handles missing snapshots', () => {
    const html = renderWildView(
        [{ Type: 71 }, { Type: 60 }],
        {
            60: { tileName: 'city_0' },
            71: { tileName: 'city_4', WSnapShoot: 'camps.png' }
        }
    );

    assert.match(html, /images\/world\/snap-shot\/camps\.png/);
    assert.match(html, /images\/world\/wildlands\/field-wildlands-05\.jpg/);
});

test('unused CityFloor atlases are neither loaded nor shipped', () => {
    assert.doesNotMatch(clientSource, /MainCityBG_|images\/CityFloor\//);
    assert.equal(
        fs.existsSync(path.join(__dirname, '..', 'caesar-assets', 'web', 'images', 'CityFloor')),
        false
    );
});

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function loadConstructionRenderer(city, buildingData) {
    const clientSource = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
        'utf8'
    ).replace(/\r\n/g, '\n');
    const start = clientSource.indexOf('function cityHasType(building_type) {');
    const end = clientSource.indexOf('\n\n// get content acording to  building type', start);
    assert.ok(start >= 0 && end > start, 'building construction renderer should exist');

    const context = {
        BUILDING_CONSTRUCTION_TYPES: undefined,
        Caesar: {
            Building: { BuildingData: buildingData },
            CurrentCity: { City: city },
            City: { getCity: () => city }
        },
        Building: {
            BuildingWithMaxLvl: type => Number(city.BuildingLvl[type]) || 0
        },
        BuildingConstData: {
            1: { title: 'كوخ' },
            6: { title: 'مسرح' },
            12: { title: 'قصر' }
        },
        Translate: { Button: { Building: { Erect: { en: 'Build' } } } },
        UserLag: { language: 'en' },
        escapeHtml: value => String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;'),
        getArabicNumbers: String
    };
    vm.runInNewContext(clientSource.slice(start, end), context);
    return context;
}

function makeConstructionData(condition) {
    const definition = {
        image: 'images/building/building01.jpg',
        lvlRequirment: [{
            food: 20,
            wood: 30,
            stone: 10,
            metal: 5,
            coin: 2,
            condetion: condition
        }]
    };
    return { 1: definition, 6: definition };
}

test('construction option hides build action until resources and building requirements are met', () => {
    const city = {
        food: 20,
        wood: 10,
        stone: 10,
        metal: 5,
        coin: 2,
        BuildingType: { palace: 12 },
        BuildingLvl: { 12: 0 }
    };
    const renderer = loadConstructionRenderer(city, makeConstructionData([
        { type: 'building', BuildingType: 12, lvl: 1 }
    ]));

    let html = renderer.renderNewBuildingOption(1);
    assert.doesNotMatch(html, /class="[^"]*construct_building/);
    assert.match(html, /يتطلب قصر مستوى 1/);

    city.wood = 30;
    city.BuildingLvl[12] = 1;
    html = renderer.renderNewBuildingOption(1);
    assert.match(html, /class="[^"]*construct_building/);
    assert.match(renderer.renderBuildingRequirementPreview(1), /كل الشروط متوفرة/);
});

test('construction option stays disabled when requirements have not loaded', () => {
    const city = { food: 100, wood: 100, stone: 100, metal: 100, coin: 100 };
    const renderer = loadConstructionRenderer(city, undefined);
    const html = renderer.renderNewBuildingOption(1);

    assert.doesNotMatch(html, /class="[^"]*construct_building/);
    assert.match(html, /تعذر تحميل متطلبات البناء/);
});

test('single-instance building cannot be built twice', () => {
    const city = {
        food: 20,
        wood: 30,
        stone: 10,
        metal: 5,
        coin: 2,
        BuildingType: { theater: 6 },
        BuildingLvl: { 12: 1, 6: 1 }
    };
    const renderer = loadConstructionRenderer(city, makeConstructionData([]));

    const availability = renderer.getNewBuildingAvailability(6);
    assert.equal(availability.ready, false);
    assert.match(availability.blockers.join(' '), /لا يمكن بناء أكثر من مسرح واحد/);
});

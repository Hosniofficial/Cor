const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

function loadConstructionRenderer(city, buildingData) {
    const start = clientSource.indexOf('function cityHasType(building_type) {');
    const end = clientSource.indexOf('\n\n// get content acording to  building type', start);
    assert.ok(start >= 0 && end > start, 'building construction renderer should exist');

    const verifyCondition = (type, lvl) => {
        const conditions = buildingData && buildingData[type]
            && buildingData[type].lvlRequirment[lvl].condetion || [];
        return conditions.map(condition => {
            const title = condition.type === 'building'
                ? (condition.BuildingType === 12 ? 'قصر' : 'المبنى')
                : 'شرط';
            const requiredLevel = Number(condition.lvl) || 0;
            return {
                Txt: condition.type === 'building' ? `${title} مستوى ${requiredLevel}` : title,
                verfied: condition.type !== 'building'
                    || (Number(city.BuildingLvl[condition.BuildingType]) || 0) >= requiredLevel
            };
        });
    };
    const caesarBuilding = { BuildingData: buildingData, verfiyCondetion: verifyCondition };
    const context = {
        Caesar: {
            Building: caesarBuilding,
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
        getArabicNumbers: String,
        changeTimeFormat: String
    };
    const requirementStart = clientSource.indexOf('function getReqTable(type, lvl) {');
    const requirementEnd = clientSource.indexOf('function cityHasType', requirementStart);
    assert.ok(requirementStart >= 0 && requirementEnd > requirementStart, 'upgrade requirement table should exist');
    vm.runInNewContext(clientSource.slice(requirementStart, requirementEnd), context);
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
    assert.match(html, /building-construction-tooltip/);
    assert.match(html, /قصر مستوى 1/);
    assert.match(html, /req_table_container/);

    city.wood = 30;
    city.BuildingLvl[12] = 1;
    html = renderer.renderNewBuildingOption(1);
    assert.match(html, /class="[^"]*construct_building/);
    assert.match(renderer.renderNewBuildingRequirementTooltip(1), /req_table_container/);
    assert.match(renderer.renderNewBuildingRequirementTooltip(1), /class="enough"/);
});

test('construction requirements are shown on each building, not in a side panel', () => {
    const renderer = loadConstructionRenderer({
        food: 100, wood: 100, stone: 100, metal: 100, coin: 100,
        BuildingType: {}, BuildingLvl: {}
    }, makeConstructionData([]));
    const html = renderer.renderNewBuildingOption(1);
    const contentStart = clientSource.indexOf('contentfor_emptyPlace: function() {');
    const contentEnd = clientSource.indexOf('\n    },\n    /**/', contentStart);
    const constructionContent = clientSource.slice(contentStart, contentEnd);

    assert.match(html, /class="upgrade_req_tooltip building-construction-tooltip"/);
    assert.match(html, /class="req_table"/);
    assert.doesNotMatch(constructionContent, /<aside/);
    assert.doesNotMatch(clientSource, /#building-requirement-preview/);
});

test('food and unselected-city images point to assets that exist', () => {
    const foodImage = clientSource.match(/wheat:\s*\{\s*image:\s*"([^"]+)"/);
    assert.ok(foodImage, 'food production icon mapping should exist');
    assert.equal(
        fs.existsSync(path.join(__dirname, '..', 'caesar-assets', 'web', foodImage[1])),
        true
    );

    for (const stylesheet of [
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'world.css'),
        path.join(__dirname, '..', 'caesar-assets', 'web', 'battle-replay', 'css', 'battle.all.bundle.css'),
        path.join(__dirname, '..', 'caesar-assets', 'web', 'battle-replay', 'css', 'battle.all.bundle.b8ba79c4-fdab-48ef-b0f1-323a482311e8.css')
    ]) {
        const stylesheetSource = fs.readFileSync(stylesheet, 'utf8');
        const imagePath = stylesheetSource.match(/url\(([^)]+no-console\.png)\)/);
        assert.ok(imagePath, `${path.basename(stylesheet)} should use an existing city-list image`);
        assert.equal(fs.existsSync(path.resolve(path.dirname(stylesheet), imagePath[1])), true);
    }
});

test('construction option stays disabled when requirements have not loaded', () => {
    const city = { food: 100, wood: 100, stone: 100, metal: 100, coin: 100 };
    const renderer = loadConstructionRenderer(city, undefined);
    const html = renderer.renderNewBuildingOption(1);

    assert.doesNotMatch(html, /class="[^"]*construct_building/);
    assert.match(html, /تعذر تحميل متطلبات البناء/);
});

test('empty-slot building selection fits five buildings per row', () => {
    const stylesheet = fs.readFileSync(
        path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113', 'lastOfLast.css'),
        'utf8'
    );
    const columnRule = stylesheet.match(
        /\.empty_place \.building-construction > ul\.total > li\s*\{([^}]*)\}/
    );
    assert.ok(columnRule, 'building construction column rule should exist');
    assert.match(columnRule[1], /width:\s*19%/);
    assert.match(columnRule[1], /margin:\s*0\.5%/);
    assert.match(stylesheet, /\.building-option:nth-child\(5n \+ 1\)/);
    assert.match(stylesheet, /\.building-option:nth-child\(5n\)/);
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

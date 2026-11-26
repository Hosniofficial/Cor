const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '..');
const assetRoots = [
    path.join(root, 'caesar-assets', 'web', 'js-3.4.113'),
    path.join(root, 'caesar-assets', 'api', 'js-3.4.113'),
    path.join(root, 'caesar-assets', 'api', 'js-0.0.24')
];

const clientSource = fs.readFileSync(
    path.join(assetRoots[0], 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

test('synced Arabic JSON assets are valid and identical across all asset roots', () => {
    const syncedSpecs = [
        { rel: ['json', 'worldUnitData.json'], expectedKeys: 117 },
        { rel: ['json', 'ItemLang', 'ar.json'], expectedKeys: 400 },
        { rel: ['json', 'equipment', 'ar.json'], expectedKeys: 305 },
        { rel: ['json', 'quest', 'ar.json'], expectedKeys: 201 }
    ];

    for (const { rel, expectedKeys } of syncedSpecs) {
        const rawBuffers = assetRoots.map(base => fs.readFileSync(path.join(base, ...rel)));
        const parsed = JSON.parse(rawBuffers[0].toString('utf8'));
        assert.equal(Object.keys(parsed).length, expectedKeys, `${rel.join('/')} preserves key count`);

        for (let i = 1; i < rawBuffers.length; i++) {
            assert.equal(
                Buffer.compare(rawBuffers[0], rawBuffers[i]),
                0,
                `${rel.join('/')} is identical between asset root 0 and ${i}`
            );
        }
    }

    const buildingAr = JSON.parse(fs.readFileSync(path.join(assetRoots[0], 'json', 'building', 'ar.json'), 'utf8'));
    const buildingMain = JSON.parse(fs.readFileSync(path.join(assetRoots[0], 'json', 'building.json'), 'utf8'));
    assert.equal(Object.keys(buildingAr).length, 19);
    assert.equal(Object.keys(buildingMain).length, 19);
    assert.equal(buildingMain['4'].title, 'إسطبل');
    assert.equal(buildingMain['9'].title, 'أكاديمية');
    assert.equal(buildingMain['11'].title, 'البلازا');
});

test('worldUnitData has clean Arabic descriptions without machine-translation gibberish', () => {
    const wudRaw = fs.readFileSync(path.join(assetRoots[0], 'json', 'worldUnitData.json'), 'utf8');
    const wud = JSON.parse(wudRaw);

    for (const bad of ['فتح باب الدم', 'Skullbearer', 'عديمة الفائدة', 'التديب', 'الشعارت']) {
        assert.equal(wudRaw.includes(bad), false, `worldUnitData.json must not contain ${bad}`);
    }

    for (const [id, unit] of Object.entries(wud)) {
        if (unit && typeof unit.Desc === 'string' && unit.Title) {
            assert.ok(unit.Desc.trim().length > 0, `unit ${id} has non-empty description`);
        }
    }
});

test('ItemLang Arabic strings are concise, accurate to server mechanics, and free of boilerplate leakage', () => {
    const itemRaw = fs.readFileSync(path.join(assetRoots[0], 'json', 'ItemLang', 'ar.json'), 'utf8');
    const items = JSON.parse(itemRaw);

    for (const bad of ['Newcomer Pack', 'زر استعمال مباشر', 'اوبُنَي', 'غير مفعل']) {
        assert.equal(itemRaw.includes(bad), false, `ItemLang/ar.json must not contain ${bad}`);
    }

    const namedEntries = Object.entries(items).filter(([, v]) => v && typeof v.name === 'string' && v.name.trim() !== '');
    assert.equal(namedEntries.length, 297);
    for (const [key, item] of namedEntries) {
        assert.ok(item.desc.trim().length > 0, `${key} has non-empty desc`);
        assert.ok(item.long_desc.trim().length > 0, `${key} has non-empty long_desc`);
    }

    assert.match(items.god_point_5.name, /5 نقاط بوابة الآلهة/);
    assert.match(items.god_point_50k.name, /50,000 نقطة بوابة الآلهة/);
    assert.match(items.exp_hero_8.desc, /1,000/);
    assert.match(items.exp_hero_30.desc, /5,000/);
    assert.match(items.exp_hero_100.desc, /20,000/);
    assert.match(items.bread.desc, /20/);
    assert.match(items.milk.desc, /25/);
    assert.match(items.meat.desc, /50/);
    assert.match(items.skill_book.desc, /بوابات الآلهة/);
    assert.ok(items.king_chest.desc.includes('100,000'));
    assert.ok(items.sol_chest.desc.includes('100,000'));
});

test('equipment and quest Arabic files fix copy-paste errors and missing descriptions', () => {
    const equipment = JSON.parse(fs.readFileSync(path.join(assetRoots[0], 'json', 'equipment', 'ar.json'), 'utf8'));
    for (const key of ['holy_belt_1', 'dark_belt_1', 'mars_sword_1', 'mars_boot_1']) {
        assert.ok(equipment[key] && equipment[key].name.trim().length > 0, `${key} name present`);
        assert.ok(equipment[key].desc.trim().length > 0, `${key} desc present`);
        assert.ok(equipment[key].long_desc.trim().length > 0, `${key} long_desc present`);
    }

    const questRaw = fs.readFileSync(path.join(assetRoots[0], 'json', 'quest', 'ar.json'), 'utf8');
    const quests = JSON.parse(questRaw);
    for (const bad of ['زر استعمال مباشر', 'اوبُنَي', '400000']) {
        assert.equal(questRaw.includes(bad), false, `quest/ar.json must not contain ${bad}`);
    }
    assert.match(quests.pres_500k.title, /500,000/);
    assert.match(quests.pres_500k.header, /22/);
    assert.match(quests.pres_500k.header, /500,000/);
    assert.match(quests.uni_9.header, /9/);
    assert.match(quests.stabl_10.header, /10/);
});

test('caesar-client.js and PHP templates fix UI typos while keeping preserved announcements intact', () => {
    // Ensure preserved battle announcement marker remains intact
    const preservedIdx = clientSource.indexOf('وذالك بمساعدة');
    assert.ok(preservedIdx > 0, 'preserved battle announcement block remains intact');

    // Scan outside preserved zone (isolate lines before/after the preserved battle announcement function)
    const lines = clientSource.split('\n');
    const preservedLine = clientSource.slice(0, preservedIdx).split('\n').length - 1;
    const outsidePreserved = [
        ...lines.slice(0, Math.max(0, preservedLine - 900)),
        ...lines.slice(preservedLine + 200)
    ].join('\n');

    for (const bad of [
        'فتح باب الدم',
        'Skullbearer',
        'عديمة الفائدة',
        'بوبات الالهة',
        'بوبة التسليح',
        'الانظمام',
        'الإنظمام',
        'تسطتيع',
        'مش عارف',
        'يا حج',
        'إسم الخلف',
        'إسم الحلف',
        'إسم الفريق',
        'التديب',
        'الشعارت',
        'زبادة',
        'انتاح الغذاء',
        'زر استعمال مباشر',
        'رتيتك',
        'دعوة املك',
        'خطاء'
    ]) {
        assert.equal(outsidePreserved.includes(bad), false, `caesar-client.js outside preserved zone must not contain ${bad}`);
    }

    // Check Queen getTitle returns in UnitData
    const block131Start = clientSource.indexOf('\n    "131": {');
    const block132Start = clientSource.indexOf('\n    "132": {');
    assert.ok(block131Start > 0 && block132Start > block131Start);
    assert.match(clientSource.slice(block131Start, block132Start), /return "الملكة الوسطى";/);
    assert.match(clientSource.slice(block132Start, block132Start + 500), /return "الملكة الكبرى";/);

    // Check allUniteType in VM
    const unitTypesStart = clientSource.indexOf('var allUniteType = {');
    const unitTypesEnd = clientSource.indexOf('\n};', unitTypesStart) + 3;
    const ctxUnit = {};
    vm.runInNewContext(clientSource.slice(unitTypesStart, unitTypesEnd), ctxUnit);
    assert.equal(ctxUnit.allUniteType.wood.prod, 'أخشاب');
    assert.equal(ctxUnit.allUniteType.mountain.ar_title, 'جبال');

    // Check WorldUtil.getDesc cleaned strings
    const getDescStart = clientSource.indexOf('} else if (WorldUnit.isMonawrat(type)) {');
    const getDescEnd = clientSource.indexOf('descForCity:', getDescStart);
    const getDescSlice = clientSource.slice(getDescStart, getDescEnd);
    assert.match(getDescSlice, /هدايا قيّمة من مجلس الشيوخ/);
    assert.match(getDescSlice, /الشعارات لتبديلها/);
    assert.match(getDescSlice, /تحدّي الملكات بين الأحلاف/);
    assert.match(getDescSlice, /تحدّي القلاع بين الأحلاف/);

    // Check getUpgradeData cases 14..18 in VM (no copy-pasted "السور")
    const upgStart = clientSource.indexOf('function getUpgradeData(place) {');
    const upgEnd = clientSource.indexOf('\nfunction getReqTable(', upgStart);
    assert.ok(upgStart > 0 && upgEnd > upgStart);

    const makeUpgradeCtx = (typeId) => {
        const c = {
            Caesar: {
                City: {
                    getCity: () => ({
                        BuildingType: { p1: typeId },
                        BuildingLvl: { p1: 1 }
                    })
                }
            },
            getReqTable: () => '<table></table>',
            getArabicNumbers: n => String(n)
        };
        vm.runInNewContext(clientSource.slice(upgStart, upgEnd), c);
        return c.getUpgradeData('p1');
    };

    const marketHtml = makeUpgradeCtx(14);
    const workshopHtml = makeUpgradeCtx(15);
    const farmHtml = makeUpgradeCtx(16);
    const mineHtml = makeUpgradeCtx(17);
    const quarryHtml = makeUpgradeCtx(18);

    assert.match(marketHtml, /السوق مستوى/);
    assert.doesNotMatch(marketHtml, /السور/);
    assert.match(workshopHtml, /الورشة مستوى/);
    assert.doesNotMatch(workshopHtml, /السور/);
    assert.match(farmHtml, /الحقل مستوى/);
    assert.doesNotMatch(farmHtml, /السور/);
    assert.match(mineHtml, /المنجم مستوى/);
    assert.doesNotMatch(mineHtml, /السور/);
    assert.match(quarryHtml, /المحجر مستوى/);
    assert.doesNotMatch(quarryHtml, /السور/);

    // Check PHP views
    const serverPhp = fs.readFileSync(path.join(root, 'caesar-web', 'server.php'), 'utf8');
    const loginPhp = fs.readFileSync(path.join(root, 'caesar-web', 'login.php'), 'utf8');
    assert.match(serverPhp, /<div id="godGateBtnWrapper">[\s\S]*?title="بوابات الآلهة"/);
    assert.match(serverPhp, /<div id="helpGateBtnWrapper">[\s\S]*?title="المساهمة وتطوير المعدات"/);
    assert.match(serverPhp, />اذهب إلى<\/button>/);
    assert.match(serverPhp, /لقد أصبح القائد المسيطر على عاصمة المشاة/);
    assert.match(loginPhp, /لديك حساب بالفعل\؟ تسجيل الدخول/);
});

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const repoRoot = path.join(__dirname, '..');
const cityAnimDir = path.join(repoRoot, 'caesar-assets', 'web', 'images', 'animation', 'City');
const clientSource = fs.readFileSync(
    path.join(repoRoot, 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

function createMockGameObject(kind, x, y, texture, extra) {
    const handlers = {};
    const obj = {
        kind,
        x,
        y,
        texture,
        originX: 0.5,
        originY: 0.5,
        depth: 0,
        scale: 1,
        alpha: 1,
        flipX: false,
        visible: true,
        active: true,
        destroyed: false,
        scene: null,
        currentAnim: null,
        delayedAnim: null,
        delayedMs: 0,
        dataStore: {},
        anims: {
            play(animKey) {
                obj.currentAnim = animKey;
                return obj;
            }
        },
        setOrigin(ox = 0, oy = 0) {
            obj.originX = ox;
            obj.originY = oy;
            return obj;
        },
        setDepth(d) {
            obj.depth = d;
            return obj;
        },
        setScale(s) {
            obj.scale = s;
            return obj;
        },
        setAlpha(a) {
            obj.alpha = a;
            return obj;
        },
        setFlipX(f) {
            obj.flipX = f;
            return obj;
        },
        setVisible(v) {
            obj.visible = v;
            return obj;
        },
        setInteractive() {
            return obj;
        },
        setTexture(t) {
            obj.texture = t;
            return obj;
        },
        setDataEnabled() {
            return obj;
        },
        data: {
            set(k, v) {
                obj.dataStore[k] = v;
            },
            get(k) {
                return obj.dataStore[k];
            }
        },
        play(animKey) {
            obj.currentAnim = animKey;
            return obj;
        },
        playAfterDelay(animKey, delayMs) {
            obj.delayedAnim = animKey;
            obj.delayedMs = delayMs;
            return obj;
        },
        on(evt, fn) {
            handlers[evt] = handlers[evt] || [];
            handlers[evt].push(fn);
            return obj;
        },
        emit(evt) {
            for (const fn of handlers[evt] || []) {
                fn();
            }
        },
        destroy() {
            obj.destroyed = true;
            obj.active = false;
            obj.scene = null;
        },
        ...extra
    };
    return obj;
}

function createMockCityScene() {
    const sprites = [];
    const images = [];
    const texts = [];
    const tweens = [];
    const timers = [];
    const createdAnims = [];

    const scene = {
        sys: { isActive: () => true },
        cityBackground: createMockGameObject('image', 0, 0, 'city_floor'),
        anims: {
            generateFrameNumbers(key, config) {
                return { key, frames: config && config.frames ? config.frames : null };
            },
            create(def) {
                createdAnims.push(def);
                return def;
            }
        },
        time: {
            delayedCall(delay, cb) {
                const timer = {
                    delay,
                    cb,
                    removed: false,
                    remove() {
                        this.removed = true;
                    }
                };
                timers.push(timer);
                return timer;
            }
        },
        tweens: {
            add(cfg) {
                const tw = {
                    cfg,
                    removed: false,
                    completed: false,
                    remove() {
                        this.removed = true;
                    },
                    triggerStep() {
                        const target = cfg.targets;
                        target.x = cfg.x;
                        if (cfg.y !== undefined) target.y = cfg.y;
                        if (typeof cfg.onUpdate === 'function') cfg.onUpdate(tw, target);
                        this.completed = true;
                        if (typeof cfg.onComplete === 'function') cfg.onComplete();
                    }
                };
                tweens.push(tw);
                return tw;
            }
        },
        add: {
            sprite(x, y, key) {
                const sp = createMockGameObject('sprite', x, y, key, { scene });
                sprites.push(sp);
                return sp;
            },
            image(x, y, key) {
                const img = createMockGameObject('image', x, y, key, { scene });
                images.push(img);
                return img;
            },
            text(x, y, txt, style) {
                const tx = createMockGameObject('text', x, y, null, { scene, text: txt, style });
                texts.push(tx);
                return tx;
            }
        },
        sprites,
        images,
        texts,
        tweensList: tweens,
        timersList: timers,
        createdAnims
    };
    scene.cityBackground.scene = scene;
    return scene;
}

test('all authentic COR city spritesheets exist in workspace and load in LPreLoad & ConfigCityAnims', () => {
    const requiredFiles = [
        'noCarryWorkerD.png',
        'noCarryWorkerR.png',
        'noCarryWorkerL.png',
        'noCarryWorkerUL.png',
        'FarmLabor.png',
        'FarmLaborCarryD.png',
        'FarmLaborCarryR.png',
        'StoneLabor.png',
        'StoneLaborCarryD.png',
        'StoneLaborCarryL.png',
        'IronLabor.png',
        'IronLaborCarryDL.png',
        'IronLaborCarryUL.png',
        'WoodLabor.png',
        'WoodLaborCarryL.png',
        'WoodLaborCarryUL.png',
        'WoodTool.png',
        'PenstockWater.png',
        'HouseSmoke.png',
        'MineSmoke.png',
        'TempleFlame.png',
        'WorkshopElevator.png',
        'PiazzaFlag.png',
        'DakFlag.png',
        'Horse.png',
        'Fountain.png',
        'Woman.png',
        'UpgradingFenceU.png',
        'UpgradingFenceD.png',
        'UpgradingPalaceSupU.png',
        'UpgradingPalaceSupD.png',
        'UpgradingLaborL.png',
        'UpgradingLaborR.png'
    ];
    for (const file of requiredFiles) {
        assert.ok(fs.existsSync(path.join(cityAnimDir, file)), `${file} should exist in ${cityAnimDir}`);
    }

    const start = clientSource.indexOf('Caesar.GE.ConfigCityAnims = function() {');
    const end = clientSource.indexOf('\nCaesar.GE.ConfigAnims = function()', start);
    assert.ok(start >= 0 && end > start, 'ConfigCityAnims block should exist');

    const scene = createMockCityScene();
    let fixedCalled = 0;
    const context = {
        Caesar: { GE: { CityScene: scene } },
        Animation: {
            FixedCityAnimation() {
                fixedCalled++;
            }
        }
    };
    vm.runInNewContext(clientSource.slice(start, end) + '\nCaesar.GE.ConfigCityAnims();', context);

    assert.equal(fixedCalled, 1);
    const animMap = new Map(scene.createdAnims.map(def => [def.key, def]));
    for (const expectedKey of [
        'FarmLabor.A', 'FarmLaborCarryR.A', 'FarmLaborCarryD.A',
        'noCarryWorkerD.A', 'noCarryWorkerR.A', 'noCarryWorkerL.A', 'noCarryWorkerUL.A',
        'StoneLabor.A', 'StoneLaborCarryL.A', 'StoneLaborCarryD.A',
        'IronLabor.A', 'IronLaborCarryDL.A', 'IronLaborCarryUL.A',
        'WoodLabor.A', 'WoodLaborCarryL.A', 'WoodLaborCarryUL.A', 'WoodTool.A',
        'PenstockWater.A', 'HouseSmoke.A', 'MineSmoke.A', 'TempleFlame.A',
        'WorkshopElevator.A', 'PiazzaFlag.A', 'DakFlag.A',
        'Horse.A', 'Fountain.A',
        'WomanFace.A', 'WomanBack.A', 'WomanIdle.A', 'WomanWave.A', 'Woman.A',
        'UpgradingHammer.A', 'UpgradingLaborL.A', 'UpgradingLaborR.A'
    ]) {
        assert.ok(animMap.has(expectedKey), `${expectedKey} animation should be configured`);
    }

    // Woman blank frames (28, 29, 38, 39) must not be played
    assert.deepEqual(Array.from(animMap.get('WomanIdle.A').frames.frames), [20, 21, 22, 23, 24, 25, 26, 27]);
    assert.deepEqual(Array.from(animMap.get('WomanWave.A').frames.frames), [30, 31, 32, 33, 34, 35, 36, 37]);
    assert.equal(animMap.get('Woman.A').frames.frames.length, 36);
    for (const blankFrame of [28, 29, 38, 39]) {
        assert.equal(animMap.get('Woman.A').frames.frames.includes(blankFrame), false);
    }
});

test('building_hammer_animate, AddCityBuilding ambient FX, and cleanup behave accurately', () => {
    const start = clientSource.indexOf('Caesar.GE.clearBuildingUpgradeAnimations = function(bObj) {');
    const end = clientSource.indexOf('\n\n/*!\n * screenfull', start);
    assert.ok(start >= 0 && end > start, 'City building animation & render block should exist');

    const scene = createMockCityScene();
    const upgradingSet = new Set(['palace', 'wall', 'farm', 'under_palace_1']);
    const cityData = {
        id_city: 1,
        lvl: 3,
        food: 100,
        BuildingType: {
            palace: 12,
            wall: 13,
            farm: 14,
            mine: 15,
            stone: 16,
            wood: 17,
            under_palace_1: 1,
            under_palace_2: 4,
            under_palace_3: 5,
            under_palace_4: 7,
            under_palace_5: 9,
            under_palace_6: 10
        },
        BuildingLvl: {
            palace: 5,
            wall: 5,
            farm: 5,
            mine: 5,
            stone: 5,
            wood: 5,
            under_palace_1: 3,
            under_palace_2: 2,
            under_palace_3: 2,
            under_palace_4: 2,
            under_palace_5: 2,
            under_palace_6: 2
        }
    };

    const BUILDING_TYPS = {
        SPACE: 0,
        PALACE: 12,
        WALL: 13
    };
    const BuildingConstData = {
        0: { sprit_name: 'no_building' },
        1: { sprit_name: 'B1', title: 'House' },
        4: { sprit_name: 'B4', title: 'Stable' },
        5: { sprit_name: 'B5', title: 'Workshop' },
        7: { sprit_name: 'B7', title: 'Embassy' },
        9: { sprit_name: 'B9', title: 'Temple' },
        10: { sprit_name: 'B10', title: 'Plaza' },
        12: { sprit_name: 'palace', title: 'Palace' },
        13: { sprit_name: 'wall', title: 'Wall', getSpriteName: () => 'wall_3' },
        14: { sprit_name: 'farm', title: 'Farm' },
        15: { sprit_name: 'mine', title: 'Mine' },
        16: { sprit_name: 'stone', title: 'Stone' },
        17: { sprit_name: 'wood', title: 'Wood' }
    };

    let levelEffectCalls = [];
    let prodRateCalls = 0;
    const context = {
        $: () => ({ length: 0 }),
        BuildingOnFloor: {},
        Phaser: {
            Input: { Events: { GAMEOBJECT_OVER: 'over', GAMEOBJECT_OUT: 'out' } },
            Geom: { Polygon: { Contains() {} } }
        },
        BUILDING_TYPS,
        BuildingConstData,
        cityBuildingInteractiveConfig: () => ({ useHandCursor: true }),
        building_lvl_lable: () => null,
        buildingClick: () => {},
        MouseOverBuilding: () => {},
        MouseOutBuilding: () => {},
        isUpgradingNow: (place) => upgradingSet.has(place),
        Animation: {
            refreshLevelEffects(lvl) {
                levelEffectCalls.push(lvl);
            },
            cityProductionRate() {
                prodRateCalls++;
            }
        },
        Caesar: {
            GE: { CityScene: scene, TextConfig: {} },
            CurrentCity: { City: cityData },
            City: { getCity: () => cityData, getCityBuilding: () => ({ done() {} }) }
        }
    };

    vm.runInNewContext(clientSource.slice(start, end), context);
    context.fillCityWithBuilding();

    assert.deepEqual(levelEffectCalls, [3]);
    assert.equal(prodRateCalls, 1);

    // Palace upgrading uses back/front supports + 4 builders and twin fountains
    const palaceObj = context.BuildingOnFloor.palace;
    assert.equal(palaceObj.BuildingAni.length, 6);
    assert.equal(palaceObj.BuildingAni[0].texture, 'UpgradingPalaceSupU');
    assert.equal(palaceObj.BuildingAni[0].depth, palaceObj.depth - 1);
    assert.equal(palaceObj.BuildingAni[1].texture, 'UpgradingPalaceSupD');
    assert.equal(palaceObj.BuildingAni[1].depth, palaceObj.depth + 1);
    assert.deepEqual(
        Array.from(palaceObj.BuildingAni.slice(2), s => s.texture),
        ['UpgradingLaborL', 'UpgradingLaborR', 'UpgradingLaborR', 'UpgradingLaborR']
    );
    assert.deepEqual(Array.from(palaceObj.AmbientAni, s => s.texture), ['Fountain', 'Fountain']);

    // Wall upgrading places 4 builders along parapet
    assert.equal(context.BuildingOnFloor.wall.BuildingAni.length, 4);
    assert.ok(context.BuildingOnFloor.wall.BuildingAni.every(s => s.texture === 'UpgradingLaborL'));

    // Standard upgrading building attaches UpgradingFenceU/D + 2 builders
    const houseObj = context.BuildingOnFloor.under_palace_1;
    assert.deepEqual(
        Array.from(houseObj.BuildingAni, s => s.texture),
        ['UpgradingFenceU', 'UpgradingFenceD', 'UpgradingLaborL', 'UpgradingLaborR']
    );
    assert.equal(houseObj.BuildingAni[0].depth, houseObj.depth - 1);
    assert.equal(houseObj.BuildingAni[1].depth, houseObj.depth + 1);

    // Building ambient FX check
    assert.equal(houseObj.AmbientAni[0].texture, 'HouseSmoke');
    houseObj.AmbientAni[0].emit('animationcomplete');
    assert.equal(houseObj.AmbientAni[0].visible, false);
    assert.equal(houseObj.AmbientTimers.length, 1);

    const stableObj = context.BuildingOnFloor.under_palace_2;
    assert.equal(stableObj.AmbientAni[0].texture, 'Horse');
    assert.equal(stableObj.AmbientAni[0].x, stableObj.x + 46);
    assert.equal(stableObj.AmbientAni[0].y, stableObj.y + 68);
    assert.equal(stableObj.AmbientAni[0].depth, stableObj.depth + 1);

    assert.equal(context.BuildingOnFloor.under_palace_3.AmbientAni[0].texture, 'WorkshopElevator');
    assert.equal(context.BuildingOnFloor.under_palace_4.AmbientAni[0].texture, 'DakFlag');
    assert.deepEqual(
        Array.from(context.BuildingOnFloor.under_palace_5.AmbientAni, s => s.texture),
        ['TempleFlame', 'TempleFlame']
    );
    assert.equal(context.BuildingOnFloor.under_palace_6.AmbientAni[0].texture, 'PiazzaFlag');
    assert.deepEqual(
        Array.from(context.BuildingOnFloor.mine.AmbientAni, s => s.texture),
        ['MineSmoke', 'MineSmoke']
    );

    // Completing upgrade removes old builders cleanly
    upgradingSet.delete('palace');
    context.Caesar.GE.refreshCityBuildingAnimations();
    assert.equal(palaceObj.BuildingAni.length, 0);

    // Re-running fillCityWithBuilding destroys ambient sprites & clears delayed timers
    const oldTimer = houseObj.AmbientTimers[0];
    const oldHouseSmoke = houseObj.AmbientAni[0];
    context.fillCityWithBuilding();
    assert.equal(oldTimer.removed, true);
    assert.equal(oldHouseSmoke.destroyed, true);
});

test('Animation level effects, citizen/worker patrol routes, and cleanup run without tweens.timeline', () => {
    const start = clientSource.indexOf('\nAnimation = {};') + 1;
    const end = clientSource.indexOf('\nfunction fire_attack_animation()', start);
    assert.ok(start > 0 && end > start, 'Animation module should exist');

    const animationSlice = clientSource.slice(start, end);
    assert.doesNotMatch(animationSlice, /tweens\.(timeline|chain)\b/);

    const scene = createMockCityScene();
    const context = {
        BuildingOnFloor: {
            palace: { x: 416, y: 12 },
            farm: { x: 0, y: 273 },
            wood: { x: 860, y: 148 },
            stone: { x: 600, y: 5 },
            mine: { x: 810, y: 25 }
        },
        X_GRID: 128,
        Y_GRID: 64,
        Caesar: {
            GE: { CityScene: scene },
            CurrentCity: { City: { lvl: 0 } }
        }
    };

    vm.runInNewContext(animationSlice, context);

    // Level 0 must not spawn PenstockWater
    context.Animation.refreshLevelEffects(0);
    assert.equal(context.Animation.LevelEffectSprites.length, 0);

    // Level >= 1 spawns PenstockWater at (10, 63) depth 15
    context.Animation.refreshLevelEffects(2);
    assert.equal(context.Animation.LevelEffectSprites.length, 1);
    const penstock = context.Animation.LevelEffectSprites[0];
    assert.equal(penstock.texture, 'PenstockWater');
    assert.equal(penstock.x, 10);
    assert.equal(penstock.y, 63);
    assert.equal(penstock.depth, 15);

    // Dropping back to level 0 destroys old PenstockWater cleanly
    context.Animation.refreshLevelEffects(0);
    assert.equal(penstock.destroyed, true);
    assert.equal(context.Animation.LevelEffectSprites.length, 0);

    // FixedCityAnimation spawns stationary workers, carriers, citizens, clouds, and PenstockWater for lvl 3
    context.Caesar.CurrentCity.City.lvl = 3;
    context.Animation.FixedCityAnimation();
    assert.equal(context.Animation.LevelEffectSprites.length, 1);
    assert.ok(context.Animation.CityAmbientSprites.length >= 16);
    assert.equal(
        context.Animation.CityAmbientSprites.filter(sp => sp.texture === 'cloud').length,
        2
    );
    assert.equal(
        context.Animation.CityAmbientSprites.filter(sp => sp.texture === 'Woman').length,
        2
    );

    // Advance patrol tweens across one leg transition without throwing
    const initialTweens = scene.tweensList.slice();
    assert.ok(initialTweens.length >= 6);
    for (const tw of initialTweens) {
        tw.triggerStep();
    }
    assert.ok(scene.tweensList.length > initialTweens.length, 'onComplete should chain the next patrol leg');

    // Calling FixedCityAnimation again removes previous tweens and destroys previous sprites
    const firstBatchSprites = context.Animation.CityAmbientSprites.slice();
    const firstBatchTweens = context.Animation.CityAmbientTweens.slice();
    context.Animation.FixedCityAnimation();
    assert.ok(firstBatchSprites.every(sp => sp.destroyed));
    assert.ok(firstBatchTweens.every(tw => tw.removed));
});

test('getCityBuildingTasks refreshes city building animations when active city tasks arrive', () => {
    const start = clientSource.indexOf('Caesar.TimedTask.getCityBuildingTasks = function(idCity) {');
    const end = clientSource.indexOf('\nCaesar.TimedTask.replaceCityTasks = function', start);
    assert.ok(start >= 0 && end > start, 'getCityBuildingTasks should exist');

    let ajaxCfg = null;
    let refreshedList = 0;
    let refreshedAnims = 0;
    let playerDataCalls = 0;
    const context = {
        API_URL: 'https://example.invalid',
        $: {
            ajax(cfg) {
                ajaxCfg = cfg;
                return 'jqxhr';
            }
        },
        Caesar: {
            Config: { idServer: 1, AuthToken: 'tok' },
            LBase: { isJson: () => true, Error() {} },
            CurrentCity: { City: { id_city: 42 } },
            GE: {
                refreshCityBuildingAnimations() {
                    refreshedAnims++;
                }
            },
            TimedTask: {
                TaskList: {
                    Building: {
                        oldTask: { id: 'oldTask', id_city: 42 }
                    }
                },
                refreshListView() {
                    refreshedList++;
                }
            }
        },
        Player_profile: {
            getPlayerBaseData() {
                playerDataCalls++;
                return { fail() {} };
            }
        }
    };

    vm.runInNewContext(clientSource.slice(start, end), context);

    context.Caesar.TimedTask.getCityBuildingTasks(99);
    ajaxCfg.success('[]');
    assert.equal(refreshedAnims, 0, 'non-active city should not trigger active city animation refresh');

    context.Caesar.TimedTask.getCityBuildingTasks(42);
    ajaxCfg.success('[]');
    assert.equal(refreshedList, 2);
    assert.equal(refreshedAnims, 1);
    assert.equal(playerDataCalls, 1);
});

test('FixedCityAnimation and runPatrolRoute start during Phaser CREATING state, keep farm carriers above farm depth, and stop on shutdown', () => {
    const start = clientSource.indexOf('\nAnimation = {};') + 1;
    const end = clientSource.indexOf('\nfunction fire_attack_animation()', start);
    assert.ok(start > 0 && end > start, 'Animation module should exist');

    const scene = createMockCityScene();
    // Simulate Phaser 3.55 Scene.create(): status CREATING (4), so sys.isActive() is false
    scene.sys = {
        isActive: () => false,
        settings: { status: 4 },
        isDestroyed: false
    };

    const context = {
        BuildingOnFloor: {
            palace: { x: 416, y: 12 },
            wall: { x: 0, y: 110, depth: 200 },
            farm: { x: 0, y: 273, depth: 350 },
            wood: { x: 860, y: 148 },
            stone: { x: 600, y: 5 },
            mine: { x: 810, y: 25 }
        },
        X_GRID: 128,
        Y_GRID: 64,
        Caesar: {
            GE: { CityScene: scene },
            CurrentCity: { City: { lvl: 2 } }
        }
    };

    vm.runInNewContext(clientSource.slice(start, end), context);

    assert.equal(context.Animation.isCitySceneAlive(scene, scene.cityBackground), true);
    context.Animation.FixedCityAnimation();

    assert.equal(context.Animation.CityAmbientTweens.length, 9);
    const carriers = context.Animation.CityAmbientSprites.filter(sp => sp.texture === 'FarmLaborCarryD');
    assert.equal(carriers.length, 2);
    assert.equal(carriers[0].currentAnim, 'FarmLaborCarryD.A');
    assert.equal(carriers[1].currentAnim, 'noCarryWorkerL.A');

    // Grain carrier stays above farm (350) inside farm plot, dips below wall (200) at gate/wall crossing, and depth-sorts on boulevard
    const carrierTween = context.Animation.CityAmbientTweens[0];
    assert.equal(carrierTween.cfg.targets, carriers[0]);
    carrierTween.cfg.onUpdate(carrierTween, {
        x: 44,
        y: 324,
        setDepth(d) { carriers[0].depth = d; }
    });
    assert.ok(carriers[0].depth > 350, 'grain carrier inside farm plot should stay above farm floor depth');
    carrierTween.cfg.onUpdate(carrierTween, {
        x: 185,
        y: 332,
        setDepth(d) { carriers[0].depth = d; }
    });
    assert.ok(carriers[0].depth < 200, 'grain carrier passing wall/gate must stay behind wall depth');
    carrierTween.cfg.onUpdate(carrierTween, {
        x: 305,
        y: 271,
        setDepth(d) { carriers[0].depth = d; }
    });
    assert.equal(carriers[0].depth, 289);

    // Iron and Wood carriers reach the Palace vicinity and return to Mine/Sawmill
    const ironCarrier = context.Animation.CityAmbientSprites.find(sp => sp.texture === 'IronLaborCarryUL');
    const woodCarrier = context.Animation.CityAmbientSprites.find(sp => sp.texture === 'WoodLaborCarryUL');
    const women = context.Animation.CityAmbientSprites.filter(sp => sp.texture === 'Woman');
    assert.equal(women.length, 2);
    assert.ok(women.every(w => scene.tweensList.some(tw => tw.cfg.targets === w)), 'both citizens should patrol');

    const latestTweenFor = target => {
        for (let i = scene.tweensList.length - 1; i >= 0; i--) {
            if (scene.tweensList[i].cfg.targets === target) return scene.tweensList[i];
        }
        return null;
    };
    const ironInitialTw = latestTweenFor(ironCarrier);
    ironInitialTw.cfg.onUpdate(ironInitialTw, { x: 754, y: 58, setDepth(d) { ironCarrier.depth = d; } });
    assert.ok(ironCarrier.depth > 90, 'iron carrier on ridge must remain above stone (70) and mine (90) depth');
    for (let step = 0; step < 4; step++) latestTweenFor(ironCarrier).triggerStep();
    assert.ok(ironCarrier.x <= 565 && ironCarrier.y <= 175, 'iron carrier should reach Palace plaza');
    assert.equal(ironCarrier.depth, 186);
    for (let step = 0; step < 4; step++) latestTweenFor(ironCarrier).triggerStep();
    assert.equal(ironCarrier.x, 842);
    assert.equal(ironCarrier.y, 102);

    for (let step = 0; step < 4; step++) latestTweenFor(woodCarrier).triggerStep();
    assert.ok(woodCarrier.x <= 565 && woodCarrier.y <= 175, 'wood carrier should reach Palace plaza');
    for (let step = 0; step < 4; step++) latestTweenFor(woodCarrier).triggerStep();
    assert.equal(woodCarrier.x, 868);
    assert.equal(woodCarrier.y, 226);

    // Chaining continues while SLEEPING (status 7) and halts at SHUTDOWN (status 8) or scene destruction
    scene.sys.settings.status = 7;
    const countBeforeWake = scene.tweensList.length;
    carrierTween.triggerStep();
    assert.equal(scene.tweensList.length, countBeforeWake + 1);

    scene.sys.settings.status = 8;
    const latestTween = scene.tweensList[scene.tweensList.length - 1];
    latestTween.triggerStep();
    assert.equal(scene.tweensList.length, countBeforeWake + 1, 'SHUTDOWN scene must stop spawning patrol legs');

    scene.sys.settings.status = 5;
    scene.sys.isDestroyed = true;
    assert.equal(context.Animation.isCitySceneAlive(scene, carriers[0]), false);
});

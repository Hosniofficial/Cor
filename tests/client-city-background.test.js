const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const clientSource = fs.readFileSync(
    path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js'),
    'utf8'
).replace(/\r\n/g, '\n');

test('city level-zero background reuses the shared city floor asset via off-thread lazy loading', () => {
    const imagesPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'images', 'city');
    assert.ok(fs.existsSync(path.join(imagesPath, 'city_floor.jpg')));
    assert.equal(fs.existsSync(path.join(imagesPath, 'background_townBackground0.jpg')), false);
    assert.match(clientSource, /city_floor:\s*"images\/city\/city_floor\.jpg"/);
    assert.doesNotMatch(clientSource, /load\.image\('town_bg_0'/);
    assert.match(clientSource, /var bgKey = "city_floor";/);
    assert.match(clientSource, /var curBgKey = "city_floor";/);
    const lpreloadStart = clientSource.indexOf('Caesar.GE.LPreLoad = function()');
    const lpreloadEnd = clientSource.indexOf('\nCaesar.GE.ConfigCityAnims', lpreloadStart);
    const lpreloadBlock = clientSource.slice(lpreloadStart, lpreloadEnd);
    assert.doesNotMatch(lpreloadBlock, /'(city_floor|town_bg_[123]|city_backdrop|city_wallpage)'/);
    assert.match(clientSource, /Caesar\.GE\.ensureCityBackground\s*=\s*function\([\s\S]*?createImageBitmap\(blob\)/);
});

test('unused harbor and lighthouse assets are not loaded or shipped', () => {
    const imagesPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'images', 'city');

    for (const [key, filename] of [
        ['seaport', '_seaport.png'],
        ['lighthouse', '_lighthouse.png']
    ]) {
        assert.doesNotMatch(clientSource, new RegExp(`load\\.image\\('${key}'`));
        assert.doesNotMatch(clientSource, new RegExp(`images/city/${filename.replace('.', '\\.')}`));
        assert.equal(fs.existsSync(path.join(imagesPath, filename)), false, `${filename} is unused`);
    }
});

function readJpegDimensions(filePath) {
    const buf = fs.readFileSync(filePath);
    assert.equal(buf.readUInt16BE(0), 0xFFD8, `${path.basename(filePath)} must be a valid JPEG`);
    let offset = 2;
    while (offset + 8 < buf.length) {
        const marker = buf.readUInt16BE(offset);
        const segLen = buf.readUInt16BE(offset + 2);
        if (marker >= 0xFFC0 && marker <= 0xFFC3) {
            return {
                height: buf.readUInt16BE(offset + 5),
                width: buf.readUInt16BE(offset + 7)
            };
        }
        offset += 2 + segLen;
    }
    throw new Error(`No SOF header found in ${filePath}`);
}

test('extended 4K 16:9 city backgrounds preserve seamless Roman countryside dimensions', () => {
    const imagesPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'images', 'city');
    for (const filename of [
        'city_floor.jpg',
        'background_townBackground1.jpg',
        'background_townBackground2.jpg',
        'background_townBackground3.jpg'
    ]) {
        const dims = readJpegDimensions(path.join(imagesPath, filename));
        assert.deepEqual(dims, { width: 3840, height: 2160 }, `${filename} should be 3840x2160`);
    }
    const backdropDims = readJpegDimensions(path.join(imagesPath, 'bg0.jpg'));
    assert.deepEqual(backdropDims, { width: 642, height: 422 }, 'bg0.jpg should be 642x422');
});

test('GameWindow canvas CSS does not stretch mismatched aspect ratios with 100% width/height', () => {
    const cssRoot = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113');
    for (const cssFile of ['lastOfLast.css', 'overrides.bundle.css']) {
        const css = fs.readFileSync(path.join(cssRoot, cssFile), 'utf8');
        const match = css.match(/#GameWindow\s+canvas\s*\{([^}]*)\}/);
        assert.ok(match, `${cssFile} must define #GameWindow canvas rule`);
        assert.doesNotMatch(match[1], /\bwidth\s*:\s*100%/i, `${cssFile} must not force canvas width: 100%`);
        assert.doesNotMatch(match[1], /\bheight\s*:\s*100%/i, `${cssFile} must not force canvas height: 100%`);
    }
});

test('phaserConfig enables Phaser.Scale.RESIZE, antialias, and mipmap filtering', () => {
    assert.match(clientSource, /mode:\s*Phaser\.Scale\.RESIZE/);
    assert.match(clientSource, /autoCenter:\s*Phaser\.Scale\.NO_CENTER/);
    assert.match(clientSource, /antialias:\s*true/);
    assert.match(clientSource, /mipmapFilter:\s*"LINEAR_MIPMAP_LINEAR"/);
    assert.match(clientSource, /Caesar\.GE\.Game\.scale\.resize\(nextW,\s*nextH\)/);
    assert.match(clientSource, /Caesar\.GE\.CITY_BG_BOUNDS\s*=/);
    assert.match(clientSource, /cityBackground\.setDisplaySize\(bgBounds\.width,\s*bgBounds\.height\)/);
});

test('CCityScene aligns 16:9 countryside scale with World Map proportions and zero empty borders across HD/2K/4K/Ultrawide', () => {
    const vm = require('node:vm');
    const startIdx = clientSource.indexOf('Caesar.GE.CITY_BG_BOUNDS =');
    const endIdx = clientSource.indexOf('Caesar.GE.CWorldScene = new Phaser.Class({');
    assert.ok(startIdx !== -1 && endIdx > startIdx, 'Could not locate CCityScene block');
    const sceneSnippet = clientSource.slice(startIdx, endIdx);

    const resizeHandlers = [];
    const inputHandlers = {};
    let tileSpriteCalled = false;
    let backgroundPos = null;
    let backgroundDisplaySize = null;

    const cam = {
        width: 1920,
        height: 1080,
        zoom: 1,
        scrollX: 0,
        scrollY: 0,
        setZoom(z) { this.zoom = z; return this; },
        centerOn(x, y) {
            // Exact Phaser 3.55.2 BaseCamera#centerOnX/Y formula (origin = 0.5)
            this.scrollX = x - (this.width * 0.5);
            this.scrollY = y - (this.height * 0.5);
            return this;
        },
        getWorldView() {
            // Exact Phaser 3.55.2 Camera#preRender worldView calculation
            const midX = this.scrollX + (this.width * 0.5);
            const midY = this.scrollY + (this.height * 0.5);
            const displayW = this.width / this.zoom;
            const displayH = this.height / this.zoom;
            return {
                midX,
                midY,
                left: midX - (displayW * 0.5),
                right: midX + (displayW * 0.5),
                top: midY - (displayH * 0.5),
                bottom: midY + (displayH * 0.5),
                displayW,
                displayH
            };
        }
    };

    const sceneInstance = {
        events: {
            on() {},
            once() {}
        },
        input: {
            mousePointer: { motionFactor: 1 },
            pointer1: { motionFactor: 1 },
            on(evt, fn) { inputHandlers[evt] = fn; }
        },
        cameras: { main: cam },
        scale: {
            on(evt, fn) { if (evt === 'resize') resizeHandlers.push(fn); },
            off() {}
        },
        add: {
            tileSprite() {
                tileSpriteCalled = true;
                const obj = {
                    setOrigin() { return obj; },
                    setDepth() { return obj; },
                    setTileScale() { return obj; }
                };
                return obj;
            },
            image(x, y) {
                backgroundPos = [x, y];
                const obj = {
                    setOrigin() { return obj; },
                    setDepth() { return obj; },
                    setDisplaySize(w, h) { backgroundDisplaySize = [w, h]; return obj; }
                };
                return obj;
            }
        }
    };

    const sandbox = {
        Caesar: {
            GE: {
                ConfigCityAnims() {},
                Game: { canvas: {} }
            },
            CurrentCity: { City: { lvl: 1 } }
        },
        BUILDING_TYPS: {},
        fillCityWithBuilding() {},
        window: { innerWidth: 1920, innerHeight: 1080 },
        Phaser: {
            Scene: function() {},
            Class: function(def) { return def; },
            Math: {
                Clamp(val, min, max) { return Math.min(Math.max(val, min), max); }
            }
        }
    };
    vm.createContext(sandbox);
    vm.runInContext(sceneSnippet, sandbox);
    sandbox.Caesar.GE.CCityScene.create.call(sceneInstance);

    const bgBounds = sandbox.Caesar.GE.CITY_BG_BOUNDS;
    const bgRight = bgBounds.left + bgBounds.width;
    const bgBottom = bgBounds.top + bgBounds.height;
    assert.deepEqual(backgroundPos, [bgBounds.left, bgBounds.top]);
    assert.deepEqual(backgroundDisplaySize, [bgBounds.width, bgBounds.height]);
    assert.equal(tileSpriteCalled, false, 'CCityScene must not allocate an extra 6000x4000 city_backdrop tileSprite under the full countryside image');
    assert.equal(resizeHandlers.length, 1);

    for (const [winW, winH] of [
        [1366, 768],
        [1920, 1080],
        [2560, 1440],
        [3840, 2160],
        [3440, 1080]
    ]) {
        cam.width = winW;
        cam.height = winH;
        resizeHandlers[0]({ width: winW, height: winH });
        const view = cam.getWorldView();
        // Whole viewport stays inside extended 16:9 countryside bounds with zero empty border
        assert.ok(view.left >= bgBounds.left - 1e-3 && view.right <= bgRight + 1e-3, `${winW}x${winH}: worldView [${view.left}..${view.right}] must stay strictly inside [${bgBounds.left}..${bgRight}]`);
        assert.ok(view.top >= bgBounds.top - (45 / cam.zoom) - 1e-3 && view.bottom <= bgBottom + 1e-3, `${winW}x${winH}: worldView top/bottom [${view.top}..${view.bottom}] must fill height without exposing bg0`);
        const syTop = (bgBounds.top - view.top) * cam.zoom;
        const syBottom = (bgBottom - view.top) * cam.zoom;
        assert.ok(syTop <= 45.01, `${winW}x${winH}: top edge (${syTop}) must stay behind #menu-bar`);
        assert.ok(syBottom >= winH - 0.01, `${winW}x${winH}: bottom edge (${syBottom}) must reach bottom of viewport (${winH})`);
        // Full 1000x400 city plateau fits comfortably on 16:9 screens without 2.6x oversized buildings
        if (winW / winH <= 2.0) {
            assert.ok(view.left <= 0.01 && view.right >= 999.99, `${winW}x${winH}: full [0..1000] city plateau must fit horizontally`);
            assert.ok(view.top <= 0.01 && view.bottom >= 399.99, `${winW}x${winH}: full [0..400] city plateau must fit vertically`);
        }
        assert.equal(sandbox.Caesar.MAX_SCREEN_WIDTH, winW);
        assert.equal(sandbox.Caesar.MAX_SCREEN_HEIGHT, winH);
    }

    // Verify 1080p scale sits nicely balanced near World Map scale (~1.54x) instead of 2.6x oversized zoom
    cam.width = 1920;
    cam.height = 1080;
    resizeHandlers[0]({ width: 1920, height: 1080 });
    assert.ok(cam.zoom >= 1.45 && cam.zoom <= 1.60, `1080p city zoom (${cam.zoom}) should stay balanced near ~1.54x`);
    const baseZoom = cam.zoom;
    const canvasEl = sandbox.Caesar.GE.Game.canvas;

    // Verify wheel zoom in + bounded mouse pan inside extended countryside + zoom back out
    for (let i = 0; i < 6; i++) {
        inputHandlers.wheel({}, [], 0, -100, 0);
    }
    assert.ok(cam.zoom > baseZoom, 'scroll up should zoom in');
    inputHandlers.pointerdown();
    inputHandlers.pointermove({ isDown: true, downX: 0, downY: 500, x: 2000, prevPosition: { x: 0, y: 500 }, y: 500, downElement: canvasEl });
    assert.ok(Math.abs(cam.getWorldView().left - bgBounds.left) < 1e-2, 'pan left when zoomed should stop strictly at bgLeft');
    inputHandlers.pointermove({ isDown: true, downX: 0, downY: 500, x: -2000, prevPosition: { x: 0, y: 500 }, y: 500, downElement: canvasEl });
    assert.ok(Math.abs(cam.getWorldView().right - bgRight) < 1e-2, 'pan right when zoomed should stop strictly at bgRight');

    // Verify click suppression after dragging (> 6px) and click preservation for small clicks (<= 6px)
    let clickCount = 0;
    const mockBuildingObj = { emit(evt) { if (evt === 'click') clickCount++; } };
    const noopEvt = { stopPropagation() {} };
    inputHandlers.gameobjectdown({}, mockBuildingObj, noopEvt);
    inputHandlers.gameobjectup({ downElement: canvasEl, getDuration: () => 120, downX: 100, downY: 100, x: 140, y: 100 }, mockBuildingObj, noopEvt);
    assert.equal(clickCount, 0, 'dragging > 6px should not trigger building click');
    inputHandlers.pointerup();
    inputHandlers.gameobjectdown({}, mockBuildingObj, noopEvt);
    inputHandlers.gameobjectup({ downElement: canvasEl, getDuration: () => 80, downX: 100, downY: 100, x: 102, y: 101 }, mockBuildingObj, noopEvt);
    assert.equal(clickCount, 1, 'normal click <= 6px movement should trigger building click');

    for (let i = 0; i < 12; i++) {
        inputHandlers.wheel({}, [], 0, 100, 0);
    }
    assert.ok(Math.abs(cam.zoom - baseZoom) < 1e-6, 'scroll down should clamp cleanly back to minCityZoom');
});

test('compact pinned bottom HUD eliminates white root background and locks Player/City panels', () => {
    const cssRoot = path.join(__dirname, '..', 'caesar-assets', 'web', 'css-3.4.113');
    const overrides = fs.readFileSync(path.join(cssRoot, 'overrides.bundle.css'), 'utf8');
    const serverPhp = fs.readFileSync(path.join(__dirname, '..', 'caesar-web', 'server.php'), 'utf8');

    assert.match(overrides, /html,\s*body\s*\{[^}]*background-color:\s*#0e0a07\s*!important;[^}]*overflow:\s*hidden\s*!important;/i);
    assert.match(overrides, /#GameWindow\s*\{[^}]*background-color:\s*#0e0a07;/i);
    assert.match(overrides, /#p-provile-slider,\s*#city-profile-slider\s*\{\s*display:\s*none\s*!important;\s*\}/i);
    assert.match(overrides, /@media\s*\(min-width:\s*761px\)\s*and\s*\(min-height:\s*651px\)\s*\{[\s\S]*?#player-profile\s*\{[\s\S]*?transform:\s*scale\(0\.80\)[\s\S]*?#city-profile\s*\{[\s\S]*?transform:\s*scale\(0\.80\)[\s\S]*?#chat-area\s*\{\s*height:\s*108px;/);

    assert.match(serverPhp, /<div class="chat-top-spacer"><\/div>/);
    assert.doesNotMatch(serverPhp, /<div style="height:\s*20px;"><\/div>/);

    assert.match(clientSource, /Caesar\.GE\.syncGameScale\s*=\s*function\(\)[\s\S]*?getParentBounds[\s\S]*?scale\.resize\(nextW,\s*nextH\)/);
    assert.doesNotMatch(clientSource, /\$\("#p-provile-slider img"\)\.click\(/);
    assert.doesNotMatch(clientSource, /\$\("#city-profile-slider img"\)\.click\(/);
    assert.match(clientSource, /\$\("#UPDOWN-chat img"\)\.click\([\s\S]*?chat-collapsed/);
    assert.match(clientSource, /#expand-chat \.expand[\s\S]*?height:\s*""/);
});

function readPngDimensions(filePath) {
    const buf = fs.readFileSync(filePath);
    assert.equal(buf.readUInt32BE(0), 0x89504E47, `${path.basename(filePath)} must be a valid PNG`);
    return {
        width: buf.readUInt32BE(16),
        height: buf.readUInt32BE(20)
    };
}

test('completed city wall tiers and farm sprites avoid viewport-edge cuts and keep world anchors invariant', () => {
    const imagesPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'images', 'city');
    assert.deepEqual(readPngDimensions(path.join(imagesPath, '_farm.png')), { width: 176, height: 100 });
    for (let tier = 0; tier <= 4; tier++) {
        assert.deepEqual(
            readPngDimensions(path.join(imagesPath, `wall_${tier}_.png`)),
            { width: 620, height: 430 },
            `wall_${tier}_.png should use expanded 620x430 corner-complete canvas`
        );
    }

    assert.match(clientSource, /BuildingOnFloor\.wall\s*=\s*Caesar\.GE\.CityScene\.add\.image\(0,\s*110,\s*wallSprite\)[\s\S]*?\.setOrigin\(72\s*\/\s*620,\s*36\s*\/\s*430\)/);
    assert.match(clientSource, /BuildingOnFloor\.farm\s*=\s*Caesar\.GE\.CityScene\.add\.image\(0,\s*273,\s*"farm"\)[\s\S]*?\.setOrigin\(48\s*\/\s*176,\s*0\)/);
    assert.match(clientSource, /return\s*\[20,\s*50,\s*20,\s*165,\s*244,\s*271,\s*357,\s*326,\s*582,\s*425,/);
});

test('City <-> World switching uses sleep/wake reuse, guarded animations, floor dedup, and non-thrashing DOM updates', () => {
    const vm = require('node:vm');

    // 1. Animation creation guards prevent rebuilding global sprite sheet animations
    assert.match(clientSource, /Caesar\.GE\.ConfigCityAnims\s*=\s*function\(\)\s*\{[\s\S]*?if\s*\(typeof Scene\.anims\.exists !== 'function'\s*\|\|\s*!Scene\.anims\.exists\('FarmLabor\.A'\)\)/);
    assert.match(clientSource, /Caesar\.GE\.ConfigAnims\s*=\s*function\(\)\s*\{[\s\S]*?if\s*\(typeof Scene\.anims\.exists !== 'function'\s*\|\|\s*!Scene\.anims\.exists\('Forest\.1'\)\)/);

    // 2. #WorldCity toggle uses switchSceneView + .hide()/.show() instead of .scene.start() / .fadeOut()/.fadeIn() / unit teardown
    const toggleStart = clientSource.indexOf('$("#WorldCity").click(function()');
    const toggleEnd = clientSource.indexOf('$(document).on("click", "#player-city-list ul li"', toggleStart);
    assert.ok(toggleStart !== -1 && toggleEnd > toggleStart, 'Could not locate #WorldCity click handler');
    const toggleBlock = clientSource.slice(toggleStart, toggleEnd);
    assert.match(toggleBlock, /Caesar\.GE\.switchSceneView\("World",\s*"City"\)/);
    assert.match(toggleBlock, /Caesar\.GE\.switchSceneView\("City",\s*"World"\)/);
    assert.doesNotMatch(toggleBlock, /\.scene\.start\(/);
    assert.doesNotMatch(toggleBlock, /\.fadeOut\(|\.fadeIn\(/);
    assert.doesNotMatch(toggleBlock, /getWorldSceneUnits\(/);
    assert.match(toggleBlock, /\$\("#smallMap-icon"\)\.hide\(\)/);
    assert.match(toggleBlock, /\$\("#smallMap-icon"\)\.show\(\)/);

    // 3. No 6000x4000 cityBackdrop tileSprite allocation in CCityScene
    const cityStart = clientSource.indexOf('Caesar.GE.CCityScene = new Phaser.Class({');
    const cityEnd = clientSource.indexOf('Caesar.GE.CWorldScene = new Phaser.Class({', cityStart);
    assert.doesNotMatch(clientSource.slice(cityStart, cityEnd), /cityBackdrop|tileSprite\(/);

    // 4. World floor tiles mark isFloor and WorldFire / cityFlag skip floor sprites & redundant flag text updates
    assert.match(clientSource, /unit\.floor\.data\.set\("isFloor",\s*true\)/);
    assert.match(clientSource, /Animation\.WorldFire\s*=\s*function\(\)\s*\{[\s\S]*?if\s*\(!El\.data\s*\|\|\s*El\.data\.get\("isFloor"\)\s*===\s*true\)\s*return;/);
    assert.match(clientSource, /Animation\.cityFlag\s*=\s*function\(\)\s*\{[\s\S]*?if\s*\(!El\.data\s*\|\|\s*El\.data\.get\("isFloor"\)\s*===\s*true\)\s*return;/);
    assert.match(clientSource, /if\s*\(Unit\.CityFlagTextEntite\.text\s*!==\s*playerFlag\)\s*\{\s*Unit\.CityFlagTextEntite\.setText\(playerFlag\);/);

    // 5. Server clock caches element and updates textContent only when changed
    assert.match(clientSource, /function dateTimeFormat\(time\)[\s\S]*?\\u00A0/);
    assert.match(clientSource, /if\s*\(nextClockText\s*!==\s*LAST_SERVER_CLOCK_TEXT\)\s*\{[\s\S]*?SERVER_CLOCK_EL\.textContent\s*=\s*nextClockText;/);
    assert.doesNotMatch(clientSource, /SERVER_CLOCK\.html\(/);

    // 6. Execute helper behavior in VM: switchSceneView & sleep/wake rebuild guards
    const helperStart = clientSource.indexOf('Caesar.GE.isSceneSleeping = function(');
    const helperEnd = clientSource.indexOf('$(document).ready(function()', helperStart);
    assert.ok(helperStart !== -1 && helperEnd > helperStart, 'Could not locate Caesar.GE scene switch helpers');
    const helperCode = clientSource.slice(helperStart, helperEnd);

    const switchCalls = [];
    const pluginSwitchCalls = [];
    const fakeSceneMgr = {
        isProcessing: false,
        getScene(key) {
            return {
                sys: { isSleeping: () => key === 'World' },
                scene: { switch(to) { pluginSwitchCalls.push([key, to]); } }
            };
        },
        switch(from, to) { switchCalls.push([from, to]); }
    };
    const helperSandbox = {
        Caesar: { GE: { Game: { scene: fakeSceneMgr } } }
    };
    vm.createContext(helperSandbox);
    vm.runInContext(helperCode, helperSandbox);

    assert.equal(helperSandbox.Caesar.GE.isSceneSleeping(fakeSceneMgr.getScene('World')), true);
    assert.equal(helperSandbox.Caesar.GE.isSceneSleeping(fakeSceneMgr.getScene('City')), false);

    helperSandbox.Caesar.GE.switchSceneView('City', 'World');
    assert.deepEqual(switchCalls, [['City', 'World']]);
    assert.deepEqual(pluginSwitchCalls, []);

    fakeSceneMgr.isProcessing = true;
    helperSandbox.Caesar.GE.switchSceneView('World', 'City');
    assert.deepEqual(pluginSwitchCalls, [['World', 'City']]);

    // 7. Execute CCityScene wake listener to confirm fillCityWithBuilding skips same clean city and runs when dirty or city switched
    const citySnippet = clientSource.slice(clientSource.indexOf('Caesar.GE.CITY_BG_BOUNDS ='), cityEnd);
    let fillCalls = 0;
    const eventsMap = {};
    const mockCam = { width: 1920, height: 1080, zoom: 1, scrollX: 0, scrollY: 0, setZoom(z) { this.zoom = z; return this; }, centerOn() { return this; } };
    const mockCityScene = {
        events: {
            on(evt, fn) { eventsMap[evt] = fn; },
            once() {},
            off() {}
        },
        input: { mousePointer: {}, pointer1: {}, on() {} },
        cameras: { main: mockCam },
        scale: { on() {}, off() {} },
        add: { image() { const o = { setOrigin() { return o; }, setDepth() { return o; }, setDisplaySize() { return o; } }; return o; } }
    };
    const citySandbox = {
        Caesar: {
            GE: { ConfigCityAnims() {}, Game: { canvas: {} }, isSceneSleeping: helperSandbox.Caesar.GE.isSceneSleeping, CityDirty: false, LastCityIdRendered: 101 },
            CurrentCity: { City: { lvl: 1, id_city: 101 } }
        },
        BUILDING_TYPS: {},
        fillCityWithBuilding() { fillCalls++; citySandbox.Caesar.GE.CityDirty = false; citySandbox.Caesar.GE.LastCityIdRendered = Number(citySandbox.Caesar.CurrentCity.City.id_city); },
        window: { innerWidth: 1920, innerHeight: 1080 },
        Phaser: { Scene: function() {}, Class: function(def) { return def; }, Math: { Clamp: (v, a, b) => Math.min(Math.max(v, a), b) } }
    };
    vm.createContext(citySandbox);
    vm.runInContext(citySnippet, citySandbox);
    citySandbox.Caesar.GE.CCityScene.create.call(mockCityScene);
    assert.equal(fillCalls, 1, 'Initial create populates buildings once');

    eventsMap.wake();
    assert.equal(fillCalls, 1, 'Waking same clean city skips full building rebuild');

    citySandbox.Caesar.GE.CityDirty = true;
    eventsMap.wake();
    assert.equal(fillCalls, 2, 'Waking dirty city triggers one rebuild');

    citySandbox.Caesar.CurrentCity.City.id_city = 202;
    eventsMap.wake();
    assert.equal(fillCalls, 3, 'Waking after switching active city triggers rebuild for new city');

    // 8. Verify #player-city-list click handler defines `view` before checking `if (view === "world")`
    const citySwitchStart = clientSource.indexOf('$(document).on("click", "#player-city-list ul li", function()');
    const citySwitchEnd = clientSource.indexOf('$(document).on("click", ".nav-to-city"', citySwitchStart);
    assert.ok(citySwitchStart !== -1 && citySwitchEnd > citySwitchStart, 'Could not locate #player-city-list click handler');
    const citySwitchHandler = clientSource.slice(citySwitchStart, citySwitchEnd);
    assert.match(citySwitchHandler, /var view\s*=\s*\$\("#WorldCity"\)\.attr\("data-view"\);/);
    assert.match(citySwitchHandler, /if\s*\(view === "world"\)/);
});

test('lazy city background loader decodes off-thread via createImageBitmap, deduplicates requests, and throttles progress DOM writes', async () => {
    const vm = require('node:vm');
    assert.match(clientSource, /loader:\s*\{\s*maxParallelDownloads:\s*8\s*\}/);

    const start = clientSource.indexOf('Caesar.GE.CITY_BG_KEY_FOR_LVL = function(');
    const end = clientSource.indexOf('Caesar.GE.CLoadingScene = new Phaser.Class({', start);
    assert.ok(start !== -1 && end > start, 'Could not locate lazy loader & progress helpers');
    const code = clientSource.slice(start, end);

    const addedTextures = new Map();
    const fetchedUrls = [];
    let domWrites = 0;
    const pctEl = {
        isConnected: true,
        _txt: '',
        set textContent(v) { domWrites++; this._txt = v; },
        get textContent() { return this._txt; }
    };
    const barEl = {
        isConnected: true,
        style: { width: '' }
    };
    const sandbox = {
        BASE_ASSET_BATH: 'assets/',
        Caesar: { GE: {} },
        document: {
            getElementById: (id) => id === 'load-percent' ? pctEl : null,
            querySelector: (sel) => sel === '#load-bar div' ? barEl : null
        },
        fetch: async (url) => {
            fetchedUrls.push(url);
            return { ok: true, blob: async () => ({ size: 123 }) };
        },
        createImageBitmap: async () => ({ width: 3840, height: 2160 }),
        Promise,
        Error,
        Math
    };
    sandbox.Caesar.GE.Game = {
        textures: {
            exists: (k) => addedTextures.has(k),
            addImage: (k, img) => addedTextures.set(k, img)
        }
    };
    vm.createContext(sandbox);
    vm.runInContext(code, sandbox);

    assert.equal(sandbox.Caesar.GE.CITY_BG_KEY_FOR_LVL(0), 'city_floor');
    assert.equal(sandbox.Caesar.GE.CITY_BG_KEY_FOR_LVL(1), 'town_bg_1');
    assert.equal(sandbox.Caesar.GE.CITY_BG_KEY_FOR_LVL(2), 'town_bg_2');
    assert.equal(sandbox.Caesar.GE.CITY_BG_KEY_FOR_LVL(3), 'town_bg_3');

    // Concurrent calls for the same key trigger only one fetch + decode and notify every caller
    const cbResults = [];
    sandbox.Caesar.GE.ensureCityBackground('town_bg_3', (k) => cbResults.push(['first', k]));
    sandbox.Caesar.GE.ensureCityBackground('town_bg_3', (k) => cbResults.push(['second', k]));
    await new Promise((r) => setImmediate(r));
    await new Promise((r) => setImmediate(r));

    assert.deepEqual(fetchedUrls, ['assets/images/city/background_townBackground3.jpg']);
    assert.equal(addedTextures.get('town_bg_3').width, 3840);
    assert.deepEqual(cbResults, [['first', 'town_bg_3'], ['second', 'town_bg_3']]);

    // Loading progress bar skips redundant DOM updates within the same integer percentage
    sandbox.Caesar.GE.Loading(42.1);
    sandbox.Caesar.GE.Loading(42.9);
    sandbox.Caesar.GE.Loading(43.0);
    assert.equal(domWrites, 2);
    assert.equal(pctEl.textContent, '43%');
    assert.equal(barEl.style.width, '43%');
});

test('hover handlers and city-switch DOM refreshes avoid forced reflows and reuse pooled tooltips', () => {
    const vm = require('node:vm');

    // 1. CCityScene hover & building clicks avoid jQuery :visible forced reflow selectors and reuse ConstructingHammer
    const cityStart = clientSource.indexOf('Caesar.GE.CCityScene = new Phaser.Class({');
    const cityEnd = clientSource.indexOf('Caesar.GE.CWorldScene = new Phaser.Class({', cityStart);
    const citySource = clientSource.slice(cityStart, cityEnd);
    assert.doesNotMatch(citySource, /:visible/);
    assert.match(citySource, /isModalBlockingCityInput\(false\)/);
    assert.match(citySource, /This\.ConstructingHammer\.setPosition\(GameObject\.x,\s*GameObject\.y - 32\)/);

    const fixedStart = clientSource.indexOf('Caesar.GE.AddCityBuilding = function(');
    const fixedEnd = clientSource.indexOf('function fillCityWithBuilding()', fixedStart);
    assert.doesNotMatch(clientSource.slice(fixedStart, fixedEnd), /:visible/);

    // 2. TimedTask.refreshListView batches DOM insertion and refreshTime updates style.width cleanly
    const tskStart = clientSource.indexOf('Caesar.TimedTask.refreshListView = function()');
    const tskEnd = clientSource.indexOf('Caesar.TimedTask.getCityBuildingTasks', tskStart);
    const tskSource = clientSource.slice(tskStart, tskEnd);
    assert.doesNotMatch(tskSource, /\.append\(/);
    assert.match(tskSource, /\$\("#build-tsk"\)\.html\(buildHtml\)/);

    const rtStart = clientSource.indexOf('function refreshTime()');
    const rtEnd = clientSource.indexOf('Caesar.WsLib.TimedTask.Army', rtStart);
    assert.doesNotMatch(clientSource.slice(rtStart, rtEnd), /\.prog-bar"\)\.width\(/);
    assert.match(clientSource.slice(rtStart, rtEnd), /progBarEl\.style\.width = clampedW/);

    // 3. Test isModalBlockingCityInput accurately without layout reads
    const helperStart = clientSource.indexOf('Caesar.GE.isModalBlockingCityInput = function(');
    const helperEnd = clientSource.indexOf('$(document).ready(function()', helperStart);
    const domById = new Map();
    const modalOverlays = [];
    const modalSandbox = {
        Caesar: { GE: {} },
        document: {
            getElementById: (id) => domById.get(id) || null,
            getElementsByClassName: (cls) => cls === 'modal-overlay' ? modalOverlays : []
        }
    };
    vm.createContext(modalSandbox);
    vm.runInContext(clientSource.slice(helperStart, helperEnd), modalSandbox);

    assert.equal(modalSandbox.Caesar.GE.isModalBlockingCityInput(false), false);
    domById.set('dialg_box', {
        isConnected: true,
        style: { display: 'block' },
        hidden: false,
        classList: { contains: (c) => c === 'for_building' }
    });
    assert.equal(modalSandbox.Caesar.GE.isModalBlockingCityInput(false), true);
    assert.equal(modalSandbox.Caesar.GE.isModalBlockingCityInput(true), false, 'Building dialog does not block other building clicks');
    domById.set('alert_container', { isConnected: true, style: { display: 'block' }, hidden: false });
    assert.equal(modalSandbox.Caesar.GE.isModalBlockingCityInput(true), true, 'Alert container blocks building clicks even over building dialog');

    // 4. Test WorldMapIcon.showUnitCoords pools Text and Bg instead of recreating on every hover
    const coordStart = clientSource.indexOf('Caesar.World.WorldMapIcon.removeUnitCoords = function()');
    const coordEnd = clientSource.indexOf('Caesar.World.WorldMapIcon.tweenWorldUnitIcons', coordStart);
    let textAdds = 0;
    let imgAdds = 0;
    let textSetCalls = 0;
    const coordScene = {
        UnitCoords: null,
        add: {
            text(x, y, txt) {
                textAdds++;
                const obj = {
                    scene: coordScene,
                    visible: true,
                    setDepth() { return obj; },
                    setOrigin() { return obj; },
                    setPosition() { return obj; },
                    setVisible(v) { obj.visible = v; return obj; },
                    setText(t) { textSetCalls++; obj.txt = t; return obj; }
                };
                return obj;
            },
            image() {
                imgAdds++;
                const obj = {
                    scene: coordScene,
                    visible: true,
                    setDepth() { return obj; },
                    setScale() { return obj; },
                    setPosition() { return obj; },
                    setVisible(v) { obj.visible = v; return obj; }
                };
                return obj;
            }
        }
    };
    const coordSandbox = {
        Caesar: {
            GE: { WorldScene: coordScene },
            World: { WorldMapIcon: {}, Map: { posX: (x) => x * 64, posY: (x, y) => y * 32, posZ: () => 5 } }
        }
    };
    vm.createContext(coordSandbox);
    vm.runInContext(clientSource.slice(coordStart, coordEnd), coordSandbox);

    coordSandbox.Caesar.World.WorldMapIcon.showUnitCoords(10, 20);
    coordSandbox.Caesar.World.WorldMapIcon.showUnitCoords(10, 20);
    coordSandbox.Caesar.World.WorldMapIcon.showUnitCoords(11, 21);
    coordSandbox.Caesar.World.WorldMapIcon.removeUnitCoords();
    assert.equal(textAdds, 1, 'showUnitCoords must create only 1 pooled Text entity across tile hovers');
    assert.equal(imgAdds, 1, 'showUnitCoords must create only 1 pooled CoordHolder image across tile hovers');
    assert.equal(textSetCalls, 1, 'showUnitCoords only re-renders text when coordinates actually change');
    assert.equal(coordScene.UnitCoords.Bg.visible, false);

    // 5. Verify unused heavy WebGL shaders (Light2D / PointLight / Rope) are pruned before TextureEvents.READY
    assert.match(clientSource, /glPipelines\.delete\(pipelineConst\.LIGHT_PIPELINE\)/);
    assert.match(clientSource, /glPipelines\.delete\(pipelineConst\.POINTLIGHT_PIPELINE\)/);
    assert.match(clientSource, /glPipelines\.delete\(pipelineConst\.ROPE_PIPELINE\)/);
});

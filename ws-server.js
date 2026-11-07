// WebSocket + HTTP Server for ws.elkaisar.com:8081
// Handles real-time WebSocket connections, combat marches, battles, casualties, loot, and dynamic World Map API

const http   = require('http');
const fs     = require('fs');
const path   = require('path');
const url    = require('url');
const mysql  = require('./node_modules/mysql2/promise');
const { WebSocketServer } = require('./node_modules/ws');

const PORT    = 8081;
const API_DIR = path.join(__dirname, 'ws.elkaisar.com8081');
const JSON_DIR = path.join(__dirname, 'app.elkaisar.com', 'js-0.0.24', 'json');

const MIME = {
    '.html': 'text/plain; charset=utf-8',
    '.json': 'text/plain; charset=utf-8',
    '.js'  : 'application/javascript; charset=utf-8',
    '.css' : 'text/css; charset=utf-8',
    '.png' : 'image/png',
    '.jpg' : 'image/jpeg',
};

// ── 1. Startup Assets Loading (In-Memory for instantaneous O(1) battle calculations) ──
console.log('[WS-Server] Loading World Map and Battle Assets...');
const tStart = Date.now();

// 1a. World Barrary (96,997 coordinate tiles)
const worldUnits = new Map();
try {
    const rawMap = fs.readFileSync(path.join(JSON_DIR, 'WorldBarrary.json'), 'utf8');
    const entries = rawMap.replace(/^"|"$/g, '').split(',');
    for (const e of entries) {
        const p = e.split('_');
        if (p.length >= 4) {
            worldUnits.set(p[0] + '_' + p[1], { ut: parseInt(p[2], 10), l: parseInt(p[3], 10) });
        }
    }
} catch (e) {
    console.error('[Asset Error] Failed to load WorldBarrary.json:', e.message);
}

// 1b. UnitArmy (48 NPC army types for camps, maneuvers, etc.)
let unitArmy = {};
try {
    unitArmy = JSON.parse(fs.readFileSync(path.join(JSON_DIR, 'UnitArmy.json'), 'utf8'));
} catch (e) {
    console.error('[Asset Error] Failed to load UnitArmy.json:', e.message);
}

// 1c. UnitData (unit titles and properties)
let unitData = {};
try {
    unitData = JSON.parse(fs.readFileSync(path.join(JSON_DIR, 'worldUnitData.json'), 'utf8'));
} catch (e) {
    console.error('[Asset Error] Failed to load worldUnitData.json:', e.message);
}

// 1d. Prize Drop Rules (6,030 loot rules)
let prizeData = { Win: [] };
try {
    prizeData = JSON.parse(fs.readFileSync(path.join(__dirname, 'app.elkaisar.com', 'api', 'AWorld', 'getWorldUnitPrize.html'), 'utf8'));
} catch (e) {
    console.error('[Asset Error] Failed to load getWorldUnitPrize.html:', e.message);
}

// 1e. Equipment Power Stats (330 equipment items with full combat attributes)
const equipPowerMap = new Map();
try {
    const rawEquip = JSON.parse(fs.readFileSync(path.join(__dirname, 'app.elkaisar.com', 'api', 'APlayerEquip', 'getEquipPower.html'), 'utf8'));
    for (const eq of rawEquip) {
        equipPowerMap.set(`${eq.equip}_${eq.part}_${eq.lvl}`, eq);
    }
} catch (e) {
    console.error('[Asset Error] Failed to load getEquipPower.html:', e.message);
}

console.log(`[WS-Server] ✅ Assets loaded in ${Date.now() - tStart} ms! (${worldUnits.size} map tiles, ${prizeData.Win.length} drop rules, ${equipPowerMap.size} equipment stats)`);

// ── Soldier Stats Definition ─────────────────────────────────────────────────
const SOL_STATS = [
    { att: 0,   hp: 0 },
    { att: 15,  hp: 20 },  // 1: مشاة خفيف (light infantry)
    { att: 45,  hp: 40 },  // 2: فرسان (cavalry)
    { att: 60,  hp: 120 }, // 3: مدرعين (heavy infantry)
    { att: 40,  hp: 18 },  // 4: رماة أقواس (archers)
    { att: 80,  hp: 30 },  // 5: مقاليع (catapults)
    { att: 160, hp: 50 }   // 6: منجنيق (ballistas)
];

// MySQL Connection Pool
let dbPool = null;
async function getDb() {
    if (!dbPool) {
        dbPool = mysql.createPool({
            host: '127.0.0.1',
            port: 3306,
            user: 'root',
            password: '',
            database: 'elkaisar_db',
            waitForConnections: true,
            connectionLimit: 10,
        });
    }
    return dbPool;
}

// Connected clients: idPlayer -> ws
const clients = new Map();

function setCors(res) {
    res.setHeader('Access-Control-Allow-Origin' , '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
}

function proxyToApi(req, res) {
    setCors(res);
    const proxyReq = http.request({
        hostname: '127.0.0.1',
        port: 8090,
        path: req.url,
        method: req.method,
        headers: { ...req.headers, host: '127.0.0.1:8090' }
    }, (proxyRes) => {
        setCors(res);
        res.writeHead(proxyRes.statusCode, proxyRes.headers);
        proxyRes.pipe(res);
    });
    proxyReq.on('error', (e) => {
        setCors(res);
        res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end(JSON.stringify({ state: 'error', msg: e.message }));
    });
    req.pipe(proxyReq);
}

// ── HTTP Request Handler ─────────────────────────────────────────────────────
async function handleHttp(req, res) {
    setCors(res);
    if (req.method === 'OPTIONS') { res.writeHead(200); res.end(); return; }

    const parsed  = url.parse(req.url, true);
    const uriPath = parsed.pathname.replace(/\/+$/, '');

    // 1. Dynamic World Map Cities from MySQL (Only real players' cities!)
    if (uriPath === '/api/AWorld/getWorldCity') {
        try {
            const db = await getDb();
            const [rows] = await db.query(
                "SELECT c.id_city AS ic, c.id_player AS ip, c.x, c.y, c.lvl AS l, c.flag AS f, p.id_guild AS ig " +
                "FROM city c JOIN player p ON c.id_player = p.id_player"
            );
            const cities = rows.map(r => ({
                ig: r.ig || null,
                ip: Number(r.ip),
                ic: Number(r.ic),
                x:  Number(r.x),
                y:  Number(r.y),
                l:  Number(r.l),
                f:  String(r.f || "0")
            }));
            res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
            res.end(JSON.stringify(cities));
            return;
        } catch (e) {
            console.error('[WS-Server DB Error]:', e.message);
        }
    }

    // 2. Proxy Hero Army, Equipment & Battle Endpoints directly to PHP API on port 8090
    if (uriPath.startsWith('/api/AHeroArmy/') || uriPath.startsWith('/api/AHeroEquip/') || uriPath.startsWith('/api/APlayerEquip/') || uriPath.startsWith('/api/ABattelRunning/') || uriPath.startsWith('/api/ACityBarrary/') || uriPath.startsWith('/api/AWorld/')) {
        return proxyToApi(req, res);
    }

    // 3. Exact file from ws.elkaisar.com8081
    let filePath = path.join(API_DIR, uriPath);
    if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
        return serveFile(filePath, res);
    }

    // 4. File with .html appended
    filePath = path.join(API_DIR, uriPath + '.html');
    if (fs.existsSync(filePath)) {
        return serveFile(filePath, res);
    }

    // 5. Default fallback for missing WS endpoints
    res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
    res.end(JSON.stringify({ state: 'ok', data: [] }));
}

function serveFile(filePath, res) {
    const ext  = path.extname(filePath);
    const mime = MIME[ext] || 'text/plain; charset=utf-8';
    res.writeHead(200, { 'Content-Type': mime });
    fs.createReadStream(filePath).pipe(res);
}

// ── WebSocket Handler ────────────────────────────────────────────────────────
function handleWs(ws, req) {
    const parsed   = url.parse(req.url, true);
    const idPlayer = parsed.query.idPlayer;
    const token    = parsed.query.token;
    const serverId = parsed.query.server;

    console.log(`[WS] Player connected: idPlayer=${idPlayer}`);

    if (idPlayer) clients.set(String(idPlayer), ws);

    ws.on('message', async (raw) => {
        try {
            const msg = JSON.parse(raw.toString());
            console.log('[WS] Handled action:', msg.url || msg.action || msg);

            // 1. Broadcast chat messages
            if (msg.url === 'Chat/sendMsg') {
                const out = JSON.stringify({
                    classPath: 'Chat.WorldMsg',
                    data: {
                        id_player: idPlayer,
                        name: msg.data ? msg.data.name : 'سيزر',
                        msg: msg.data ? msg.data.msg : '',
                        time: Math.floor(Date.now() / 1000)
                    }
                });
                for (const client of clients.values()) {
                    if (client.readyState === 1) client.send(out);
                }
            }

            // 2. Battle March Initiation (Battel/start)
            if (msg.url === 'Battel/start') {
                const db = await getDb();
                const data = msg.data || {};
                const xCoord = parseInt(data.xCoord, 10);
                const yCoord = parseInt(data.yCoord, 10);
                const idHero = parseInt(data.idHero, 10);
                const attackTask = parseInt(data.attackTask, 10) || 0;

                // 2a. Check Hero & Troops
                const [hArmy] = await db.query("SELECT * FROM hero_army WHERE id_hero = ?", [idHero]);
                const army = hArmy[0] || {};
                const totalTroops = (army.f_1_num || 0) + (army.f_2_num || 0) + (army.f_3_num || 0) +
                                    (army.b_1_num || 0) + (army.b_2_num || 0) + (army.b_3_num || 0);

                if (totalTroops <= 0) {
                    ws.send(JSON.stringify({
                        classPath: 'Battel.StartFailed',
                        state: 'hero_carry_no_army'
                    }));
                    return;
                }

                const [hRows] = await db.query("SELECT * FROM city_hero WHERE id_hero = ?", [idHero]);
                const hero = hRows[0];
                if (!hero) {
                    ws.send(JSON.stringify({
                        classPath: 'Battel.StartFailed',
                        state: 'hero_cant_used'
                    }));
                    return;
                }

                const [cRows] = await db.query("SELECT * FROM city WHERE id_city = ?", [hero.id_city]);
                const city = cRows[0] || { x: 444, y: 154, name: 'المدينة 1', id_city: 1, id_player: 1 };
                const [pRows] = await db.query("SELECT * FROM player WHERE id_player = ?", [city.id_player]);
                const player = pRows[0] || { name: 'البطل سيزر', id_player: 1 };

                // 2b. Identify Target Unit Type and Level (Check Player Cities first, then World Units)
                const [targetCityRows] = await db.query("SELECT * FROM city WHERE x = ? AND y = ?", [xCoord, yCoord]);
                let unitType = 0;
                let unitLvl = 1;
                let unitTitle = 'أرض منبسطة';

                if (targetCityRows.length > 0) {
                    unitType = 60; // Player City (PvP)
                    unitLvl = targetCityRows[0].lvl || 10;
                    unitTitle = `مدينة [${targetCityRows[0].name}]`;
                } else {
                    const targetKey = `${xCoord}_${yCoord}`;
                    const mapUnit = worldUnits.get(targetKey) || { ut: 0, l: 1 };
                    unitType = mapUnit.ut;
                    unitLvl = Math.max(1, mapUnit.l || 1);
                    unitTitle = (unitData[unitType] && unitData[unitType].Title) ? unitData[unitType].Title : (unitType === 0 ? 'أرض منبسطة' : 'برية');
                }

                // 2c. Calculate March Duration
                const dist = Math.hypot(xCoord - city.x, yCoord - city.y);
                const marchDuration = Math.max(10, Math.min(60, Math.ceil(dist * 0.4)));
                const now = Math.floor(Date.now() / 1000);
                const arrive = now + marchDuration;

                // 2d. Insert into world_battel
                const [ins] = await db.query(
                    "INSERT INTO world_battel (id_player, id_city, id_hero, task, x_city, y_city, x_coord, y_coord, time_start, time_arrive, state) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'marching')",
                    [player.id_player, city.id_city, idHero, attackTask, city.x, city.y, xCoord, yCoord, now, arrive]
                );
                const battelId = ins.insertId;

                // 2e. Send Battel.Started with countdown
                ws.send(JSON.stringify({
                    classPath: 'Battel.Started',
                    id_battel: battelId,
                    Battel: {
                        id_battel: battelId,
                        id_player: player.id_player,
                        PlayerName: player.name,
                        CityName: city.name,
                        x_coord: xCoord,
                        y_coord: yCoord,
                        x_city: city.x,
                        y_city: city.y,
                        task: attackTask,
                        time_start: now,
                        time_arrive: arrive,
                        time_end: arrive
                    },
                    StartingPrice: []
                }));

                // 2f. Battle Resolution Timer
                setTimeout(async () => {
                    try {
                        const battleDb = await getDb();

                        // Verify battle wasn't aborted
                        const [bCheck] = await battleDb.query("SELECT state FROM world_battel WHERE id_battel = ?", [battelId]);
                        if (!bCheck[0] || bCheck[0].state === 'aborted') return;

                        // ── COMBAT RESOLUTION ENGINE ──
                        // 1. Calculate Attacker Combat Power
                        let attPower = 0;
                        const slots = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
                        for (const s of slots) {
                            const t = parseInt(army[s + '_type'], 10) || 0;
                            const n = parseInt(army[s + '_num'], 10) || 0;
                            if (t > 0 && n > 0 && SOL_STATS[t]) {
                                attPower += (SOL_STATS[t].att * 0.6 + SOL_STATS[t].hp * 0.4) * n;
                            }
                        }
                        const heroBonus = 1.0 + ((hero.power || 50) + (hero.point_b || 0)) / 100.0;
                        attPower *= heroBonus;

                        // 1b. Hero Equipment Combat Bonus (Weapons, Armor, Horses, Rings)
                        let eqAtt = 0;
                        let eqVit = 0;
                        try {
                            const [hEquips] = await battleDb.query("SELECT * FROM player_equip WHERE id_hero = ? AND on_hero = 1", [idHero]);
                            for (const eq of (hEquips || [])) {
                                const ep = equipPowerMap.get(`${eq.type}_${eq.part}_${eq.lvl}`);
                                if (ep) {
                                    eqAtt += (ep.attack || 0);
                                    eqVit += (ep.vitality || 0);
                                }
                            }
                        } catch (e) { }
                        attPower += (eqAtt * 1.5 + eqVit * 0.8);

                        // 2. Fetch or Calculate Defender Army
                        let defenderTroops = {};
                        if (unitArmy[unitType] && unitArmy[unitType][unitLvl]) {
                            defenderTroops = unitArmy[unitType][unitLvl];
                        } else if (unitType >= 1 && unitType <= 59) {
                            // Wilds / Barray scaled army
                            defenderTroops = {
                                1: unitLvl * 25,
                                4: unitLvl * 15,
                                2: (unitLvl > 3 ? (unitLvl - 3) * 20 : 0),
                                3: (unitLvl > 5 ? (unitLvl - 5) * 15 : 0)
                            };
                        } else {
                            // Small defending force for level
                            defenderTroops = { 1: Math.max(10, unitLvl * 20), 4: Math.max(5, unitLvl * 10) };
                        }

                        // 3. Calculate Defender Combat Power
                        let defPower = 0;
                        for (const [type, num] of Object.entries(defenderTroops)) {
                            const t = parseInt(type, 10);
                            const n = parseInt(num, 10) || 0;
                            if (t > 0 && n > 0 && SOL_STATS[t]) {
                                defPower += (SOL_STATS[t].att * 0.6 + SOL_STATS[t].hp * 0.4) * n;
                            }
                        }
                        defPower *= (1.0 + unitLvl * 0.05);

                        // 4. Compare Power & Determine Victory vs Defeat
                        const ratio = attPower / Math.max(1, defPower);
                        const isVictory = ratio >= 1.0;

                        // 5. Calculate Loss Rate & Recovery Mechanics (Exact Elkaisar Rules)
                        // Target Categories:
                        // - Wilds (1-59): 75% Recovery (25% permanent loss)
                        // - Camps & Maneuvers (70-96): 65% Recovery (35% permanent loss)
                        // - Rebels & Carthage (97-105): 50% Recovery (50% permanent loss)
                        // - Real Cities / PvP (60-63): 0% Recovery (100% permanent loss)
                        // - Fortresses & Arenas (150+): 60% Recovery (40% permanent loss)
                        let recoveryRate = 0.0;
                        let targetCategoryName = 'معركة حقيقية (PvP)';

                        if (unitType >= 1 && unitType <= 59) {
                            recoveryRate = 0.75;
                            targetCategoryName = 'برية';
                        } else if (unitType >= 70 && unitType <= 96) {
                            recoveryRate = 0.65;
                            targetCategoryName = 'معسكر / مناورات';
                        } else if (unitType >= 97 && unitType <= 105) {
                            recoveryRate = 0.50;
                            targetCategoryName = 'عصابات تمرد';
                        } else if (unitType >= 60 && unitType <= 63) {
                            recoveryRate = 0.00;
                            targetCategoryName = 'معركة حقيقية ضد لاعب (PvP)';
                        } else {
                            recoveryRate = 0.60;
                            targetCategoryName = 'قلعة / ميدان تحدي';
                        }

                        let clashFallenRate = 0;
                        if (isVictory) {
                            clashFallenRate = Math.min(0.50, Math.max(0.02, 0.40 / Math.sqrt(ratio)));
                        } else {
                            clashFallenRate = Math.min(0.95, 0.65 + (1.0 - ratio) * 0.30);
                        }

                        let totalFallen = 0;
                        let totalRecovered = 0;
                        let totalPermanentLoss = 0;

                        for (const s of slots) {
                            const num = parseInt(army[s + '_num'], 10) || 0;
                            const type = parseInt(army[s + '_type'], 10) || 0;
                            if (num > 0) {
                                const rawFallen = isVictory 
                                    ? Math.min(num, Math.ceil(num * clashFallenRate)) 
                                    : Math.min(num, Math.max(1, Math.ceil(num * clashFallenRate)));
                                
                                const recovered = Math.floor(rawFallen * recoveryRate);
                                const permanentLoss = rawFallen - recovered;

                                totalFallen += rawFallen;
                                totalRecovered += recovered;
                                totalPermanentLoss += permanentLoss;

                                const remaining = Math.max(0, num - permanentLoss);
                                const newType = remaining > 0 ? type : 0;
                                await battleDb.query(`UPDATE hero_army SET ${s}_num = ?, ${s}_type = ? WHERE id_hero = ?`, [remaining, newType, idHero]);
                            }
                        }

                        await battleDb.query("UPDATE world_battel SET state = ? WHERE id_battel = ?", [isVictory ? 'won' : 'lost', battelId]);

                        if (isVictory) {
                            // ── VICTORY RESOLUTION ──
                            // 6a. Loot Resources
                            const loot = Math.min(100000, Math.max(2000, unitLvl * 3500));
                            await battleDb.query(
                                "UPDATE city SET food = LEAST(food_cap, food + ?), wood = LEAST(wood_cap, wood + ?), stone = LEAST(stone_cap, stone + ?), metal = LEAST(metal_cap, metal + ?), coin = LEAST(coin_cap, coin + ?) WHERE id_city = ?",
                                [loot, loot, loot, loot, loot * 2, city.id_city]
                            );

                            // 6b. Hero EXP & Player Prestige
                            const expGain = Math.max(50, Math.min(50000, Math.floor(defPower * 0.15)));
                            const prestigeGain = Math.max(10, Math.min(1000, Math.floor(unitLvl * 10)));
                            await battleDb.query("UPDATE city_hero SET exp = exp + ?, power = LEAST(power_max, power + 5) WHERE id_hero = ?", [expGain, idHero]);
                            await battleDb.query("UPDATE player SET prestige = prestige + ? WHERE id_player = ?", [prestigeGain, player.id_player]);

                            // 6c. Prize Drop Tables Evaluation
                            const droppedItems = [];
                            const winPrizes = prizeData.Win ? prizeData.Win.filter(x => x.unitType == unitType && x.lvl == unitLvl) : [];
                            for (const p of winPrizes) {
                                if (Math.random() * 1000 <= p.win_rate) {
                                    const amt = Math.floor(Math.random() * (p.amount_max - p.amount_min + 1)) + p.amount_min;
                                    if (amt > 0) {
                                        droppedItems.push({ prize: p.prize, amount: amt });
                                        await battleDb.query(
                                            "INSERT INTO player_item (id_player, id_item, amount) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)",
                                            [player.id_player, p.prize, amt]
                                        );
                                    }
                                }
                            }

                            // 6d. Colonize Wild if attackTask is conquer (0) and target is wild
                            if (attackTask === 0 && unitType >= 1 && unitType <= 59) {
                                await battleDb.query(
                                    "INSERT INTO city_barray (id_city, id_player, x_coord, y_coord, Type, lvl) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id_city = VALUES(id_city), id_player = VALUES(id_player), Type = VALUES(Type), lvl = VALUES(lvl)",
                                    [city.id_city, player.id_player, xCoord, yCoord, unitType, unitLvl]
                                );
                            }

                            // 6e. Send Client Completion Messages
                            if (ws.readyState === 1) {
                                ws.send(JSON.stringify({
                                    classPath: 'Battel.battel',
                                    task: 'finish',
                                    result: 'win',
                                    id_battel: battelId,
                                    Battel: { id_battel: battelId }
                                }));
                                ws.send(JSON.stringify({
                                    classPath: 'Battel.Finished',
                                    id_battel: battelId,
                                    Battel: { id_battel: battelId }
                                }));

                                const itemsSummary = droppedItems.length > 0 
                                    ? ` + غنائم أدوات (${droppedItems.map(d => d.prize + ' ×' + d.amount).join('، ')})` 
                                    : '';
                                const lossReport = recoveryRate > 0 
                                    ? `سقط في المعركة [${totalFallen}] جندي، تم علاج واستعادة [${totalRecovered}]، والخسائر الفعلية [${totalPermanentLoss}] جندي فقط (${Math.round((1 - recoveryRate) * 100)}%)`
                                    : `الخسائر: [${totalPermanentLoss} جندي] (خسارة كاملة 100% في معارك اللاعبين الحقيقيين)`;

                                ws.send(JSON.stringify({
                                    classPath: 'Chat.WorldMsg',
                                    data: {
                                        id_player: 0,
                                        name: 'النظام الحربي',
                                        msg: `🏆 انتصار مجيد! سحق البطل [${hero.name}] قوات [${unitTitle} مستوى ${unitLvl}] (${targetCategoryName}). ${lossReport}، الغنائم: [${loot * 5} موارد]${itemsSummary}!`,
                                        time: Math.floor(Date.now() / 1000)
                                    }
                                }));
                            }

                        } else {
                            // ── DEFEAT RESOLUTION ──
                            await battleDb.query("UPDATE city_hero SET power = GREATEST(0, power - 25) WHERE id_hero = ?", [idHero]);

                            if (ws.readyState === 1) {
                                ws.send(JSON.stringify({
                                    classPath: 'Battel.battel',
                                    task: 'finish',
                                    result: 'defeat',
                                    id_battel: battelId,
                                    Battel: { id_battel: battelId }
                                }));
                                ws.send(JSON.stringify({
                                    classPath: 'Battel.Finished',
                                    id_battel: battelId,
                                    Battel: { id_battel: battelId }
                                }));

                                const defeatLossReport = recoveryRate > 0 
                                    ? `سقط [${totalFallen}] جندي، تم علاج واستعادة [${totalRecovered}]، وفُقد [${totalPermanentLoss}] جندي`
                                    : `إبادة كاملة: فقدان [${totalPermanentLoss}] جندي 100% في حرب ضد لاعبين`;

                                ws.send(JSON.stringify({
                                    classPath: 'Chat.WorldMsg',
                                    data: {
                                        id_player: 0,
                                        name: 'النظام الحربي',
                                        msg: `❌ هزيمة ساحقة! انهزم البطل [${hero.name}] أمام حامية [${unitTitle} مستوى ${unitLvl}] (${targetCategoryName}). ${defeatLossReport} وعاد البطل جريحاً دون غنائم!`,
                                        time: Math.floor(Date.now() / 1000)
                                    }
                                }));
                            }
                        }

                    } catch (bErr) {
                        console.error('[Battle Resolution Error]:', bErr.message);
                    }
                }, marchDuration * 1000);
            }

            // 3. Battle Abort (Battel/abort)
            if (msg.url === 'Battel/abort') {
                const battelId = msg.data ? (msg.data.id_battel || msg.data.idBattel) : 0;
                if (battelId) {
                    const db = await getDb();
                    await db.query("UPDATE world_battel SET state = 'aborted' WHERE id_battel = ?", [battelId]);
                    ws.send(JSON.stringify({
                        classPath: 'Battel.battel',
                        task: 'aborted',
                        id_battel: battelId,
                        Battel: { id_battel: battelId }
                    }));
                }
            }

        } catch (e) {
            console.error('[WS Message Error]:', e.message);
        }
    });

    ws.on('close', () => {
        if (idPlayer) clients.delete(String(idPlayer));
        console.log(`[WS] Disconnected: idPlayer=${idPlayer}`);
    });

    ws.on('error', (err) => {
        console.error('[WS] Error for', idPlayer, ':', err.message);
    });
}

// ── Server Start ─────────────────────────────────────────────────────────────
const server = http.createServer(handleHttp);
const wss = new WebSocketServer({ server });

wss.on('connection', handleWs);

server.listen(PORT, '0.0.0.0', () => {
    console.log(`[WS-Server] ✅ Listening on port ${PORT}`);
    console.log(`[WS-Server] WebSocket: ws://localhost:${PORT}`);
    console.log(`[WS-Server] Full Combat Simulator, Army Casualties, Real Loot Tables Active!`);
});

process.on('uncaughtException', (err) => {
    console.error('[WS-Server] Uncaught:', err.message);
});

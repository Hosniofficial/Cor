// WebSocket + HTTP Server for ws.elkaisar.com:8081
// Handles real-time WebSocket connections, combat marches, battles, casualties, loot, and dynamic World Map API

const http   = require('http');
const fs     = require('fs');
const path   = require('path');
const mysql  = require('./node_modules/mysql2/promise');
const { WebSocketServer } = require('./node_modules/ws');

const PORT    = 8081;
const API_PORT = Number(process.env.ELKAISAR_API_PORT || 8092);
const API_DIR = path.join(__dirname, 'ws.elkaisar.com8081');
const JSON_DIR = path.join(__dirname, 'app.elkaisar.com', 'js-0.0.24', 'json');
if (!Number.isInteger(API_PORT) || API_PORT < 1 || API_PORT > 65535) {
    throw new Error('ELKAISAR_API_PORT must be between 1 and 65535');
}
const ENVIRONMENT = (process.env.ELKAISAR_ENV || 'development').toLowerCase();
if (!['development', 'test', 'production'].includes(ENVIRONMENT)) {
    throw new Error('ELKAISAR_ENV must be development, test, or production');
}
const IS_PRODUCTION = ENVIRONMENT === 'production';

function getDatabaseSetting(name, developmentDefault) {
    const value = process.env[name];
    if (value !== undefined && value.trim() !== '') {
        return value;
    }
    if (IS_PRODUCTION) {
        throw new Error(`Missing required database setting: ${name}`);
    }
    return developmentDefault;
}

const DB_CONFIG = {
    host: getDatabaseSetting('ELKAISAR_DB_HOST', '127.0.0.1'),
    port: Number(getDatabaseSetting('ELKAISAR_DB_PORT', '3306')),
    user: getDatabaseSetting('ELKAISAR_DB_USER', 'root'),
    password: getDatabaseSetting('ELKAISAR_DB_PASSWORD', ''),
    database: getDatabaseSetting('ELKAISAR_DB_NAME', 'elkaisar_prod_ready_20261109'),
};
if (!Number.isInteger(DB_CONFIG.port) || DB_CONFIG.port < 1 || DB_CONFIG.port > 65535) {
    throw new Error('ELKAISAR_DB_PORT must be between 1 and 65535');
}

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
    prizeData = JSON.parse(fs.readFileSync(path.join(__dirname, 'app.elkaisar.com', 'data', 'world-unit-prizes.json'), 'utf8'));
} catch (e) {
    console.error('[Asset Error] Failed to load world-unit-prizes.json:', e.message);
}

// 1e. Equipment Power Stats (330 equipment items with full combat attributes)
const equipPowerMap = new Map();
try {
    const rawEquip = JSON.parse(fs.readFileSync(path.join(__dirname, 'app.elkaisar.com', 'data', 'equipment-power.json'), 'utf8'));
    for (const eq of rawEquip) {
        equipPowerMap.set(`${eq.equip}_${eq.part}_${eq.lvl}`, eq);
    }
} catch (e) {
    console.error('[Asset Error] Failed to load equipment-power.json:', e.message);
}

console.log(`[WS-Server] ✅ Assets loaded in ${Date.now() - tStart} ms! (${worldUnits.size} map tiles, ${prizeData.Win.length} drop rules, ${equipPowerMap.size} equipment stats)`);

// ── Soldier Stats Definition (Authentic stats matching obfuscated.js) ─────────────
const SOL_STATS = [
    { att: 0,   hp: 0 },
    { att: 8,   hp: 60 },   // 1: مشاة (army_a)
    { att: 30,  hp: 250 },  // 2: فرسان (army_b)
    { att: 25,  hp: 400 },  // 3: مدرعين (army_c)
    { att: 9,   hp: 45 },   // 4: رماة (army_d)
    { att: 19,  hp: 100 },  // 5: مقاليع (army_e)
    { att: 40,  hp: 600 },  // 6: منجنيق (army_f)
    { att: 10,  hp: 80 }    // 7: جواسيس (spies)
];

// MySQL Connection Pool
let dbPool = null;
async function getDb() {
    if (!dbPool) {
        dbPool = mysql.createPool({
            ...DB_CONFIG,
            waitForConnections: true,
            connectionLimit: 10,
        });
    }
    return dbPool;
}

// Connected clients: idPlayer -> Set<ws>
const clients = new Map();
const REPLACED_SESSION_CODE = 4001;
const REPLACED_SESSION_REASON = 'ACCOUNT_LOGIN_ELSEWHERE';
const MAX_CITY_RESOURCE_BALANCE = 1000000000000;

async function creditCityResources(db, cityId, requested) {
    const resources = Object.entries(requested);
    for (const [resource, amount] of resources) {
        if (!['food', 'wood', 'stone', 'metal', 'coin'].includes(resource)
            || !Number.isSafeInteger(amount) || amount < 0
            || amount > MAX_CITY_RESOURCE_BALANCE) {
            throw new Error('Invalid city resource credit');
        }
    }
    const credits = resources.filter(([, amount]) => amount > 0);
    if (credits.length === 0) return {};

    const connection = await db.getConnection();
    try {
        await connection.beginTransaction();
        const columns = credits.map(([resource]) => `\`${resource}\``).join(', ');
        const [rows] = await connection.query(
            `SELECT ${columns} FROM city WHERE id_city = ? FOR UPDATE`,
            [cityId]
        );
        if (!rows.length) throw new Error('Resource recipient city not found');

        const credited = {};
        const setClauses = [];
        const params = [];
        for (const [resource, requestedAmount] of credits) {
            const current = Number(rows[0][resource]);
            const amount = Math.min(requestedAmount, Math.max(0, MAX_CITY_RESOURCE_BALANCE - current));
            credited[resource] = amount;
            if (amount > 0) {
                setClauses.push(`\`${resource}\` = \`${resource}\` + ?`);
                params.push(amount);
            }
        }

        if (setClauses.length) {
            await connection.query(
                `UPDATE city SET ${setClauses.join(', ')} WHERE id_city = ?`,
                [...params, cityId]
            );
        }
        await connection.commit();
        return credited;
    } catch (error) {
        await connection.rollback();
        throw error;
    } finally {
        connection.release();
    }
}

async function authenticateSocketPlayer(db, idPlayer, token) {
    if (!/^\d+$/.test(String(idPlayer || '')) || typeof token !== 'string' || token === '') {
        return false;
    }

    const [rows] = await db.query(
        'SELECT id_player FROM player WHERE id_player = ? AND p_token = ? LIMIT 1',
        [idPlayer, token]
    );
    return rows.length === 1;
}

function closeReplacedSession(ws) {
    if (ws.readyState === 1) {
        ws.close(REPLACED_SESSION_CODE, REPLACED_SESSION_REASON);
    }
}

async function closeSessionsWithStaleTokens() {
    if (clients.size === 0) return;

    const db = await getDb();
    const playerIds = Array.from(clients.keys());
    for (let offset = 0; offset < playerIds.length; offset += 500) {
        const batch = playerIds.slice(offset, offset + 500);
        const placeholders = batch.map(() => '?').join(', ');
        const [rows] = await db.query(
            `SELECT id_player, p_token FROM player WHERE id_player IN (${placeholders})`,
            batch
        );
        const currentTokens = new Map(rows.map(row => [String(row.id_player), String(row.p_token)]));

        for (const idPlayer of batch) {
            const playerClients = clients.get(idPlayer);
            if (!playerClients) continue;

            const currentToken = currentTokens.get(idPlayer);
            for (const client of playerClients) {
                if (!currentToken || client.playerToken !== currentToken) {
                    closeReplacedSession(client);
                }
            }
        }
    }
}

function broadcast(message, excludedPlayerId) {
    for (const [playerId, playerClients] of clients.entries()) {
        if (excludedPlayerId && playerId === String(excludedPlayerId)) continue;
        for (const client of playerClients) {
            if (client.readyState === 1) client.send(message);
        }
    }
}

function getPlayerClients(idPlayer) {
    const playerClients = clients.get(String(idPlayer));
    return playerClients ? Array.from(playerClients).filter(client => client.readyState === 1) : [];
}

function sendToPlayer(idPlayer, message) {
    for (const client of getPlayerClients(idPlayer)) {
        client.send(message);
    }
}

async function hasActiveBattleAt(db, xCoord, yCoord) {
    const [rows] = await db.query(
        "SELECT 1 FROM world_battel WHERE x_coord = ? AND y_coord = ? " +
        "AND state = 'marching' AND time_arrive > UNIX_TIMESTAMP() LIMIT 1",
        [xCoord, yCoord]
    );
    return rows.length > 0;
}

function reportArmySlots(army, remainingArmy, defenderTroops) {
    const slotNames = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
    const attackerSlots = {};
    for (const slot of slotNames) {
        const type = Number(army[`${slot}_type`]) || 0;
        const pre = Number(army[`${slot}_num`]) || 0;
        attackerSlots[slot] = {
            type,
            pre,
            cur: Number(remainingArmy[slot]) || 0
        };
    }

    const defenderSlots = Object.fromEntries(slotNames.map(slot => [slot, { type: 0, pre: 0, cur: 0 }]));
    Object.entries(defenderTroops).slice(0, slotNames.length).forEach(([type, count], index) => {
        const amount = Math.max(0, Number(count) || 0);
        defenderSlots[slotNames[index]] = { type: Number(type) || 0, pre: amount, cur: amount };
    });

    return { attackerSlots, defenderSlots };
}

async function saveWsBattleReport(db, details) {
    const {
        player, hero, battleId, xCoord, yCoord, unitType, unitLvl, attackTask,
        isVictory, expGained, honorGained, resourceLoot, itemLoot,
        attackerSlots, defenderSlots
    } = details;
    const asReportHero = (slots, side, name, avatar, idPlayer, xp) => {
        const reportHero = { side, h_name: name, avatar, id_player: idPlayer, xp };
        for (const slot of ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3']) {
            reportHero[`${slot}_type`] = String(slots[slot].type);
            reportHero[`${slot}_pre`] = String(slots[slot].pre);
            reportHero[`${slot}_post`] = String(slots[slot].cur);
        }
        return reportHero;
    };
    const reportDetail = {
        encId: '0',
        general_data: {
            p_name: player.name,
            task: attackTask,
            lvl: unitLvl,
            round_num: 1,
            side_win: isVictory ? 1 : 0
        },
        heros: [
            asReportHero(attackerSlots, 1, hero.name || 'البطل سيزر', Number(hero.avatar) || 1, Number(player.id_player), expGained),
            asReportHero(defenderSlots, 0, `حامية الأعداء [مستوى ${unitLvl}]`, 2, 0, 0)
        ],
        prize: {
            resource: resourceLoot,
            matrial: itemLoot,
            honor: honorGained
        },
        rounds: ['تم حسم نتيجة المعركة بناءً على قوة الجيشين.'],
        rounds_log: ['تم حسم نتيجة المعركة بناءً على قوة الجيشين.']
    };
    const detailJson = JSON.stringify(reportDetail);
    const [insertResult] = await db.query(
        "INSERT INTO report_player (id_player, x, y, t, lvl, type, seen, detail, time_stamp) " +
        "VALUES (?, ?, ?, ?, ?, 1, 0, ?, NOW())",
        [player.id_player, xCoord, yCoord, unitType, unitLvl, detailJson]
    );
    reportDetail.encId = String(insertResult.insertId);
    const finalizedDetail = JSON.stringify(reportDetail);
    await db.query("UPDATE report_player SET detail = ? WHERE id_report = ?", [finalizedDetail, insertResult.insertId]);

    const total = slots => Object.values(slots).reduce((sum, slot) => sum + slot.pre, 0);
    const remaining = slots => Object.values(slots).reduce((sum, slot) => sum + slot.cur, 0);
    await db.query(
        "INSERT INTO battel_log (id_world_battel, attacker_id, defender_id, winner, atk_losses, def_losses, " +
        "loot_food, loot_wood, loot_stone, loot_metal, loot_coin, honor_gained, detail) " +
        "VALUES (?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            battleId,
            player.id_player,
            isVictory ? 'attacker' : 'defender',
            Math.max(0, total(attackerSlots) - remaining(attackerSlots)),
            Math.max(0, total(defenderSlots) - remaining(defenderSlots)),
            resourceLoot.food,
            resourceLoot.wood,
            resourceLoot.stone,
            resourceLoot.metal,
            resourceLoot.coin,
            honorGained,
            finalizedDetail
        ]
    );
}

let processingHeroReturns = false;
async function processDueHeroReturns() {
    if (processingHeroReturns) return;
    processingHeroReturns = true;
    let connection;
    try {
        const db = await getDb();
        connection = await db.getConnection();
        await connection.beginTransaction();
        const [dueBattles] = await connection.query(
            "SELECT id_battel, id_player, id_hero, task, x_city, y_city " +
            "FROM world_battel WHERE state IN ('won', 'lost') AND time_back <= UNIX_TIMESTAMP() " +
            "ORDER BY time_back ASC LIMIT 25 FOR UPDATE"
        );
        const returned = [];
        for (const battle of dueBattles) {
            const [update] = await connection.query(
                "UPDATE world_battel SET state = 'returned' WHERE id_battel = ? AND state IN ('won', 'lost')",
                [battle.id_battel]
            );
            if (!update.affectedRows) continue;

            const [activeBattles] = await connection.query(
                "SELECT 1 FROM world_battel WHERE id_hero = ? AND id_battel <> ? AND " +
                "((state = 'marching' AND time_arrive > UNIX_TIMESTAMP()) OR " +
                "(state IN ('won', 'lost') AND time_back > UNIX_TIMESTAMP())) LIMIT 1",
                [battle.id_hero, battle.id_battel]
            );
            if (activeBattles.length) continue;

            const [heroUpdate] = await connection.query(
                "UPDATE city_hero SET in_city = 1 WHERE id_hero = ? AND id_player = ? AND in_city = 0",
                [battle.id_hero, battle.id_player]
            );
            if (heroUpdate.affectedRows) returned.push(battle);
        }
        await connection.commit();
        for (const battle of returned) {
            sendToPlayer(battle.id_player, JSON.stringify({
                classPath: 'Hero.Back',
                idHero: Number(battle.id_hero),
                inCity: 1,
                Task: Number(battle.task),
                task: Number(battle.task),
                xTo: Number(battle.x_city),
                yTo: Number(battle.y_city)
            }));
        }
    } catch (error) {
        if (connection) {
            try {
                await connection.rollback();
            } catch (rollbackError) {
                console.error('[Hero Return Rollback Error]:', rollbackError.message);
            }
        }
        console.error('[Hero Return Processor Error]:', error.message);
    } finally {
        if (connection) connection.release();
        processingHeroReturns = false;
    }
}

function setCors(res) {
    res.setHeader('Access-Control-Allow-Origin' , '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
}

function proxyToApi(req, res) {
    setCors(res);
    const proxyReq = http.request({
        hostname: '127.0.0.1',
        port: API_PORT,
        path: req.url,
        method: req.method,
        headers: { ...req.headers, host: `127.0.0.1:${API_PORT}` }
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

    const requestUrl = new URL(req.url, 'http://localhost');
    const uriPath = requestUrl.pathname.replace(/\/+$/, '');

    // 1. Dynamic World Map Cities from MySQL (Only real players' cities!)
    if (uriPath === '/api/AWorld/getWorldCity') {
        try {
            const db = await getDb();
            const [rows] = await db.query(
                "SELECT c.id_city AS ic, c.id_player AS ip, c.x, c.y, c.lvl AS l, " +
                "CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag ELSE '0' END AS f, " +
                "CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag_expires_at ELSE 0 END AS fe, " +
                "p.id_guild AS ig " +
                "FROM city c JOIN player p ON c.id_player = p.id_player"
            );
            const cities = rows.map(r => ({
                ig: r.ig || null,
                ip: Number(r.ip),
                ic: Number(r.ic),
                x:  Number(r.x),
                y:  Number(r.y),
                l:  Number(r.l),
                f:  String(r.f || "0"),
                fe: Number(r.fe) || 0
            }));
            res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
            res.end(JSON.stringify(cities));
            return;
        } catch (e) {
            console.error('[WS-Server DB Error]:', e.message);
            res.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
            res.end(JSON.stringify({ state: 'error', msg: 'World city data is unavailable' }));
            return;
        }
    }

    // 2. Exact file from ws.elkaisar.com8081
    let filePath = path.join(API_DIR, uriPath);
    if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
        return serveFile(filePath, res);
    }

    // 3. File with .html appended
    filePath = path.join(API_DIR, uriPath + '.html');
    if (fs.existsSync(filePath)) {
        return serveFile(filePath, res);
    }

    // 4. Proxy all dynamic API and auth calls directly to the PHP API server
    if (uriPath.startsWith('/api/') || uriPath.startsWith('/home/')) {
        return proxyToApi(req, res);
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
    const requestUrl = new URL(req.url, 'ws://localhost');
    const idPlayer = requestUrl.searchParams.get('idPlayer');
    const token    = requestUrl.searchParams.get('token');
    const serverId = requestUrl.searchParams.get('server');

    const authentication = (async () => {
        const db = await getDb();
        if (!await authenticateSocketPlayer(db, idPlayer, token)) {
            if (ws.readyState === 1) ws.close(1008, 'Unauthorized');
            return false;
        }
        if (ws.readyState === 1) {
            ws.playerToken = token;
            const playerClients = clients.get(String(idPlayer)) || new Set();
            playerClients.add(ws);
            clients.set(String(idPlayer), playerClients);
            console.log(`[WS] Player connected: idPlayer=${idPlayer}`);
        }
        return ws.readyState === 1;
    })().catch(error => {
        console.error('[WS Authentication Error]:', error.message);
        if (ws.readyState === 1) ws.close(1011, 'Authentication unavailable');
        return false;
    });

    ws.on('message', async (raw) => {
        try {
            if (!await authentication) return;
            if (!await authenticateSocketPlayer(await getDb(), idPlayer, token)) {
                closeReplacedSession(ws);
                return;
            }
            const msg = JSON.parse(raw.toString());
            const action = typeof msg.url === 'string'
                ? msg.url
                : typeof msg.action === 'string' ? msg.action : 'message';
            console.log('[WS] Handled action:', action.slice(0, 100));

            if (msg.url === 'World/getWorldCityColonized') {
                const db = await getDb();
                if (!await authenticateSocketPlayer(db, idPlayer, token)) return;

                const [rows] = await db.query(
                    'SELECT b.x_coord AS x, b.y_coord AS y, p.city_flag, p.id_guild, p.id_player ' +
                    'FROM city_barray b ' +
                    'JOIN city c ON c.id_city = b.id_city ' +
                    'JOIN player p ON p.id_player = b.id_player'
                );
                ws.send(JSON.stringify({
                    classPath: 'Base.worldCityColonized',
                    data: {
                        City: rows.map(city => ({
                            x: Number(city.x),
                            y: Number(city.y),
                            city_flag: String(city.city_flag || '0'),
                            id_guild: city.id_guild ? Number(city.id_guild) : null,
                            id_player: Number(city.id_player)
                        }))
                    }
                }));
            }

            if (msg.url === 'World/refreshWorldCityColonization') {
                const data = msg.data || {};
                const xCoord = Number(data.xCoord);
                const yCoord = Number(data.yCoord);
                if (
                    !Number.isInteger(xCoord) || xCoord < 0 || xCoord >= 500 ||
                    !Number.isInteger(yCoord) || yCoord < 0 || yCoord >= 500
                ) return;

                const db = await getDb();
                if (!await authenticateSocketPlayer(db, idPlayer, token)) return;
                const [rows] = await db.query(
                    'SELECT b.x_coord AS x, b.y_coord AS y, p.city_flag, p.id_guild, p.id_player ' +
                    'FROM city_barray b ' +
                    'JOIN city c ON c.id_city = b.id_city ' +
                    'JOIN player p ON p.id_player = b.id_player ' +
                    'WHERE b.x_coord = ? AND b.y_coord = ? LIMIT 1',
                    [xCoord, yCoord]
                );
                const colonized = rows.length > 0;
                const update = JSON.stringify({
                    classPath: 'Base.refreshWorldCityColonization',
                    data: colonized ? {
                        xCoord,
                        yCoord,
                        colonized: true,
                        city_flag: String(rows[0].city_flag || '0'),
                        id_guild: rows[0].id_guild ? Number(rows[0].id_guild) : null,
                        id_player: Number(rows[0].id_player)
                    } : {
                        xCoord,
                        yCoord,
                        colonized: false
                    }
                });
                broadcast(update);
            }

            if (msg.url === 'World/getWorldFireUnit') {
                const db = await getDb();
                if (!await authenticateSocketPlayer(db, idPlayer, token)) return;

                const [rows] = await db.query(
                    "SELECT x_coord, y_coord FROM world_battel " +
                    "WHERE state = 'marching' AND time_arrive > UNIX_TIMESTAMP()"
                );
                const units = {};
                for (const battle of rows) {
                    const x = Number(battle.x_coord);
                    const y = Number(battle.y_coord);
                    if (Number.isInteger(x) && x >= 0 && x < 500 && Number.isInteger(y) && y >= 0 && y < 500) {
                        units[x * 500 + y] = 1;
                    }
                }
                ws.send(JSON.stringify({
                    classPath: 'Base.WorldFireUnit',
                    data: { Units: units }
                }));
            }

            if (msg.url === 'Battel/getAllWorldBattels') {
                const db = await getDb();
                if (!await authenticateSocketPlayer(db, idPlayer, token)) return;

                const [rows] = await db.query(
                    "SELECT b.id_battel, b.id_player, p.id_guild, p.name AS PlayerName, c.name AS CityName, " +
                    "b.x_city, b.y_city, b.x_coord, b.y_coord, b.task, b.time_start, b.time_arrive " +
                    "FROM world_battel b " +
                    "JOIN player p ON p.id_player = b.id_player " +
                    "JOIN city c ON c.id_city = b.id_city " +
                    "WHERE b.state = 'marching' AND b.time_arrive > UNIX_TIMESTAMP() " +
                    "ORDER BY b.id_battel DESC"
                );
                ws.send(JSON.stringify({
                    classPath: 'Base.Ack',
                    ReqId: Number(msg.data && msg.data.ReqId),
                    Res: rows.map(battle => ({
                        id_battel: Number(battle.id_battel),
                        idPlayer: Number(battle.id_player),
                        idGuild: battle.id_guild ? Number(battle.id_guild) : null,
                        PlayerName: battle.PlayerName,
                        CityName: battle.CityName,
                        xCity: Number(battle.x_city),
                        yCity: Number(battle.y_city),
                        xCoord: Number(battle.x_coord),
                        yCoord: Number(battle.y_coord),
                        task: Number(battle.task),
                        timeStart: Number(battle.time_start),
                        timeEnd: Number(battle.time_arrive),
                        attackNum: 1,
                        defenceNum: 0
                    }))
                }));
            }

            if (msg.url === 'World/refreshCityFlag') {
                const cityId = Number(msg.data && msg.data.idCity);
                if (!idPlayer || !Number.isSafeInteger(cityId) || cityId <= 0) return;

                const db = await getDb();
                const [rows] = await db.query(
                    "SELECT c.x, c.y, CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag ELSE '0' END AS f, " +
                    "CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag_expires_at ELSE 0 END AS fe " +
                    "FROM city c WHERE c.id_city = ? AND c.id_player = ? LIMIT 1",
                    [cityId, idPlayer]
                );
                if (!rows.length) return;

                const city = rows[0];
                const update = JSON.stringify({
                    classPath: 'Base.refreshCityFlag',
                    data: {
                        x: Number(city.x),
                        y: Number(city.y),
                        f: String(city.f || '0'),
                        fe: Number(city.fe) || 0
                    }
                });
                broadcast(update);
            }

            if (msg.url === 'World/refreshWorldCitiesForPlayers') {
                const cityId = Number(msg.data && msg.data.idCity);
                if (!idPlayer || !token || !Number.isSafeInteger(cityId) || cityId <= 0) return;

                const db = await getDb();
                const [rows] = await db.query(
                    "SELECT c.x AS xCoord, c.y AS yCoord, c.id_city AS idCity, c.id_player AS idPlayer, " +
                    "c.lvl AS CityLvl, COALESCE(p.id_guild, 0) AS idGuild, " +
                    "CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag ELSE '0' END AS CityFlag, " +
                    "CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag_expires_at ELSE 0 END AS CityFlagExpiresAt " +
                    "FROM city c JOIN player p ON p.id_player = c.id_player " +
                    "WHERE c.id_city = ? AND c.id_player = ? AND p.p_token = ? LIMIT 1",
                    [cityId, idPlayer, token]
                );
                if (!rows.length) return;

                const city = rows[0];
                const update = JSON.stringify({
                    classPath: 'Base.refreshWorldCitiesForPlayers',
                    data: {
                        xCoord: Number(city.xCoord),
                        yCoord: Number(city.yCoord),
                        idCity: Number(city.idCity),
                        idPlayer: Number(city.idPlayer),
                        idGuild: city.idGuild ? Number(city.idGuild) : null,
                        CityLvl: Number(city.CityLvl),
                        CityFlag: String(city.CityFlag || '0'),
                        CityFlagExpiresAt: Number(city.CityFlagExpiresAt) || 0
                    }
                });
                broadcast(update);
            }

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
                broadcast(out);
            }

            // 2. Battle March Initiation (Battel/start)
            if (msg.url === 'Battel/start') {
                const db = await getDb();
                if (!await authenticateSocketPlayer(db, idPlayer, token)) {
                    ws.send(JSON.stringify({
                        classPath: 'Battel.StartFailed',
                        state: 'hero_cant_used'
                    }));
                    return;
                }
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
                if (!hero || Number(hero.id_player) !== Number(idPlayer)) {
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
                if (Number(city.id_player) !== Number(idPlayer)) {
                    ws.send(JSON.stringify({
                        classPath: 'Battel.StartFailed',
                        state: 'hero_cant_used'
                    }));
                    return;
                }

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
                const launchConnection = await db.getConnection();
                let ins;
                try {
                    await launchConnection.beginTransaction();
                    const [heroUpdate] = await launchConnection.query(
                        "UPDATE city_hero SET in_city = 0 WHERE id_hero = ? AND id_player = ? AND in_city = 1",
                        [idHero, idPlayer]
                    );
                    if (!heroUpdate.affectedRows) {
                        await launchConnection.rollback();
                        ws.send(JSON.stringify({
                            classPath: 'Battel.StartFailed',
                            state: 'hero_cant_used'
                        }));
                        return;
                    }
                    [ins] = await launchConnection.query(
                        "INSERT INTO world_battel (id_player, id_city, id_hero, task, x_city, y_city, x_coord, y_coord, time_start, time_arrive, time_back, state) " +
                        "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'marching')",
                        [player.id_player, city.id_city, idHero, attackTask, city.x, city.y, xCoord, yCoord, now, arrive]
                    );
                    await launchConnection.commit();
                } catch (error) {
                    await launchConnection.rollback();
                    throw error;
                } finally {
                    launchConnection.release();
                }
                const battelId = ins.insertId;

                // 2e. Send Battel.Started with countdown
                sendToPlayer(idPlayer, JSON.stringify({
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
                        time_end: arrive,
                        attackNum: 1,
                        defenceNum: 0
                    },
                    xCoord,
                    yCoord,
                    xCity: Number(city.x),
                    yCity: Number(city.y),
                    idPlayer: Number(player.id_player),
                    idGuild: player.id_guild ? Number(player.id_guild) : null,
                    PlayerName: player.name,
                    CityName: city.name,
                    task: attackTask,
                    timeStart: now,
                    timeEnd: arrive,
                    StartingPrice: []
                }));

                broadcast(JSON.stringify({
                    classPath: 'World.Battel.Started',
                    id_battel: battelId,
                    idPlayer: Number(player.id_player),
                    idGuild: player.id_guild ? Number(player.id_guild) : null,
                    PlayerName: player.name,
                    CityName: city.name,
                    xCoord,
                    yCoord,
                    xCity: Number(city.x),
                    yCity: Number(city.y),
                    task: attackTask,
                    timeStart: now,
                    timeEnd: arrive
                }), idPlayer);

                if (targetCityRows.length > 0) {
                    sendToPlayer(targetCityRows[0].id_player, JSON.stringify({
                        classPath: 'Battel.battel',
                        task: 'YOUR_CITY_FIRE',
                        city_name: targetCityRows[0].name,
                        x_to: xCoord,
                        y_to: yCoord,
                        x_from: Number(city.x),
                        y_from: Number(city.y)
                    }));
                }

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
                        const totalBravery = (parseInt(hero.point_b, 10) || 0) + (parseInt(hero.point_b_plus, 10) || 0);
                        const heroBonus = 1.0 + ((hero.power || 50) + totalBravery) / 100.0;
                        attPower *= heroBonus;

                        // 1b. Hero Equipment Combat Bonus (Primary & Secondary Gear)
                        let eqAtt = 0;
                        let eqVit = 0;
                        let eqDmg = 0;
                        let eqBrk = 0;
                        let eqStr = 0;
                        try {
                            const [hEquips] = await battleDb.query("SELECT * FROM player_equip WHERE id_hero = ? AND on_hero = 1", [idHero]);
                            for (const eq of (hEquips || [])) {
                                const ep = equipPowerMap.get(`${eq.type}_${eq.part}_${eq.lvl}`);
                                if (ep) {
                                    eqAtt += (ep.attack || 0);
                                    eqVit += (ep.vitality || 0);
                                    eqDmg += (ep.damage || 0);
                                    eqBrk += (ep.break || 0);
                                    eqStr += (ep.strike || 0);
                                }
                            }
                        } catch (e) { }
                        attPower += (eqAtt * 1.5 + eqVit * 0.8 + eqDmg * 2.0 + eqBrk * 1.5 + eqStr * 1.0);

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

                        // 3. Calculate Defender Combat Power (with Enemy Commander Equipment)
                        let defPower = 0;
                        for (const [type, num] of Object.entries(defenderTroops)) {
                            const t = parseInt(type, 10);
                            const n = parseInt(num, 10) || 0;
                            if (t > 0 && n > 0 && SOL_STATS[t]) {
                                defPower += (SOL_STATS[t].att * 0.6 + SOL_STATS[t].hp * 0.4) * n;
                            }
                        }
                        defPower *= (1.0 + unitLvl * 0.05);

                        // Enemy commander equipment & power boost
                        let enemyEquipBonus = 0;
                        if (unitType >= 97 && unitType <= 105) {
                            enemyEquipBonus = unitLvl * 300; // Carthaginian Elite gear
                        } else if (unitType >= 70 && unitType <= 96) {
                            enemyEquipBonus = unitLvl * 150; // Legion / Roman military gear
                        } else if (unitType >= 150 && unitType <= 236) {
                            enemyEquipBonus = unitLvl * 600; // God & Emperor War gear
                        }
                        defPower += enemyEquipBonus;

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

                        // Hospital & Medicine Calculation (Base 10% up to 60%)
                        let medicineLvl = 0;
                        try {
                            const [pEdu] = await battleDb.query("SELECT medicine FROM player_edu WHERE id_player = ?", [player.id_player]);
                            if (pEdu && pEdu[0]) medicineLvl = parseInt(pEdu[0].medicine, 10) || 0;
                        } catch (e) { }
                        const woundedRate = Math.min(0.60, 0.10 + (medicineLvl * 0.05));
                        const woundedTroops = { army_a: 0, army_b: 0, army_c: 0, army_d: 0, army_e: 0, army_f: 0 };
                        const solTypeToCol = { 1: 'army_a', 2: 'army_b', 3: 'army_c', 4: 'army_d', 5: 'army_e', 6: 'army_f' };
                        const remainingArmy = {};

                        let totalFallen = 0;
                        let totalRecovered = 0;
                        let totalPermanentLoss = 0;
                        let totalWounded = 0;

                        for (const s of slots) {
                            const num = parseInt(army[s + '_num'], 10) || 0;
                            const type = parseInt(army[s + '_type'], 10) || 0;
                            remainingArmy[s] = num;
                            if (num > 0) {
                                const rawFallen = isVictory 
                                    ? Math.min(num, Math.ceil(num * clashFallenRate)) 
                                    : Math.min(num, Math.max(1, Math.ceil(num * clashFallenRate)));
                                
                                const recovered = Math.floor(rawFallen * recoveryRate);
                                const permanentLoss = rawFallen - recovered;

                                totalFallen += rawFallen;
                                totalRecovered += recovered;
                                totalPermanentLoss += permanentLoss;

                                // Wounded to City Hospital
                                const woundedCount = Math.floor(permanentLoss * woundedRate);
                                if (woundedCount > 0 && solTypeToCol[type]) {
                                    woundedTroops[solTypeToCol[type]] += woundedCount;
                                    totalWounded += woundedCount;
                                }

                                const remaining = Math.max(0, num - permanentLoss);
                                remainingArmy[s] = remaining;
                                const newType = remaining > 0 ? type : 0;
                                await battleDb.query(`UPDATE hero_army SET ${s}_num = ?, ${s}_type = ? WHERE id_hero = ?`, [remaining, newType, idHero]);
                            }
                        }

                        // Store wounded in City Hospital
                        if (totalWounded > 0) {
                            await battleDb.query(
                                `INSERT INTO city_wounded (id_city, army_a, army_b, army_c, army_d, army_e, army_f)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE
                                 army_a = army_a + VALUES(army_a), army_b = army_b + VALUES(army_b), army_c = army_c + VALUES(army_c),
                                 army_d = army_d + VALUES(army_d), army_e = army_e + VALUES(army_e), army_f = army_f + VALUES(army_f)`,
                                [city.id_city, woundedTroops.army_a, woundedTroops.army_b, woundedTroops.army_c, woundedTroops.army_d, woundedTroops.army_e, woundedTroops.army_f]
                            );
                        }

                        const { attackerSlots, defenderSlots } = reportArmySlots(army, remainingArmy, defenderTroops);
                        let reportExpGained = 0;
                        let reportHonorGained = 0;
                        let reportResourceLoot = { food: 0, wood: 0, stone: 0, metal: 0, coin: 0 };
                        let reportItemLoot = [];

                        if (isVictory) {
                            // ── VICTORY RESOLUTION ──
                            // 6a. Loot Resources
                            const loot = Math.min(100000, Math.max(2000, unitLvl * 3500));
                            const creditedLoot = await creditCityResources(battleDb, city.id_city, {
                                food: loot,
                                wood: loot,
                                stone: loot,
                                metal: loot,
                                coin: loot * 2
                            });
                            reportResourceLoot = Object.fromEntries(
                                Object.entries(reportResourceLoot).map(([resource]) => [resource, Number(creditedLoot[resource]) || 0])
                            );

                            // 6b. Hero EXP & Player Prestige by Target Category & Hero Wisdom
                            const heroRows = await battleDb.query("SELECT * FROM city_hero WHERE id_hero = ?", [idHero]);
                            const heroData = (heroRows && heroRows[0]) ? heroRows[0] : {};
                            const wisdom = parseInt(heroData.point_b, 10) || 0;
                            const wisdomMultiplier = 1.0 + (wisdom * 0.001);

                            let baseExp = 0;
                            let prestigeGain = 0;
                            let honorGained = 0;

                            if (unitType >= 97 && unitType <= 105) {
                                // Carthage Gangs & Rebels (عصابات قرطاجية وقطاع طرق)
                                baseExp = unitLvl * 4500 + Math.floor(Math.random() * 1500 + 500) + Math.floor(defPower * 0.25);
                                honorGained = unitLvl * 150 + Math.floor(defPower * 0.08) + Math.floor(Math.random() * 100 + 50);
                                prestigeGain = Math.max(50, unitLvl * 25);
                            } else if (unitType >= 70 && unitType <= 96) {
                                // Maneuvers & Camps (مناورات ومعسكرات)
                                baseExp = unitLvl * 2800 + Math.floor(Math.random() * 700 + 300) + Math.floor(defPower * 0.20);
                                honorGained = unitLvl * 90 + Math.floor(defPower * 0.05) + Math.floor(Math.random() * 60 + 20);
                                prestigeGain = Math.max(30, unitLvl * 18);
                            } else if (unitType >= 1 && unitType <= 59) {
                                // Wilds (البراري)
                                baseExp = unitLvl * 750 + Math.floor(Math.random() * 300 + 100) + Math.floor(defPower * 0.12);
                                honorGained = unitLvl * 40 + Math.floor(defPower * 0.03) + Math.floor(Math.random() * 40 + 10);
                                prestigeGain = Math.max(10, unitLvl * 10);
                                // Bonus Domination Honor when colonizing / occupying wild (استيلاء)
                                if (attackTask === 1) { // BATTEL_TASK_DOMINATE
                                    honorGained += (unitLvl * 50) + 50;
                                    prestigeGain += (unitLvl * 15);
                                }
                            } else if (unitType >= 150 && unitType <= 236) {
                                // Capitals, Arenas & Fortresses (قلاع وعواصم وميادين الحرب)
                                baseExp = unitLvl * 6000 + Math.floor(Math.random() * 2000 + 1000) + Math.floor(defPower * 0.30);
                                honorGained = unitLvl * 220 + Math.floor(defPower * 0.10) + Math.floor(Math.random() * 150 + 100);
                                prestigeGain = Math.max(100, unitLvl * 35);
                            } else {
                                // PvP / City
                                baseExp = Math.max(500, Math.floor(defPower * 0.25));
                                honorGained = Math.max(100, Math.floor(defPower * 0.15));
                                prestigeGain = Math.max(20, unitLvl * 15);
                            }

                            const expGain = Math.floor(baseExp * wisdomMultiplier);
                            reportExpGained = expGain;
                            reportHonorGained = honorGained;
                            await battleDb.query("UPDATE city_hero SET exp = exp + ?, power = LEAST(power_max, power + 5) WHERE id_hero = ?", [expGain, idHero]);
                            await battleDb.query("UPDATE player SET prestige = prestige + ?, honor = honor + ? WHERE id_player = ?", [prestigeGain, honorGained, player.id_player]);

                            if (player.id_guild > 0) {
                                await battleDb.query("UPDATE guild SET prestige = prestige + ?, honor = honor + ? WHERE id_guild = ?", [prestigeGain, honorGained, player.id_guild]);
                            }

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

                            // 6d. Direct Hero Equipment Drop Chance (From Elite Camps, Gangs, Arenas)
                            const droppedEquip = [];
                            let equipDropChance = 0;
                            let possibleEquipTypes = [];
                            if (unitType >= 97 && unitType <= 105 && unitLvl >= 3) {
                                equipDropChance = 0.25; // 25% on Carthage & Gangs lvl 3+
                                possibleEquipTypes = ['mili', 'peace', 'victory', 'mars'];
                            } else if (unitType >= 70 && unitType <= 96 && unitLvl >= 3) {
                                equipDropChance = 0.18; // 18% on Camps & Maneuvers lvl 3+
                                possibleEquipTypes = ['loy', 'sparta', 'lion'];
                            } else if (unitType >= 150 && unitType <= 236) {
                                equipDropChance = 0.40; // 40% on Arenas & Fortresses
                                possibleEquipTypes = ['fearless', 'thunder', 'holy', 'neptune', 'thor'];
                            }

                            if (possibleEquipTypes.length > 0 && Math.random() < equipDropChance) {
                                const parts = ['sword', 'shield', 'helmet', 'armor', 'boot', 'belt'];
                                const eqType = possibleEquipTypes[Math.floor(Math.random() * possibleEquipTypes.length)];
                                const eqPart = parts[Math.floor(Math.random() * parts.length)];
                                const eqLvl = Math.max(1, Math.min(10, unitLvl));
                                await battleDb.query(
                                    "INSERT INTO player_equip (id_player, id_hero, type, part, lvl, on_hero) VALUES (?, 0, ?, ?, ?, 0)",
                                    [player.id_player, eqType, eqPart, eqLvl]
                                );
                                droppedEquip.push({ type: eqType, part: eqPart, lvl: eqLvl });
                            }
                            reportItemLoot = [
                                ...droppedItems,
                                ...droppedEquip.map(equip => ({
                                    prize: `equip_${equip.type}_${equip.part}`,
                                    amount: 1
                                }))
                            ];

                            // 6e. Colonize / Dominate Wild (استيلاء على البرية)
                            let dominated = false;
                            if (attackTask === 1 && unitType >= 1 && unitType <= 59) {
                                const [pBuilding] = await battleDb.query("SELECT lvl FROM city_building WHERE id_city = ? AND type = 10 LIMIT 1", [city.id_city]);
                                const palaceLvl = (pBuilding && pBuilding[0]) ? parseInt(pBuilding[0].lvl, 10) : 1;
                                const maxWilds = Math.max(1, palaceLvl);

                                const [curBarray] = await battleDb.query("SELECT COUNT(*) as cnt FROM city_barray WHERE id_city = ?", [city.id_city]);
                                const curWildCount = (curBarray && curBarray[0]) ? parseInt(curBarray[0].cnt, 10) : 0;

                                if (curWildCount < maxWilds) {
                                    await battleDb.query(
                                        "INSERT INTO city_barray (id_city, id_player, x_coord, y_coord, Type, lvl) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id_city = VALUES(id_city), id_player = VALUES(id_player), Type = VALUES(Type), lvl = VALUES(lvl)",
                                        [city.id_city, player.id_player, xCoord, yCoord, unitType, unitLvl]
                                    );
                                    dominated = true;
                                }
                            }
                            if (dominated) {
                                broadcast(JSON.stringify({
                                    classPath: 'Base.refreshWorldCityColonization',
                                    data: {
                                        xCoord,
                                        yCoord,
                                        colonized: true,
                                        city_flag: String(player.city_flag || '0'),
                                        id_guild: player.id_guild ? Number(player.id_guild) : null,
                                        id_player: Number(player.id_player)
                                    }
                                }));
                            }

                            // 6f. Send Client Completion Messages
                            {
                                const itemsSummary = droppedItems.length > 0 
                                    ? ` + غنائم أدوات (${droppedItems.map(d => d.prize + ' ×' + d.amount).join('، ')})` 
                                    : '';
                                const equipSummary = droppedEquip.length > 0
                                    ? ` + عتاد بطل نخبوي غنيمة [${droppedEquip.map(e => `${e.type} ${e.part} مستوى ${e.lvl}`).join(', ')}]`
                                    : '';
                                const woundedSummary = totalWounded > 0
                                    ? ` + تم نقل [${totalWounded}] جريح إلى مشفى المدينة`
                                    : '';
                                const lossReport = recoveryRate > 0 
                                    ? `سقط في المعركة [${totalFallen}] جندي، استعاد البطل فوراً [${totalRecovered}]، والخسائر [${totalPermanentLoss}] جندي (${Math.round((1 - recoveryRate) * 100)}%)`
                                    : `الخسائر: [${totalPermanentLoss} جندي] (إبادة كاملة 100% في حرب ضد لاعبين)`;

                                broadcast(JSON.stringify({
                                    classPath: 'Chat.WorldMsg',
                                    data: {
                                        id_player: 0,
                                        name: 'النظام الحربي',
                                        msg: `🏆 انتصار مجيد! سحق البطل [${hero.name}] قوات [${unitTitle} مستوى ${unitLvl}] (${targetCategoryName}). ${lossReport}${woundedSummary}، غنائم الموارد: ${Object.entries(creditedLoot).map(([resource, amount]) => `${resource} ${amount}`).join('، ')}، +${honorGained} شرف، +${prestigeGain} برستيج${itemsSummary}${equipSummary}!${dominated ? ' 🚩 تم الاستيلاء على البرية وضمها للمدينة بنجاح!' : ''}`,
                                        time: Math.floor(Date.now() / 1000)
                                    }
                                }));
                            }

                        } else {
                            // ── DEFEAT RESOLUTION ──
                            const heroRows = await battleDb.query("SELECT * FROM city_hero WHERE id_hero = ?", [idHero]);
                            const heroData = (heroRows && heroRows[0]) ? heroRows[0] : {};
                            const wisdom = parseInt(heroData.point_b, 10) || 0;
                            const wisdomMultiplier = 1.0 + (wisdom * 0.001);
                            const partialExp = Math.max(50, Math.floor(defPower * 0.05 * wisdomMultiplier));
                            reportExpGained = partialExp;

                            await battleDb.query("UPDATE city_hero SET exp = exp + ?, power = GREATEST(0, power - 25) WHERE id_hero = ?", [partialExp, idHero]);

                            {
                                const defeatLossReport = recoveryRate > 0 
                                    ? `سقط [${totalFallen}] جندي، تم علاج واستعادة [${totalRecovered}]، وفُقد [${totalPermanentLoss}] جندي`
                                    : `إبادة كاملة: فقدان [${totalPermanentLoss}] جندي 100% في حرب ضد لاعبين`;

                                broadcast(JSON.stringify({
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

                        try {
                            await saveWsBattleReport(battleDb, {
                                player,
                                hero,
                                battleId: battelId,
                                xCoord,
                                yCoord,
                                unitType,
                                unitLvl,
                                attackTask,
                                isVictory,
                                expGained: reportExpGained,
                                honorGained: reportHonorGained,
                                resourceLoot: reportResourceLoot,
                                itemLoot: reportItemLoot,
                                attackerSlots,
                                defenderSlots
                            });
                        } catch (reportError) {
                            console.error('[Battle Report Error]:', reportError.message);
                        }

                        await battleDb.query(
                            "UPDATE world_battel SET state = ?, time_back = ? WHERE id_battel = ? AND state = 'marching'",
                            [isVictory ? 'won' : 'lost', Math.floor(Date.now() / 1000) + marchDuration, battelId]
                        );
                        sendToPlayer(idPlayer, JSON.stringify({
                            classPath: 'Battel.Finished',
                            id_battel: battelId,
                            Battel: {
                                id_battel: battelId,
                                task: attackTask,
                                id_player: Number(player.id_player),
                                x_coord: xCoord,
                                y_coord: yCoord
                            },
                            sideWin: isVictory ? 1 : 0,
                            ItemPrize: reportItemLoot.map(item => ({ Item: item.prize, amount: item.amount })),
                            Player: { Honor: reportHonorGained }
                        }));

                        broadcast(JSON.stringify({
                            classPath: 'World.Battel.Ended',
                            id_battel: battelId,
                            xCoord,
                            yCoord,
                            xCity: Number(city.x),
                            yCity: Number(city.y),
                            stillActive: await hasActiveBattleAt(battleDb, xCoord, yCoord)
                        }));

                    } catch (bErr) {
                        console.error('[Battle Resolution Error]:', bErr.message);
                    }
                }, marchDuration * 1000);
            }

            // 3. Battle Abort (Battel/abort)
            if (msg.url === 'Battel/abort') {
                const abortData = msg.data || {};
                const battelId = Number(abortData.id_battel || abortData.idBattel || 0);
                const heroId = Number(abortData.idHero || 0);
                if (battelId > 0 || heroId > 0) {
                    const db = await getDb();
                    if (!await authenticateSocketPlayer(db, idPlayer, token)) return;
                    const abortConnection = await db.getConnection();
                    let battle;
                    let heroReturned = false;
                    try {
                        await abortConnection.beginTransaction();
                        const [battles] = await abortConnection.query(
                            "SELECT id_battel, id_hero, task, x_city, y_city, x_coord, y_coord FROM world_battel " +
                            "WHERE (? > 0 AND id_battel = ? OR ? > 0 AND id_hero = ?) " +
                            "AND id_player = ? AND state = 'marching' AND time_arrive > UNIX_TIMESTAMP() LIMIT 1 FOR UPDATE",
                            [battelId, battelId, heroId, heroId, idPlayer]
                        );
                        if (!battles.length) {
                            await abortConnection.rollback();
                        } else {
                            battle = battles[0];
                            const [abortUpdate] = await abortConnection.query(
                                "UPDATE world_battel SET state = 'aborted' WHERE id_battel = ? AND id_player = ? AND state = 'marching'",
                                [battle.id_battel, idPlayer]
                            );
                            if (abortUpdate.affectedRows) {
                                const [heroUpdate] = await abortConnection.query(
                                    "UPDATE city_hero SET in_city = 1 WHERE id_hero = ? AND id_player = ? AND in_city = 0",
                                    [battle.id_hero, idPlayer]
                                );
                                heroReturned = heroUpdate.affectedRows > 0;
                                await abortConnection.commit();
                            } else {
                                await abortConnection.rollback();
                                battle = null;
                            }
                        }
                    } catch (error) {
                        await abortConnection.rollback();
                        throw error;
                    } finally {
                        abortConnection.release();
                    }
                    if (!battle) {
                        sendToPlayer(idPlayer, JSON.stringify({
                            classPath: 'Battel.battel',
                            task: 'aborted_fail',
                            id_battel: battelId || 0
                        }));
                        return;
                    }
                    const activeBattleId = Number(battle.id_battel);
                    sendToPlayer(idPlayer, JSON.stringify({
                        classPath: 'Battel.battel',
                        task: 'aborted',
                        id_battel: activeBattleId,
                        Battel: { id_battel: activeBattleId }
                    }));
                    if (heroReturned) {
                        sendToPlayer(idPlayer, JSON.stringify({
                            classPath: 'Hero.Back',
                            idHero: Number(battle.id_hero),
                            inCity: 1,
                            Task: Number(battle.task),
                            task: Number(battle.task),
                            xTo: Number(battle.x_city),
                            yTo: Number(battle.y_city)
                        }));
                    }
                    broadcast(JSON.stringify({
                        classPath: 'World.Battel.Ended',
                        id_battel: activeBattleId,
                        xCoord: Number(battle.x_coord),
                        yCoord: Number(battle.y_coord),
                        xCity: Number(battle.x_city),
                        yCity: Number(battle.y_city),
                        stillActive: await hasActiveBattleAt(
                            db,
                            Number(battle.x_coord),
                            Number(battle.y_coord)
                        )
                    }));
                }
            }

        } catch (e) {
            console.error('[WS Message Error]:', e.message);
        }
    });

    ws.on('close', () => {
        if (idPlayer) {
            const playerClients = clients.get(String(idPlayer));
            if (playerClients) {
                playerClients.delete(ws);
                if (playerClients.size === 0) clients.delete(String(idPlayer));
            }
        }
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

let isCheckingSessions = false;
const sessionCheckTimer = setInterval(async () => {
    if (isCheckingSessions) return;
    isCheckingSessions = true;
    try {
        await closeSessionsWithStaleTokens();
    } catch (error) {
        console.error('[WS Session Check Error]:', error.message);
    } finally {
        isCheckingSessions = false;
    }
}, 2000);
sessionCheckTimer.unref();

const heroReturnTimer = setInterval(processDueHeroReturns, 1000);
heroReturnTimer.unref();

server.listen(PORT, '127.0.0.1', () => {
    console.log(`[WS-Server] ✅ Listening on port ${PORT}`);
    console.log(`[WS-Server] WebSocket: ws://localhost:${PORT}`);
    console.log(`[WS-Server] Combat, reports, and persistent hero returns are active.`);
});

process.on('uncaughtException', (err) => {
    console.error('[WS-Server] Uncaught:', err.message);
});

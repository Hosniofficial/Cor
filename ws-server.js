// WebSocket + HTTP Server for ws.elkaisar.com:8081
// Handles real-time WebSocket connections, combat marches, battles, casualties, loot, and dynamic World Map API

const http   = require('http');
const fs     = require('fs');
const path   = require('path');
const mysql  = require('./node_modules/mysql2/promise');
const { WebSocketServer } = require('./node_modules/ws');
const {
    getMarchDuration,
    getNextResetAt,
    getReturnDuration,
    getResetIntervalHours,
    isFixedDurationTarget
} = require('./battle-mechanics');
const {
    allocateProportionally,
    calculateArmyCasualties,
    calculateArmyPower,
    didBattleSideWin
} = require('./battle-party');

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
            worldUnits.set(p[0] + '_' + p[1], {
                ut: parseInt(p[2], 10),
                l: parseInt(p[3], 10),
                baseL: parseInt(p[3], 10),
                t: parseInt(p[5], 10) || 0
            });
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

// ── LiveOps Dynamic Server Configuration Cache ───────────────────────────────
let liveServerConfig = {
    camp_reset_interval_hours: 12,
    gang_reset_interval_hours: 6,
    squad_reset_interval_hours: 4,
    camp_max_attack_per_player: 10,
    wilderness_luxury_drop_multiplier: 1.0,
    global_drop_rate_multiplier: 1.0,
    equipment_drop_chance_percent: 25,
    starter_player_gold: 50,
    starter_protection_hours: 168,
    resource_production_multiplier: 1.0,
    march_speed_multiplier: 1.0,
    building_speed_multiplier: 1.0,
    study_speed_multiplier: 1.0,
    recruit_speed_multiplier: 1.0,
    camp_rally_duration_sec: 120,
    city_attack_min_duration_sec: 900,
    wounded_recovery_rate: 0.70,
    maintenance_mode: false,
    maintenance_message: 'السيرفر حالياً في وضع الصيانة الدورية لتحديث الأنظمة. سنعود قريباً!',
    server_truce_active: false,
    live_banner_text: '',
    live_banner_color: '#dfba6b'
};

async function reloadLiveServerConfig() {
    try {
        const db = await getDb();
        const [rows] = await db.query("SELECT config_key, config_value, value_type FROM server_config");
        for (const r of rows) {
            let val = r.config_value;
            if (r.value_type === 'number') {
                val = val.includes('.') ? parseFloat(val) : parseInt(val, 10);
            } else if (r.value_type === 'boolean') {
                val = val === 'true' || val === '1';
            }
            liveServerConfig[r.config_key] = val;
        }
        console.log(`[LiveOps] ✅ Dynamic configs refreshed from database (${rows.length} keys active).`);
    } catch (e) {
        console.error('[LiveOps Error] Failed to refresh server_config:', e.message);
    }
}

// Initial load and periodic background sync every 60 seconds
reloadLiveServerConfig();
setInterval(reloadLiveServerConfig, 60000);

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

async function consumeUnitRequirements(connection, idPlayer, requirements) {
    for (const requirement of requirements || []) {
        const item = String(requirement.Item || '');
        const amount = Number(requirement.amount);
        if (!item || !Number.isSafeInteger(amount) || amount <= 0) {
            throw new Error('Invalid world-unit item requirement');
        }
        const [result] = await connection.query(
            'UPDATE player_item SET amount = amount - ? WHERE id_player = ? AND id_item = ? AND amount >= ?',
            [amount, idPlayer, item, amount]
        );
        if (!result.affectedRows) {
            const error = new Error('Insufficient world-unit item requirement');
            error.code = 'INSUFFICIENT_WORLD_UNIT_ITEM';
            throw error;
        }
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
        "AND state = 'marching' LIMIT 1",
        [xCoord, yCoord]
    );
    return rows.length > 0;
}

function reportArmySlots(army, remainingArmy, defenderTroops, isVictory) {
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
    Object.entries(defenderTroops).slice(0, slotNames.length).forEach(([type, troopCount], index) => {
        const amount = Math.max(0, Number(
            troopCount && typeof troopCount === 'object' ? troopCount.pre : troopCount
        ) || 0);
        defenderSlots[slotNames[index]] = {
            type: Number(type) || 0,
            pre: amount,
            cur: troopCount && typeof troopCount === 'object'
                ? Math.max(0, Number(troopCount.cur) || 0)
                : isVictory ? 0 : amount
        };
    });

    return { attackerSlots, defenderSlots };
}

function aggregateMemberArmy(members) {
    const troops = {};
    for (const member of members) {
        for (const slot of ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3']) {
            const type = Number(member.army[`${slot}_type`]) || 0;
            const amount = Number(member.army[`${slot}_num`]) || 0;
            if (!type || amount <= 0) continue;
            const current = Number(member.remainingArmy && member.remainingArmy[slot]) || 0;
            if (!troops[type]) troops[type] = { pre: 0, cur: 0 };
            troops[type].pre += amount;
            troops[type].cur += current;
        }
    }

    const army = {};
    const remainingArmy = {};
    const entries = Object.entries(troops);
    for (let index = 0; index < 6; index++) {
        const slot = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'][index];
        const [type, counts] = entries[index] || [0, { pre: 0, cur: 0 }];
        army[`${slot}_type`] = Number(type);
        army[`${slot}_num`] = counts.pre;
        remainingArmy[slot] = counts.cur;
    }
    return { army, remainingArmy, troops };
}

async function saveWsBattleReport(db, details) {
    const {
        player, hero, battleId, xCoord, yCoord, unitType, unitLvl, attackTask,
        isVictory, expGained, honorGained, resourceLoot, itemLoot,
        attackerSlots, defenderSlots, writeBattleLog = true,
        logAttackerSlots = attackerSlots, logDefenderSlots = defenderSlots,
        heroSide = 1, opponentName = `حامية الأعداء [مستوى ${unitLvl}]`
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
            asReportHero(attackerSlots, heroSide, hero.name || 'البطل سيزر', Number(hero.avatar) || 1, Number(player.id_player), expGained),
            asReportHero(defenderSlots, 1 - heroSide, opponentName, 2, 0, 0)
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

    if (!writeBattleLog) return;
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
            Math.max(0, total(logAttackerSlots) - remaining(logAttackerSlots)),
            Math.max(0, total(logDefenderSlots) - remaining(logDefenderSlots)),
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
            "SELECT id_member, id_battel, id_player, id_hero, task, x_city, y_city " +
            "FROM world_battel_member WHERE state IN ('won', 'lost') AND time_back <= UNIX_TIMESTAMP() " +
            "ORDER BY time_back ASC LIMIT 25 FOR UPDATE"
        );
        const returned = [];
        for (const battle of dueBattles) {
            const [update] = await connection.query(
                "UPDATE world_battel_member SET state = 'returned' WHERE id_member = ? AND state IN ('won', 'lost')",
                [battle.id_member]
            );
            if (!update.affectedRows) continue;

            await connection.query(
                "UPDATE world_battel SET state = 'returned' WHERE id_battel = ? AND id_hero = ? AND state IN ('won', 'lost')",
                [battle.id_battel, battle.id_hero]
            );

            const [activeBattles] = await connection.query(
                "SELECT 1 FROM world_battel_member WHERE id_hero = ? AND id_member <> ? AND " +
                "((state = 'marching' AND time_arrive > UNIX_TIMESTAMP()) OR " +
                "(state IN ('won', 'lost') AND time_back > UNIX_TIMESTAMP())) LIMIT 1",
                [battle.id_hero, battle.id_member]
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

let processingWorldUnitResets = false;
async function processDueWorldUnitResets() {
    if (processingWorldUnitResets) return;
    processingWorldUnitResets = true;
    try {
        const db = await getDb();
        const now = Math.floor(Date.now() / 1000);
        const [units] = await db.query(
            'SELECT x_coord, y_coord, unit_type, unit_subtype, base_lvl FROM world_unit_state ' +
            'WHERE next_reset_at > 0 AND next_reset_at <= ? ORDER BY next_reset_at ASC LIMIT 100',
            [now]
        );
        for (const unit of units) {
            const nextResetAt = getNextResetAt(unit.unit_subtype, now);
            const [result] = await db.query(
                'UPDATE world_unit_state SET current_lvl = base_lvl, next_reset_at = ? ' +
                'WHERE x_coord = ? AND y_coord = ? AND unit_type = ? AND next_reset_at <= ?',
                [nextResetAt, unit.x_coord, unit.y_coord, unit.unit_type, now]
            );
            if (!result.affectedRows) continue;

            const mapUnit = worldUnits.get(`${unit.x_coord}_${unit.y_coord}`);
            if (!mapUnit || Number(mapUnit.ut) !== Number(unit.unit_type)) continue;
            mapUnit.l = Number(unit.base_lvl);
            broadcast(JSON.stringify({
                classPath: 'World.RefereshWorldUnit',
                data: {
                    WorldUnits: [{
                        x: Number(unit.x_coord),
                        y: Number(unit.y_coord),
                        t: Number(unit.unit_subtype),
                        ut: Number(unit.unit_type),
                        l: Number(unit.base_lvl)
                    }]
                }
            }));
        }
    } catch (error) {
        console.error('[World Unit Reset Error]:', error.message);
    } finally {
        processingWorldUnitResets = false;
    }
}

async function advanceWorldUnitLevel(db, { xCoord, yCoord, unitType, unitSubtype, baseLevel, maxLevel }) {
    const connection = await db.getConnection();
    let updatedLevel;
    try {
        await connection.beginTransaction();
        const now = Math.floor(Date.now() / 1000);
        const interval = getResetIntervalHours(unitSubtype);
        const initialNextResetAt = interval ? getNextResetAt(unitSubtype, now) : 0;
        await connection.query(
            'INSERT IGNORE INTO world_unit_state ' +
            '(x_coord, y_coord, unit_type, unit_subtype, base_lvl, current_lvl, max_lvl, next_reset_at) ' +
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [xCoord, yCoord, unitType, unitSubtype, baseLevel, baseLevel, maxLevel, initialNextResetAt]
        );
        const [rows] = await connection.query(
            'SELECT unit_type, unit_subtype, base_lvl, current_lvl, max_lvl, next_reset_at ' +
            'FROM world_unit_state WHERE x_coord = ? AND y_coord = ? FOR UPDATE',
            [xCoord, yCoord]
        );
        const current = rows[0]
            && Number(rows[0].unit_type) === Number(unitType)
            && Number(rows[0].unit_subtype) === Number(unitSubtype)
            ? rows[0]
            : null;
        const effectiveBaseLevel = current ? Number(current.base_lvl) : Number(baseLevel);
        const currentLevel = current
            ? (Number(current.next_reset_at) > 0 && Number(current.next_reset_at) <= now
                ? effectiveBaseLevel
                : Number(current.current_lvl))
            : Number(baseLevel);
        updatedLevel = Math.min(Number(maxLevel), currentLevel + 1);
        const nextResetAt = current && Number(current.next_reset_at) > now
            ? Number(current.next_reset_at)
            : initialNextResetAt;
        await connection.query(
            'UPDATE world_unit_state SET unit_type = ?, unit_subtype = ?, base_lvl = ?, current_lvl = ?, ' +
            'max_lvl = ?, next_reset_at = ? WHERE x_coord = ? AND y_coord = ?',
            [unitType, unitSubtype, effectiveBaseLevel, updatedLevel, maxLevel, nextResetAt, xCoord, yCoord]
        );
        await connection.commit();
    } catch (error) {
        await connection.rollback();
        throw error;
    } finally {
        connection.release();
    }

    const mapUnit = worldUnits.get(`${xCoord}_${yCoord}`);
    if (mapUnit && Number(mapUnit.ut) === Number(unitType)) {
        mapUnit.l = updatedLevel;
        broadcast(JSON.stringify({
            classPath: 'World.RefereshWorldUnit',
            data: {
                WorldUnits: [{ x: xCoord, y: yCoord, t: unitSubtype, ut: unitType, l: updatedLevel }]
            }
        }));
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

    // 0. LiveOps Internal Endpoints (From Admin Panel)
    if (uriPath === '/api/internal/reload_config') {
        await reloadLiveServerConfig();
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify({ state: 'ok', msg: 'Config reloaded' }));
        return;
    }
    if (uriPath === '/api/internal/reset_camps') {
        broadcast(JSON.stringify({
            classPath: 'Chat.WorldMsg',
            data: {
                id_player: 0,
                name: 'القيادة العسكرية',
                msg: '🚩 تم تجديد وإعادة تعيين جميع المعسكرات والمناورات وعصابات التمرد على مستوى السيرفر كاملاً! استعدوا للقتال!',
                time: Math.floor(Date.now() / 1000)
            }
        }));
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify({ state: 'ok', msg: 'Camps reset broadcasted' }));
        return;
    }
    if (uriPath === '/api/internal/broadcast_banner') {
        let body = '';
        req.on('data', chunk => { body += chunk; });
        req.on('end', () => {
            const params = new URLSearchParams(body);
            const text = params.get('text') || '';
            const color = params.get('color') || '#dfba6b';
            broadcast(JSON.stringify({
                classPath: 'World.MarqueeBanner',
                text: text,
                color: color,
                time: Math.floor(Date.now() / 1000)
            }));
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ state: 'ok' }));
        });
        return;
    }

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

            if (msg.url === 'Battel/joined') {
                if (!idPlayer || !token) return;
                const idBattle = Number(msg.data && msg.data.idBattel);
                if (!Number.isSafeInteger(idBattle) || idBattle <= 0) return;

                const db = await getDb();
                if (!await authenticateSocketPlayer(db, idPlayer, token)) return;
                const [rows] = await db.query(
                    "SELECT b.id_battel, b.x_coord, b.y_coord, " +
                    "(SELECT COUNT(*) FROM world_battel_member m WHERE m.id_battel = b.id_battel " +
                    "AND m.side = 1 AND m.state IN ('marching', 'won', 'lost')) AS attackNum, " +
                    "(SELECT COUNT(*) FROM world_battel_member m WHERE m.id_battel = b.id_battel " +
                    "AND m.side = 0 AND m.state IN ('marching', 'won', 'lost')) AS defenceNum " +
                    "FROM world_battel b WHERE b.id_battel = ? AND b.state = 'marching' " +
                    "AND b.time_arrive > UNIX_TIMESTAMP() LIMIT 1",
                    [idBattle]
                );
                if (!rows.length) return;
                const battle = rows[0];
                broadcast(JSON.stringify({
                    classPath: 'World.Battel.ParticipantsUpdated',
                    id_battel: Number(battle.id_battel),
                    xCoord: Number(battle.x_coord),
                    yCoord: Number(battle.y_coord),
                    attackNum: Number(battle.attackNum),
                    defenceNum: Number(battle.defenceNum)
                }));
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
                if (![xCoord, yCoord, idHero].every(Number.isSafeInteger)
                    || xCoord < 0 || xCoord >= 500 || yCoord < 0 || yCoord >= 500 || idHero <= 0) {
                    ws.send(JSON.stringify({ classPath: 'Battel.StartFailed', state: 'in_attackable' }));
                    return;
                }

                // 2a. Check Hero & Troops
                const [hArmy] = await db.query("SELECT * FROM hero_army WHERE id_hero = ?", [idHero]);
                const army = hArmy[0] || {};
                const totalTroops = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3']
                    .reduce((sum, slot) => sum + (Number(army[`${slot}_num`]) || 0), 0);

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
                const city = cRows[0];
                if (!city) {
                    ws.send(JSON.stringify({ classPath: 'Battel.StartFailed', state: 'hero_cant_used' }));
                    return;
                }
                const [pRows] = await db.query("SELECT * FROM player WHERE id_player = ?", [city.id_player]);
                const player = pRows[0];
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
                let unitSubtype = 0;
                let isBarray = false;
                let unitTitle = 'أرض منبسطة';

                if (targetCityRows.length > 0) {
                    if (Number(targetCityRows[0].id_player) === Number(idPlayer)) {
                        ws.send(JSON.stringify({ classPath: 'Battel.StartFailed', state: 'in_attackable' }));
                        return;
                    }
                    unitType = 60; // Player City (PvP)
                    unitLvl = targetCityRows[0].lvl || 10;
                    unitTitle = `مدينة [${targetCityRows[0].name}]`;
                } else {
                    const targetKey = `${xCoord}_${yCoord}`;
                    const [barrayRows] = await db.query(
                        'SELECT Type, lvl, id_player FROM city_barray WHERE x_coord = ? AND y_coord = ? LIMIT 1',
                        [xCoord, yCoord]
                    );
                    const mapUnit = barrayRows.length
                        ? { ut: Number(barrayRows[0].Type), l: Number(barrayRows[0].lvl), t: 0, idPlayer: Number(barrayRows[0].id_player) }
                        : worldUnits.get(targetKey) || { ut: 0, l: 1, t: 0 };
                    isBarray = barrayRows.length > 0;
                    if (mapUnit.idPlayer === Number(idPlayer)) {
                        ws.send(JSON.stringify({ classPath: 'Battel.StartFailed', state: 'in_attackable' }));
                        return;
                    }
                    unitType = mapUnit.ut;
                    unitLvl = Math.max(1, mapUnit.l || 1);
                    unitSubtype = mapUnit.t || 0;
                    if (!isBarray && unitType) {
                        const [stateRows] = await db.query(
                            'SELECT unit_type, unit_subtype, base_lvl, current_lvl, next_reset_at FROM world_unit_state WHERE x_coord = ? AND y_coord = ? LIMIT 1',
                            [xCoord, yCoord]
                        );
                        const state = stateRows[0];
                        if (state && Number(state.unit_type) === Number(unitType)
                            && Number(state.unit_subtype) === Number(unitSubtype)) {
                            unitLvl = Number(state.next_reset_at) > 0 && Number(state.next_reset_at) <= Math.floor(Date.now() / 1000)
                                ? Number(state.base_lvl)
                                : Number(state.current_lvl);
                        }
                    }
                    unitTitle = (unitData[unitType] && unitData[unitType].Title) ? unitData[unitType].Title : (unitType === 0 ? 'أرض منبسطة' : 'برية');
                }
                if (!unitType) {
                    ws.send(JSON.stringify({ classPath: 'Battel.StartFailed', state: 'in_attackable' }));
                    return;
                }

                // 2c. Calculate March Duration using dynamic configs & authentic rally logic
                const speedMult = Math.max(0.1, Number(liveServerConfig.march_speed_multiplier) || 1.0);
                if (unitType === 60 && liveServerConfig.server_truce_active) {
                    ws.send(JSON.stringify({ classPath: 'Battel.StartFailed', state: 'in_attackable', msg: 'السيرفر حالياً في هدنة عامة، لا يمكن الهجوم على مدن اللاعبين!' }));
                    return;
                }
                const unitTypeData = unitData[unitType] || {};
                if ((Number(unitTypeData.reqProm) || 0) > (Number(player.porm) || 0)
                    || (Number(unitTypeData.reqFitness) || 0) > (Number(hero.power) || 0)) {
                    ws.send(JSON.stringify({ classPath: 'Battel.StartFailed', state: 'hero_cant_used' }));
                    return;
                }
                const calculatedDuration = getMarchDuration({
                    x1: city.x,
                    y1: city.y,
                    x2: xCoord,
                    y2: yCoord,
                    army,
                    unitType,
                    isCity: false
                });
                const configuredDuration = unitType === 60
                    ? Math.max(Number(liveServerConfig.city_attack_min_duration_sec) || 900, calculatedDuration)
                    : isFixedDurationTarget(unitType) && unitType !== 177
                        ? (Number(liveServerConfig.camp_rally_duration_sec) || calculatedDuration)
                        : calculatedDuration;
                const marchDuration = Math.max(1, Math.ceil(configuredDuration / speedMult));
                const returnDuration = getReturnDuration({
                    x1: city.x,
                    y1: city.y,
                    x2: xCoord,
                    y2: yCoord,
                    army
                });
                const now = Math.floor(Date.now() / 1000);
                const arrive = now + marchDuration;

                // 2d. Insert into world_battel
                const launchConnection = await db.getConnection();
                let ins;
                try {
                    await launchConnection.beginTransaction();
                    await consumeUnitRequirements(launchConnection, idPlayer, unitTypeData.MakeReq);
                    const [heroUpdate] = await launchConnection.query(
                        "UPDATE city_hero SET in_city = 0 WHERE id_hero = ? AND id_player = ? AND in_city = 1",
                        [idHero, idPlayer]
                    );
                    if (!heroUpdate.affectedRows) {
                        const error = new Error('Hero is not available in city');
                        error.code = 'HERO_NOT_AVAILABLE';
                        throw error;
                    }
                    [ins] = await launchConnection.query(
                        "INSERT INTO world_battel (id_player, id_city, id_hero, task, x_city, y_city, x_coord, y_coord, time_start, time_arrive, time_back, return_duration, state) " +
                        "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'marching')",
                        [player.id_player, city.id_city, idHero, attackTask, city.x, city.y, xCoord, yCoord, now, arrive, returnDuration]
                    );
                    await launchConnection.query(
                        "INSERT INTO world_battel_member (id_battel, id_player, id_city, id_hero, side, task, x_city, y_city, time_start, time_arrive, return_duration, state) " +
                        "VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, 'marching')",
                        [ins.insertId, player.id_player, city.id_city, idHero, attackTask, city.x, city.y, now, arrive, returnDuration]
                    );
                    await launchConnection.commit();
                } catch (error) {
                    await launchConnection.rollback();
                    if (error.code === 'INSUFFICIENT_WORLD_UNIT_ITEM' || error.code === 'HERO_NOT_AVAILABLE') {
                        ws.send(JSON.stringify({
                            classPath: 'Battel.StartFailed',
                            state: error.code === 'INSUFFICIENT_WORLD_UNIT_ITEM' ? 'no_enough_mat' : 'hero_cant_used'
                        }));
                        return;
                    }
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
                        const slots = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
                        const [memberRows] = await battleDb.query(
                            "SELECT m.id_member, m.id_player, m.id_city, m.id_hero, m.side, m.task, " +
                            "m.x_city, m.y_city, m.return_duration, h.name AS hero_name, h.avatar AS hero_avatar, " +
                            "h.power AS hero_power, h.power_max AS hero_power_max, h.point_b, h.point_b_plus, " +
                            "p.name AS player_name, p.id_guild AS player_id_guild, p.city_flag, " +
                            "c.name AS city_name, c.x AS city_x, c.y AS city_y " +
                            "FROM world_battel_member m " +
                            "JOIN city_hero h ON h.id_hero = m.id_hero AND h.id_player = m.id_player " +
                            "JOIN player p ON p.id_player = m.id_player " +
                            "JOIN city c ON c.id_city = m.id_city " +
                            "WHERE m.id_battel = ? AND m.state = 'marching' " +
                            "AND m.time_arrive <= (SELECT time_arrive FROM world_battel WHERE id_battel = ?) " +
                            "ORDER BY m.id_member ASC",
                            [battelId, battelId]
                        );
                        if (!memberRows.length) {
                            throw new Error(`Battle ${battelId} has no arrived members`);
                        }
                        const memberIds = memberRows.map(member => Number(member.id_hero));
                        const memberPlaceholders = memberIds.map(() => '?').join(', ');
                        const [memberArmies] = await battleDb.query(
                            `SELECT * FROM hero_army WHERE id_hero IN (${memberPlaceholders})`,
                            memberIds
                        );
                        const [memberEquips] = await battleDb.query(
                            `SELECT * FROM player_equip WHERE on_hero = 1 AND id_hero IN (${memberPlaceholders})`,
                            memberIds
                        );
                        const [memberEducation] = await battleDb.query(
                            `SELECT id_player, medicine FROM player_edu WHERE id_player IN (${memberRows.map(() => '?').join(', ')})`,
                            memberRows.map(member => Number(member.id_player))
                        );
                        const armiesByHero = new Map(memberArmies.map(memberArmy => [Number(memberArmy.id_hero), memberArmy]));
                        const equipsByHero = new Map();
                        for (const memberEquip of memberEquips) {
                            const id = Number(memberEquip.id_hero);
                            if (!equipsByHero.has(id)) equipsByHero.set(id, []);
                            const bonus = equipPowerMap.get(`${memberEquip.type}_${memberEquip.part}_${memberEquip.lvl}`);
                            if (bonus) equipsByHero.get(id).push(bonus);
                        }
                        const medicineByPlayer = new Map(memberEducation.map(education => [
                            Number(education.id_player),
                            Number(education.medicine) || 0
                        ]));
                        const battleMembers = memberRows.map(member => {
                            const memberHero = {
                                id_hero: Number(member.id_hero),
                                id_player: Number(member.id_player),
                                name: member.hero_name,
                                avatar: Number(member.hero_avatar) || 1,
                                power: Number(member.hero_power) || 0,
                                power_max: Number(member.hero_power_max) || 0,
                                point_b: Number(member.point_b) || 0,
                                point_b_plus: Number(member.point_b_plus) || 0
                            };
                            const memberPlayer = {
                                id_player: Number(member.id_player),
                                name: member.player_name,
                                id_guild: Number(member.player_id_guild) || 0,
                                city_flag: member.city_flag
                            };
                            const memberCity = {
                                id_city: Number(member.id_city),
                                name: member.city_name,
                                x: Number(member.city_x),
                                y: Number(member.city_y)
                            };
                            const memberArmy = armiesByHero.get(memberHero.id_hero);
                            if (!memberArmy) {
                                throw new Error(`Battle ${battelId} is missing army for hero ${memberHero.id_hero}`);
                            }
                            const memberData = {
                                id_member: Number(member.id_member),
                                side: Number(member.side),
                                task: Number(member.task),
                                returnDuration: Number(member.return_duration) || returnDuration,
                                hero: memberHero,
                                player: memberPlayer,
                                city: memberCity,
                                army: memberArmy,
                                medicineLevel: medicineByPlayer.get(memberPlayer.id_player) || 0,
                                expGained: 0,
                                honorGained: 0,
                                itemLoot: []
                            };
                            memberData.combatPower = calculateArmyPower(
                                memberData.army,
                                memberData.hero,
                                SOL_STATS,
                                equipsByHero.get(memberHero.id_hero) || []
                            );
                            return memberData;
                        });
                        const attackers = battleMembers.filter(member => member.side === 1);
                        const joinedDefenders = battleMembers.filter(member => member.side === 0);
                        if (!attackers.length) {
                            throw new Error(`Battle ${battelId} has no attacking members`);
                        }
                        const attPower = attackers.reduce((total, member) => total + member.combatPower, 0);

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

                        // 3. Calculate Defender Combat Power (with joined defenders)
                        let defPower = 0;
                        for (const [type, num] of Object.entries(defenderTroops)) {
                            const t = parseInt(type, 10);
                            const n = parseInt(num, 10) || 0;
                            if (t > 0 && n > 0 && SOL_STATS[t]) {
                                defPower += (SOL_STATS[t].att * 0.6 + SOL_STATS[t].hp * 0.4) * n;
                            }
                        }
                        defPower *= (1.0 + unitLvl * 0.05);
                        defPower += joinedDefenders.reduce((total, member) => total + member.combatPower, 0);

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

                        const solTypeToCol = { 1: 'army_a', 2: 'army_b', 3: 'army_c', 4: 'army_d', 5: 'army_e', 6: 'army_f' };
                        const attackReport = aggregateMemberArmy(attackers);
                        const defenderReport = aggregateMemberArmy(joinedDefenders);
                        const opposingDefenseReport = Object.fromEntries(
                            Object.entries(defenderTroops).map(([type, amount]) => [
                                type,
                                { pre: Number(amount) || 0, cur: isVictory ? 0 : Number(amount) || 0 }
                            ])
                        );
                        for (const [type, counts] of Object.entries(defenderReport.troops)) {
                            if (!opposingDefenseReport[type]) opposingDefenseReport[type] = { pre: 0, cur: 0 };
                            opposingDefenseReport[type].pre += counts.pre;
                            opposingDefenseReport[type].cur += counts.cur;
                        }
                        const casualtiesBySide = {
                            0: { fallen: 0, recovered: 0, permanentLoss: 0, wounded: 0 },
                            1: { fallen: 0, recovered: 0, permanentLoss: 0, wounded: 0 }
                        };
                        for (const member of battleMembers) {
                            const memberIsAttacker = member.side === 1;
                            const memberWon = didBattleSideWin(isVictory, member.side);
                            const memberRatio = memberIsAttacker ? ratio : 1 / Math.max(ratio, Number.EPSILON);
                            const memberLossRate = memberWon
                                ? Math.min(0.50, Math.max(0.02, 0.40 / Math.sqrt(memberRatio)))
                                : Math.min(0.95, 0.65 + (1.0 - memberRatio) * 0.30);
                            const woundedRate = Math.min(0.60, 0.10 + member.medicineLevel * 0.05);
                            const casualties = calculateArmyCasualties(
                                member.army,
                                memberLossRate,
                                recoveryRate,
                                woundedRate,
                                memberWon
                            );
                            member.casualties = casualties;
                            member.remainingArmy = {};
                            for (const slot of slots) {
                                const result = casualties.remainingArmy[slot];
                                member.remainingArmy[slot] = result.amount;
                                if (Number(member.army[`${slot}_num`]) > 0) {
                                    await battleDb.query(
                                        `UPDATE hero_army SET ${slot}_num = ?, ${slot}_type = ? WHERE id_hero = ?`,
                                        [result.amount, result.type, member.hero.id_hero]
                                    );
                                }
                            }

                            for (const [type, amount] of Object.entries(casualties.wounded)) {
                                const column = solTypeToCol[type];
                                if (!column || amount <= 0) continue;
                                if (!member.woundedTroops) {
                                    member.woundedTroops = { army_a: 0, army_b: 0, army_c: 0, army_d: 0, army_e: 0, army_f: 0 };
                                }
                                member.woundedTroops[column] += amount;
                            }

                            for (const key of Object.keys(casualtiesBySide[member.side])) {
                                casualtiesBySide[member.side][key] += casualties.totals[key];
                            }
                        }
                        const hostMember = battleMembers.find(member => member.hero.id_hero === Number(idHero));
                        if (!hostMember) {
                            throw new Error(`Battle ${battelId} is missing the initiating hero`);
                        }
                        for (const member of battleMembers) {
                            const wounded = member.woundedTroops || {};
                            const woundedAmount = Object.values(wounded).reduce((sum, amount) => sum + amount, 0);
                            if (!woundedAmount) continue;
                            await battleDb.query(
                                `INSERT INTO city_wounded (id_city, army_a, army_b, army_c, army_d, army_e, army_f)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE
                                 army_a = army_a + VALUES(army_a), army_b = army_b + VALUES(army_b), army_c = army_c + VALUES(army_c),
                                 army_d = army_d + VALUES(army_d), army_e = army_e + VALUES(army_e), army_f = army_f + VALUES(army_f)`,
                                [
                                    member.city.id_city,
                                    wounded.army_a || 0,
                                    wounded.army_b || 0,
                                    wounded.army_c || 0,
                                    wounded.army_d || 0,
                                    wounded.army_e || 0,
                                    wounded.army_f || 0
                                ]
                            );
                        }
                        const attackingCasualties = casualtiesBySide[1];

                        if (isVictory) {
                            // ── VICTORY RESOLUTION ──
                            if (!targetCityRows.length && !isBarray && unitTypeData.lvlChange === true
                                && Number(unitTypeData.maxLvl) > unitLvl) {
                                const baseUnit = worldUnits.get(`${xCoord}_${yCoord}`);
                                if (baseUnit) {
                                    await advanceWorldUnitLevel(battleDb, {
                                        xCoord,
                                        yCoord,
                                        unitType,
                                        unitSubtype,
                                        baseLevel: Number(baseUnit.baseL) || unitLvl,
                                        maxLevel: Number(unitTypeData.maxLvl)
                                    });
                                }
                            }
                            // 6a. Loot Resources
                            const loot = Math.min(100000, Math.max(2000, unitLvl * 3500));
                            const lootPool = {
                                food: loot,
                                wood: loot,
                                stone: loot,
                                metal: loot,
                                coin: loot * 2
                            };
                            for (const share of allocateProportionally(loot, attackers)) {
                                share.member.resourceLoot = await creditCityResources(battleDb, share.member.city.id_city, {
                                    food: share.amount,
                                    wood: share.amount,
                                    stone: share.amount,
                                    metal: share.amount,
                                    coin: share.amount * 2
                                });
                            }
                            const creditedLoot = Object.fromEntries(
                                Object.keys(lootPool).map(resource => [
                                    resource,
                                    attackers.reduce((total, member) => total + (Number(member.resourceLoot && member.resourceLoot[resource]) || 0), 0)
                                ])
                            );

                            // 6b. Hero EXP & Player Prestige by Target Category & Hero Wisdom
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

                            const expShares = allocateProportionally(baseExp, attackers);
                            const honorShares = allocateProportionally(honorGained, attackers);
                            const prestigeShares = allocateProportionally(prestigeGain, attackers);
                            for (let index = 0; index < attackers.length; index++) {
                                const member = attackers[index];
                                const wisdomMultiplier = 1 + (Number(member.hero.point_b) || 0) * 0.001;
                                member.expGained = Math.floor(expShares[index].amount * wisdomMultiplier);
                                member.honorGained = honorShares[index].amount;
                                member.prestigeGained = prestigeShares[index].amount;
                                member.itemLoot = [];
                                await battleDb.query(
                                    "UPDATE city_hero SET exp = exp + ?, power = LEAST(power_max, power + 5) WHERE id_hero = ?",
                                    [member.expGained, member.hero.id_hero]
                                );
                                await battleDb.query(
                                    "UPDATE player SET prestige = prestige + ?, honor = honor + ? WHERE id_player = ?",
                                    [member.prestigeGained, member.honorGained, member.player.id_player]
                                );
                                if (member.player.id_guild > 0) {
                                    await battleDb.query(
                                        "UPDATE guild SET prestige = prestige + ?, honor = honor + ? WHERE id_guild = ?",
                                        [member.prestigeGained, member.honorGained, member.player.id_guild]
                                    );
                                }
                            }
                            for (const member of battleMembers) {
                                if (!member.itemLoot) member.itemLoot = [];
                            }

                            // 6c. Prize Drop Tables Evaluation (with Dynamic Multipliers & Wilderness Luxuries)
                            const droppedItems = [];
                            const winPrizes = prizeData.Win ? prizeData.Win.filter(x => x.unitType == unitType && x.lvl == unitLvl) : [];
                            const globalMult = Math.max(0.1, Number(liveServerConfig.global_drop_rate_multiplier) || 1.0);
                            const luxuryMult = Math.max(0.1, Number(liveServerConfig.wilderness_luxury_drop_multiplier) || 1.0);
                            const luxuryKeys = ['luxury_1', 'luxury_2', 'bread', 'necklace_1', 'necklace_2', 'necklace_3', 'necklace_4', 'necklace_5', 't_map'];

                            for (const p of winPrizes) {
                                let effectiveRate = p.win_rate * globalMult;
                                if (luxuryKeys.includes(p.prize)) {
                                    effectiveRate *= luxuryMult;
                                }
                                if (Math.random() * 1000 <= effectiveRate) {
                                    const amt = Math.floor(Math.random() * (p.amount_max - p.amount_min + 1)) + p.amount_min;
                                    if (amt > 0) {
                                        droppedItems.push({ prize: p.prize, amount: amt });
                                    }
                                }
                            }
                            for (const drop of droppedItems) {
                                for (const share of allocateProportionally(drop.amount, attackers)) {
                                    if (!share.amount) continue;
                                    await battleDb.query(
                                        "INSERT INTO player_item (id_player, id_item, amount) VALUES (?, ?, ?) " +
                                        "ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)",
                                        [share.member.player.id_player, drop.prize, share.amount]
                                    );
                                    share.member.itemLoot.push({ prize: drop.prize, amount: share.amount });
                                }
                            }

                            // 6d. Direct Hero Equipment Drop Chance (From Elite Camps, Gangs, Arenas)
                            const droppedEquip = [];
                            let equipDropChance = 0;
                            let possibleEquipTypes = [];
                            const customEquipChance = (Number(liveServerConfig.equipment_drop_chance_percent) || 25) / 100;
                            if (unitType >= 97 && unitType <= 105 && unitLvl >= 3) {
                                equipDropChance = customEquipChance; // e.g. 25% on Carthage & Gangs lvl 3+
                                possibleEquipTypes = ['mili', 'peace', 'victory', 'mars'];
                            } else if (unitType >= 70 && unitType <= 96 && unitLvl >= 3) {
                                equipDropChance = customEquipChance * 0.8; // e.g. on Camps & Maneuvers lvl 3+
                                possibleEquipTypes = ['loy', 'sparta', 'lion'];
                            } else if (unitType >= 150 && unitType <= 236) {
                                equipDropChance = Math.min(1.0, customEquipChance * 1.5); // on Arenas & Fortresses
                                possibleEquipTypes = ['fearless', 'thunder', 'holy', 'neptune', 'thor'];
                            }

                            if (possibleEquipTypes.length > 0 && Math.random() < equipDropChance) {
                                const parts = ['sword', 'shield', 'helmet', 'armor', 'boot', 'belt'];
                                const eqType = possibleEquipTypes[Math.floor(Math.random() * possibleEquipTypes.length)];
                                const eqPart = parts[Math.floor(Math.random() * parts.length)];
                                const eqLvl = Math.max(1, Math.min(10, unitLvl));
                                const equipRecipient = allocateProportionally(1, attackers)
                                    .find(share => share.amount > 0).member;
                                await battleDb.query(
                                    "INSERT INTO player_equip (id_player, id_hero, type, part, lvl, on_hero) VALUES (?, 0, ?, ?, ?, 0)",
                                    [equipRecipient.player.id_player, eqType, eqPart, eqLvl]
                                );
                                droppedEquip.push({ type: eqType, part: eqPart, lvl: eqLvl });
                                equipRecipient.itemLoot.push({
                                    prize: `equip_${eqType}_${eqPart}`,
                                    amount: 1
                                });
                            }
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
                                const woundedSummary = attackingCasualties.wounded > 0
                                    ? ` + تم نقل [${attackingCasualties.wounded}] جريح إلى مشفى المدينة`
                                    : '';
                                const lossReport = recoveryRate > 0 
                                        ? `سقط في المعركة [${attackingCasualties.fallen}] جندي، استعاد المهاجمون [${attackingCasualties.recovered}]، أُرسل [${attackingCasualties.wounded}] للمشفى، والخسائر النهائية [${attackingCasualties.permanentLoss}] جندي`
                                        : `الخسائر النهائية: [${attackingCasualties.permanentLoss} جندي] (100% في حرب ضد لاعبين)`;

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
                            const partialExp = Math.max(50, Math.floor(defPower * 0.05));
                            const expShares = allocateProportionally(partialExp, attackers);
                            for (let index = 0; index < attackers.length; index++) {
                                const member = attackers[index];
                                member.expGained = Math.floor(
                                    expShares[index].amount * (1 + (Number(member.hero.point_b) || 0) * 0.001)
                                );
                                await battleDb.query(
                                    "UPDATE city_hero SET exp = exp + ?, power = GREATEST(0, power - 25) WHERE id_hero = ?",
                                    [member.expGained, member.hero.id_hero]
                                );
                            }
                            {
                                const defeatLossReport = recoveryRate > 0 
                                    ? `سقط [${attackingCasualties.fallen}] جندي، استُعيد [${attackingCasualties.recovered}]، أُرسل [${attackingCasualties.wounded}] للمشفى، والخسائر النهائية [${attackingCasualties.permanentLoss}] جندي`
                                    : `إبادة كاملة: الخسائر النهائية [${attackingCasualties.permanentLoss}] جندي 100% في حرب ضد لاعبين`;

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

                        for (const member of battleMembers) {
                            const memberWon = didBattleSideWin(isVictory, member.side);
                            const opponentTroops = member.side === 1 ? opposingDefenseReport : attackReport.troops;
                            const memberSlots = reportArmySlots(
                                member.army,
                                member.remainingArmy,
                                opponentTroops,
                                memberWon
                            );
                            try {
                                await saveWsBattleReport(battleDb, {
                                    player: member.player,
                                    hero: member.hero,
                                    battleId: battelId,
                                    xCoord,
                                    yCoord,
                                    unitType,
                                    unitLvl,
                                    attackTask: member.task,
                                    isVictory,
                                    expGained: member.expGained,
                                    honorGained: member.honorGained,
                                    resourceLoot: member.resourceLoot || { food: 0, wood: 0, stone: 0, metal: 0, coin: 0 },
                                    itemLoot: member.itemLoot,
                                    attackerSlots: memberSlots.attackerSlots,
                                    defenderSlots: memberSlots.defenderSlots,
                                    writeBattleLog: member.hero.id_hero === Number(idHero),
                                    heroSide: member.side,
                                    opponentName: member.side === 1
                                        ? `حامية الأعداء [مستوى ${unitLvl}]`
                                        : `قوات المهاجمين [${player.name}]`,
                                    logAttackerSlots: reportArmySlots(
                                        attackReport.army,
                                        attackReport.remainingArmy,
                                        opposingDefenseReport,
                                        isVictory
                                    ).attackerSlots,
                                    logDefenderSlots: reportArmySlots(
                                        attackReport.army,
                                        attackReport.remainingArmy,
                                        opposingDefenseReport,
                                        isVictory
                                    ).defenderSlots
                                });
                            } catch (reportError) {
                                console.error(`[Battle Report Error] Hero ${member.hero.id_hero}:`, reportError.message);
                            }
                        }

                        const returnAt = Math.floor(Date.now() / 1000) + returnDuration;
                        await battleDb.query(
                            "UPDATE world_battel SET state = ?, time_back = ? WHERE id_battel = ? AND state = 'marching'",
                            [isVictory ? 'won' : 'lost', returnAt, battelId]
                        );
                        await battleDb.query(
                            "UPDATE world_battel_member SET state = ?, time_back = UNIX_TIMESTAMP() + return_duration " +
                            "WHERE id_battel = ? AND state = 'marching'",
                            [isVictory ? 'won' : 'lost', battelId]
                        );
                        for (const member of battleMembers) {
                            sendToPlayer(member.player.id_player, JSON.stringify({
                                classPath: 'Battel.Finished',
                                id_battel: battelId,
                                Battel: {
                                    id_battel: battelId,
                                    task: member.task,
                                    id_player: member.player.id_player,
                                    x_coord: xCoord,
                                    y_coord: yCoord
                                },
                                sideWin: didBattleSideWin(isVictory, member.side) ? 1 : 0,
                                ItemPrize: member.itemLoot.map(item => ({ Item: item.prize, amount: item.amount })),
                                Player: { Honor: member.honorGained }
                            }));
                        }

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
                    let returnedHeroes = [];
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
                                const [members] = await abortConnection.query(
                                    "SELECT id_hero, id_player, task, x_city, y_city FROM world_battel_member " +
                                    "WHERE id_battel = ? AND state = 'marching' FOR UPDATE",
                                    [battle.id_battel]
                                );
                                await abortConnection.query(
                                    "UPDATE world_battel_member SET state = 'aborted' " +
                                    "WHERE id_battel = ? AND state = 'marching'",
                                    [battle.id_battel]
                                );
                                for (const member of members) {
                                    const [heroUpdate] = await abortConnection.query(
                                        "UPDATE city_hero SET in_city = 1 " +
                                        "WHERE id_hero = ? AND id_player = ? AND in_city = 0",
                                        [member.id_hero, member.id_player]
                                    );
                                    if (heroUpdate.affectedRows) returnedHeroes.push(member);
                                }
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
                    for (const returnedHero of returnedHeroes) {
                        sendToPlayer(returnedHero.id_player, JSON.stringify({
                            classPath: 'Hero.Back',
                            idHero: Number(returnedHero.id_hero),
                            inCity: 1,
                            Task: Number(returnedHero.task),
                            task: Number(returnedHero.task),
                            xTo: Number(returnedHero.x_city),
                            yTo: Number(returnedHero.y_city)
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
const worldUnitResetTimer = setInterval(processDueWorldUnitResets, 60000);
worldUnitResetTimer.unref();

server.listen(PORT, '127.0.0.1', () => {
    console.log(`[WS-Server] ✅ Listening on port ${PORT}`);
    console.log(`[WS-Server] WebSocket: ws://localhost:${PORT}`);
    console.log(`[WS-Server] Combat, reports, and persistent hero returns are active.`);
});

process.on('uncaughtException', (err) => {
    console.error('[WS-Server] Uncaught:', err.message);
});

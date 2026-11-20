// WebSocket + HTTP Server for CAESER OF ARABS
// Handles real-time WebSocket connections, combat marches, battles, casualties, loot, and dynamic World Map API

const http   = require('http');
const https  = require('https');
const fs     = require('fs');
const path   = require('path');
const mysql  = require('mysql2/promise');
const { WebSocketServer } = require('ws');
const {
    HERO_ACTION_POWER_COST,
    getHeroRequiredPower,
    applyPersistedWorldUnitLevels,
    getMarchDuration,
    getNextResetAt,
    getReturnDuration,
    getResetIntervalHours,
    isFixedDurationTarget,
    normalizeDynamicWorldUnitSeedLevels,
    recoverHeroPower
} = require('../caesar-shared/battle-mechanics');
const {
    allocateProportionally,
    calculateArmyCasualties,
    calculateArmyCapacity,
    calculateArmyPower,
    calculateKillBasedRewards,
    distributeMemberTroopLosses,
    didBattleSideWin,
    simulateTacticalCombat
} = require('../caesar-shared/battle-party');
const { resolveBattle } = require('../caesar-shared/battle-resolution');
const { getHeroOutsideCityLimit } = require('../caesar-shared/hero-deployment');
const { isAuthorizedInternalRequest } = require('../caesar-shared/internal-api-auth');
const { handleRequiredAssetError } = require('../caesar-shared/startup-asset-policy');

const PORT    = 8081;
const API_PORT = Number(process.env.CAESAR_API_PORT || 8092);
const API_DIR = path.join(__dirname, 'api');
const JSON_DIR = path.join(__dirname, '..', 'caesar-assets', 'api', 'js-0.0.24', 'json');
if (!Number.isInteger(API_PORT) || API_PORT < 1 || API_PORT > 65535) {
    throw new Error('CAESAR_API_PORT must be between 1 and 65535');
}
const ENVIRONMENT = (process.env.CAESAR_ENV || 'development').toLowerCase();
if (!['development', 'test', 'production'].includes(ENVIRONMENT)) {
    throw new Error('CAESAR_ENV must be development, test, or production');
}
const IS_PRODUCTION = ENVIRONMENT === 'production';
const TLS_CERT_FILE = (process.env.CAESAR_TLS_CERT_FILE || '').trim();
const TLS_KEY_FILE = (process.env.CAESAR_TLS_KEY_FILE || '').trim();
if (Boolean(TLS_CERT_FILE) !== Boolean(TLS_KEY_FILE)) {
    throw new Error('CAESAR_TLS_CERT_FILE and CAESAR_TLS_KEY_FILE must be configured together');
}
if (process.env.CAESAR_REQUIRE_TLS === '1' && !TLS_CERT_FILE) {
    throw new Error('TLS is required but CAESAR_TLS_CERT_FILE and CAESAR_TLS_KEY_FILE are not configured');
}
const INTERNAL_API_TOKEN = (process.env.CAESAR_INTERNAL_API_TOKEN || '').trim();
if (IS_PRODUCTION && !INTERNAL_API_TOKEN) {
    throw new Error('Missing required setting: CAESAR_INTERNAL_API_TOKEN');
}

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
    host: getDatabaseSetting('CAESAR_DB_HOST', '127.0.0.1'),
    port: Number(getDatabaseSetting('CAESAR_DB_PORT', '3306')),
    user: getDatabaseSetting('CAESAR_DB_USER', 'root'),
    password: getDatabaseSetting('CAESAR_DB_PASSWORD', ''),
    database: getDatabaseSetting('CAESAR_DB_NAME', 'caesar_test'),
};
if (!Number.isInteger(DB_CONFIG.port) || DB_CONFIG.port < 1 || DB_CONFIG.port > 65535) {
    throw new Error('CAESAR_DB_PORT must be between 1 and 65535');
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
    handleRequiredAssetError('WorldBarrary.json', e);
}

// 1b. UnitArmy (48 NPC army types for camps, maneuvers, etc.)
let unitArmy = {};
try {
    unitArmy = JSON.parse(fs.readFileSync(path.join(JSON_DIR, 'UnitArmy.json'), 'utf8'));
} catch (e) {
    handleRequiredAssetError('UnitArmy.json', e);
}

// 1c. UnitData (unit titles and properties)
let unitData = {};
try {
    unitData = JSON.parse(fs.readFileSync(path.join(JSON_DIR, 'worldUnitData.json'), 'utf8'));
} catch (e) {
    handleRequiredAssetError('worldUnitData.json', e);
}
normalizeDynamicWorldUnitSeedLevels(worldUnits, unitData);

// 1d. Prize Drop Rules (6,030 loot rules)
let prizeData = { Win: [] };
try {
    prizeData = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'caesar-private', 'data', 'world-unit-prizes.json'), 'utf8'));
} catch (e) {
    handleRequiredAssetError('world-unit-prizes.json', e);
}

// 1e. Equipment Power Stats (330 equipment items with full combat attributes)
const equipPowerMap = new Map();
try {
    const rawEquip = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'caesar-private', 'data', 'equipment-power.json'), 'utf8'));
    for (const eq of rawEquip) {
        equipPowerMap.set(`${eq.equip}_${eq.part}_${eq.lvl}`, eq);
    }
} catch (e) {
    handleRequiredAssetError('equipment-power.json', e);
}

console.log(`[WS-Server] ✅ Assets loaded in ${Date.now() - tStart} ms! (${worldUnits.size} map tiles, ${prizeData.Win.length} drop rules, ${equipPowerMap.size} equipment stats)`);

// ── Soldier stats matching the CAESAR client bundle ────────────────────────────
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
    starter_player_gold: 50,
    starter_protection_hours: 168,
    resource_production_multiplier: 1.0,
    march_speed_multiplier: 1.0,
    building_speed_multiplier: 1.0,
    study_speed_multiplier: 1.0,
    camp_rally_duration_sec: 120,
    city_attack_min_duration_sec: 900,
    wounded_recovery_rate: 0.70,
    maintenance_mode: false,
    maintenance_message: 'السيرفر حالياً في وضع الصيانة الدورية لتحديث الأنظمة. سنعود قريباً!',
    server_truce_active: false,
    live_banner_text: '',
    live_banner_color: '#dfba6b',
    event_multipliers: { luxury: 1, global: 1, speed: 1, truce: false },
    active_events: []
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
        const [events] = await db.query(
            "SELECT id_event, title, event_type, multiplier, time_end FROM server_events " +
            "WHERE is_active = 1 AND time_start <= UNIX_TIMESTAMP() AND time_end >= UNIX_TIMESTAMP() " +
            "AND event_type <> 'equipment_drop_boost'"
        );
        const eventMultipliers = { luxury: 1, global: 1, speed: 1, truce: false };
        for (const event of events) {
            const multiplier = Math.max(1, Math.min(10, Number(event.multiplier) || 1));
            if (event.event_type === 'luxury_drop_boost') eventMultipliers.luxury = Math.min(10, eventMultipliers.luxury * multiplier);
            if (event.event_type === 'global_drop_boost') eventMultipliers.global = Math.min(10, eventMultipliers.global * multiplier);
            if (event.event_type === 'speed_boost') eventMultipliers.speed = Math.min(10, eventMultipliers.speed * multiplier);
            if (event.event_type === 'truce') eventMultipliers.truce = true;
        }
        liveServerConfig.event_multipliers = eventMultipliers;
        liveServerConfig.active_events = events.map(event => ({
            id_event: Number(event.id_event),
            title: String(event.title),
            event_type: String(event.event_type),
            time_end: Number(event.time_end)
        }));
        broadcast(JSON.stringify({
            classPath: 'World.LiveEventsUpdated',
            data: liveServerConfig.active_events
        }));
        console.log(`[LiveOps] ✅ Dynamic configs refreshed from database (${rows.length} keys active).`);
    } catch (e) {
        console.error('[LiveOps Error] Failed to refresh server_config:', e.message);
        throw e;
    }
}

// Initial load and periodic background sync every 60 seconds
let liveServerConfigLoaded = false;
let liveServerConfigRefreshPromise = null;

function refreshLiveServerConfig() {
    if (liveServerConfigRefreshPromise) return liveServerConfigRefreshPromise;

    const refreshPromise = reloadLiveServerConfig()
        .then(() => {
            liveServerConfigLoaded = true;
        })
        .finally(() => {
            if (liveServerConfigRefreshPromise === refreshPromise) {
                liveServerConfigRefreshPromise = null;
            }
        });
    liveServerConfigRefreshPromise = refreshPromise;
    refreshPromise.catch(() => {});
    return refreshPromise;
}

async function ensureLiveServerConfig() {
    if (liveServerConfigLoaded) return;
    await refreshLiveServerConfig();
}

refreshLiveServerConfig();
setInterval(() => {
    refreshLiveServerConfig().catch(() => {});
}, 60000);

async function creditCityResources(db, cityId, requested) {
    const resources = Object.entries(requested);
    for (const [resource, amount] of resources) {
        if (!['food', 'wood', 'stone', 'metal', 'coin'].includes(resource)
            || !Number.isSafeInteger(amount) || amount < 0
            || amount > MAX_CITY_RESOURCE_BALANCE) {
            throw new Error('Invalid city resource credit');
        }
    }
    const defaultResources = { food: 0, wood: 0, stone: 0, metal: 0, coin: 0 };
    const credits = resources.filter(([, amount]) => amount > 0);
    if (credits.length === 0) return { ...defaultResources };

    const ownsConnection = typeof db.getConnection === 'function';
    const connection = ownsConnection ? await db.getConnection() : db;
    try {
        if (ownsConnection) await connection.beginTransaction();
        const columns = credits.map(([resource]) => `\`${resource}\``).join(', ');
        const [rows] = await connection.query(
            `SELECT ${columns} FROM city WHERE id_city = ? FOR UPDATE`,
            [cityId]
        );
        if (!rows.length) throw new Error('Resource recipient city not found');

        const credited = { ...defaultResources };
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
        if (ownsConnection) await connection.commit();
        return credited;
    } catch (error) {
        if (ownsConnection) await connection.rollback();
        throw error;
    } finally {
        if (ownsConnection) connection.release();
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

function sendChatFailure(ws, reason) {
    if (ws.readyState === 1) {
        ws.send(JSON.stringify({
            classPath: 'Chat.SendFailed',
            data: { reason }
        }));
    }
}

function makeChatMessage(player, chatMsg) {
    return {
        idFrom: Number(player.id_player),
        idMsg: Date.now(),
        userGroup: Number(player.user_group) || 0,
        playerAvatar: Number(player.avatar) || 1,
        playerFromAvatar: Number(player.avatar) || 1,
        p_avatar: Number(player.avatar) || 1,
        playerTitle: {},
        fromName: String(player.name || ''),
        chatMsg,
        time: Math.floor(Date.now() / 1000)
    };
}

async function hasActiveBattleAt(db, xCoord, yCoord) {
    const [rows] = await db.query(
        "SELECT 1 FROM world_battel WHERE x_coord = ? AND y_coord = ? " +
        "AND state = 'marching' LIMIT 1",
        [xCoord, yCoord]
    );
    return rows.length > 0;
}

function reportArmySlots(army, remainingArmy, defenderTroops, isVictory, injuredBySlot = {}) {
    const slotNames = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
    const attackerSlots = {};
    for (const slot of slotNames) {
        const type = Number(army[`${slot}_type`]) || 0;
        const pre = Number(army[`${slot}_num`]) || 0;
        const cur = Number(remainingArmy[slot]) || 0;
        attackerSlots[slot] = {
            type,
            pre,
            cur,
            injured: Math.min(
                Math.max(0, pre - cur),
                Math.max(0, Number(injuredBySlot[slot]) || 0)
            )
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
                : isVictory ? 0 : amount,
            injured: Math.min(
                amount,
                Math.max(0, Number(troopCount && typeof troopCount === 'object' ? troopCount.injured : 0) || 0)
            )
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
            if (!troops[type]) troops[type] = { pre: 0, cur: 0, injured: 0 };
            troops[type].pre += amount;
            troops[type].cur += current;
            troops[type].injured += Math.max(
                0,
                Number(member.casualties && member.casualties.woundedBySlot
                    && member.casualties.woundedBySlot[slot]) || 0
            );
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
        heroSide = 1, opponentName = 'حامية الأعداء', battleStartTime, returnDuration,
        roundCount = 0, roundsLog = [], killsByType = {}
    } = details;
    const resolvedAt = Math.floor(Date.now() / 1000);
    const asReportHero = (slots, side, name, avatar, idPlayer, xp, kills = {}) => {
        const reportHero = { side, h_name: name, avatar, id_player: idPlayer, xp, kills };
        for (const slot of ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3']) {
            reportHero[`${slot}_type`] = String(slots[slot].type);
            reportHero[`${slot}_pre`] = String(slots[slot].pre);
            reportHero[`${slot}_post`] = String(slots[slot].cur);
            reportHero[`${slot}_loss`] = String(Math.max(0, slots[slot].pre - slots[slot].cur));
            if (Number(idPlayer) === Number(player.id_player)) {
                reportHero[`${slot}_injured`] = String(Number(slots[slot].injured) || 0);
            }
        }
        return reportHero;
    };
    const reportDetail = {
        encId: '0',
        general_data: {
            p_name: player.name,
            task: attackTask,
            lvl: unitLvl,
            round_num: roundCount,
            side_win: isVictory ? 1 : 0
        },
        heros: [
            asReportHero(attackerSlots, heroSide, hero.name || 'البطل سيزر', Number(hero.avatar) || 1, Number(player.id_player), expGained, killsByType),
            asReportHero(defenderSlots, 1 - heroSide, opponentName, 2, 0, 0)
        ],
        prize: {
            resource: resourceLoot,
            matrial: itemLoot,
            honor: honorGained
        },
        rounds: roundsLog,
        rounds_log: roundsLog
    };

    // Build Cocos2d-html5 Battle Replay protocol
    const slotToBlock = { f_1: 0, f_2: 1, f_3: 2, b_1: 3, b_2: 4, b_3: 5 };
    const atkHeroId = Number(hero && hero.id_hero) || 1;
    const defHeroId = 200000 + (Number(unitType) || 1) * 100 + (Number(unitLvl) || 1);

    const atkTypes = {};
    const atkPre = {};
    const atkPost = {};
    const defTypes = {};
    const defPre = {};
    const defPost = {};

    for (const [slotKey, blockIdx] of Object.entries(slotToBlock)) {
        atkTypes[blockIdx] = Number(attackerSlots[slotKey] && attackerSlots[slotKey].type) || 0;
        atkPre[blockIdx] = Number(attackerSlots[slotKey] && attackerSlots[slotKey].pre) || 0;
        atkPost[blockIdx] = Number(attackerSlots[slotKey] && attackerSlots[slotKey].cur) || 0;

        defTypes[blockIdx] = Number(defenderSlots[slotKey] && defenderSlots[slotKey].type) || 0;
        defPre[blockIdx] = Number(defenderSlots[slotKey] && defenderSlots[slotKey].pre) || 0;
        defPost[blockIdx] = Number(defenderSlots[slotKey] && defenderSlots[slotKey].cur) || 0;
    }

    const effectiveRounds = Math.max(1, (roundsLog && roundsLog.length) || roundCount || 1);
    const roundList = [];
    const curAtk = {};
    const curDef = {};
    for (const [slotKey] of Object.entries(slotToBlock)) {
        const attackerSlot = attackerSlots[slotKey] || { type: 0, pre: 0, cur: 0 };
        const defenderSlot = defenderSlots[slotKey] || { type: 0, pre: 0, cur: 0 };
        curAtk[slotKey] = { ...attackerSlot, cur: Number(attackerSlot.pre) || 0 };
        curDef[slotKey] = { ...defenderSlot, cur: Number(defenderSlot.pre) || 0 };
    }

    for (let r = 1; r <= effectiveRounds; r++) {
        const roundAtkBlocks = {};
        const roundDefBlocks = {};
        let roundAtkLosses = 0;
        let roundDefLosses = 0;

        for (const [slotKey, blockIdx] of Object.entries(slotToBlock)) {
            const totDeadAtk = Math.max(0, (attackerSlots[slotKey]?.pre || 0) - (attackerSlots[slotKey]?.cur || 0));
            const deadAtk = (r === effectiveRounds)
                ? Math.max(0, (curAtk[slotKey]?.cur || 0) - (attackerSlots[slotKey]?.cur || 0))
                : Math.round(totDeadAtk / effectiveRounds);
            const actualDeadAtk = Math.max(0, Math.min(curAtk[slotKey]?.cur || 0, deadAtk));
            roundAtkBlocks[blockIdx] = {
                unit: Number(curAtk[slotKey]?.cur) || 0,
                dead: actualDeadAtk,
                armyType: Number(attackerSlots[slotKey]?.type) || 0
            };
            curAtk[slotKey].cur = (curAtk[slotKey].cur || 0) - actualDeadAtk;
            roundAtkLosses += actualDeadAtk;

            const totDeadDef = Math.max(0, (defenderSlots[slotKey]?.pre || 0) - (defenderSlots[slotKey]?.cur || 0));
            const deadDef = (r === effectiveRounds)
                ? Math.max(0, (curDef[slotKey]?.cur || 0) - (defenderSlots[slotKey]?.cur || 0))
                : Math.round(totDeadDef / effectiveRounds);
            const actualDeadDef = Math.max(0, Math.min(curDef[slotKey]?.cur || 0, deadDef));
            roundDefBlocks[blockIdx] = {
                unit: Number(curDef[slotKey]?.cur) || 0,
                dead: actualDeadDef,
                armyType: Number(defenderSlots[slotKey]?.type) || 0
            };
            curDef[slotKey].cur = (curDef[slotKey].cur || 0) - actualDeadDef;
            roundDefLosses += actualDeadDef;
        }

        let attCellIdx = 0;
        for (const [, blockIdx] of Object.entries(slotToBlock)) {
            if (roundAtkBlocks[blockIdx].unit > 0) { attCellIdx = blockIdx; break; }
        }
        let defCellIdx = 0;
        for (const [, blockIdx] of Object.entries(slotToBlock)) {
            if (roundDefBlocks[blockIdx].unit > 0) { defCellIdx = blockIdx; break; }
        }

        const attacks = [];
        if (roundDefLosses > 0) {
            attacks.push({
                HeroAttIndex: 3,
                CellAttIndex: attCellIdx,
                HeroDefIndex: 0,
                CellDefIndex: defCellIdx,
                AttackType: 0,
                totalDead: roundDefLosses,
                hitSkillType: 1
            });
        }
        if (roundAtkLosses > 0) {
            attacks.push({
                HeroAttIndex: 0,
                CellAttIndex: defCellIdx,
                HeroDefIndex: 3,
                CellDefIndex: attCellIdx,
                AttackType: 0,
                totalDead: roundAtkLosses,
                hitSkillType: 1
            });
        }

        roundList.push({
            Heros: {
                '0': {
                    idHero: defHeroId,
                    HeroIndex: 0,
                    side: 0,
                    ArmyBlocks: roundDefBlocks
                },
                '3': {
                    idHero: atkHeroId,
                    HeroIndex: 3,
                    side: 1,
                    ArmyBlocks: roundAtkBlocks
                }
            },
            Attacks: attacks
        });
    }

    reportDetail.round_list = roundList;
    reportDetail.hero_list = [
        {
            Hero: {
                id_hero: atkHeroId,
                id_city: Number(hero?.id_city) || 0,
                id_player: Number(player.id_player),
                name: hero.name || 'البطل سيزر',
                lvl: Number(hero.lvl) || 1,
                avatar: Number(hero.avatar) || 1,
                side: 1
            },
            City: {},
            type: atkTypes,
            pre: atkPre,
            post: atkPost
        },
        {
            Hero: {
                id_hero: defHeroId,
                id_city: 0,
                id_player: 0,
                name: opponentName,
                lvl: Number(unitLvl) || 1,
                avatar: 2,
                side: 0
            },
            City: {},
            type: defTypes,
            pre: defPre,
            post: defPost
        }
    ];
    reportDetail.player_list = {
        [String(player.id_player)]: {
            ItemPrize: Array.isArray(itemLoot) ? itemLoot : [],
            Player: {
                id_player: Number(player.id_player),
                name: player.name || 'اللاعب',
                avatar: Number(player.avatar) || 1
            },
            ResourcePrize: {
                food: Number(resourceLoot && resourceLoot.food) || 0,
                wood: Number(resourceLoot && resourceLoot.wood) || 0,
                stone: Number(resourceLoot && resourceLoot.stone) || 0,
                metal: Number(resourceLoot && resourceLoot.metal) || 0,
                coin: Number(resourceLoot && resourceLoot.coin) || 0
            }
        },
        '0': {
            ItemPrize: [],
            Player: {
                id_player: 0,
                name: opponentName,
                avatar: 2
            },
            ResourcePrize: {
                food: 0, wood: 0, stone: 0, metal: 0, coin: 0
            }
        }
    };
    reportDetail.battle_data = {
        id_battle: Number(battleId) || 0,
        id_hero: atkHeroId,
        id_player: Number(player.id_player),
        id_player_def: 0,
        time_start: Number(battleStartTime) || resolvedAt,
        time_end: resolvedAt,
        time_back: resolvedAt + (Number(returnDuration) || 0),
        return_duration_sec: Number(returnDuration) || 0,
        battle_duration_sec: Math.max(0, resolvedAt - (Number(details.battleArrivalTime) || resolvedAt)),
        expedition_duration_sec: Math.max(0, resolvedAt - (Number(battleStartTime) || resolvedAt)),
        x_coord: Number(xCoord) || 150,
        y_coord: Number(yCoord) || 200,
        x_city: Number(xCoord) || 150,
        y_city: Number(yCoord) || 200,
        task: Number(attackTask) || 0,
        done: 0,
        attackNum: 1,
        defenceNum: 0,
        unit_lvl: Number(unitLvl) || 1,
        sideWin: isVictory ? 1 : 2,
        rounds: effectiveRounds
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

            const [garrisonRows] = await connection.query(
                "SELECT g.id_city, g.status, h.id_city AS home_city_id FROM city_garrison g " +
                "JOIN city_hero h ON h.id_hero = g.id_hero " +
                "WHERE g.id_hero = ? FOR UPDATE",
                [battle.id_hero]
            );
            if (garrisonRows.length) {
                if (garrisonRows[0].status === 'returning') continue;
                await connection.query(
                    "UPDATE city_garrison SET status = 'stationed', battle_id = NULL, return_at = 0 " +
                    "WHERE id_hero = ?",
                    [battle.id_hero]
                );
                if (Number(garrisonRows[0].id_city) !== Number(garrisonRows[0].home_city_id)) {
                    continue;
                }
            }

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

let processingCityGarrisonArrivals = false;
async function processDueCityGarrisonArrivals() {
    if (processingCityGarrisonArrivals) return;
    processingCityGarrisonArrivals = true;
    let connection;
    const returnedHeroes = [];
    try {
        const db = await getDb();
        connection = await db.getConnection();
        await connection.beginTransaction();
        const [dueGarrisons] = await connection.query(
            "SELECT g.id_hero, g.id_player, g.id_city, g.status, h.id_city AS home_city_id, " +
            "home.x AS home_x, home.y AS home_y " +
            "FROM city_garrison g JOIN city_hero h ON h.id_hero = g.id_hero " +
            "JOIN city home ON home.id_city = h.id_city " +
            "WHERE (g.status = 'marching' AND g.time_arrive <= UNIX_TIMESTAMP()) " +
            "OR (g.status = 'returning' AND g.return_at <= UNIX_TIMESTAMP()) " +
            "ORDER BY CASE WHEN g.status = 'marching' THEN g.time_arrive ELSE g.return_at END " +
            "LIMIT 100 FOR UPDATE"
        );
        for (const garrison of dueGarrisons) {
            if (garrison.status === 'marching') {
                await connection.query(
                    "UPDATE city_garrison SET status = 'stationed' " +
                    "WHERE id_hero = ? AND status = 'marching' AND time_arrive <= UNIX_TIMESTAMP()",
                    [garrison.id_hero]
                );
                continue;
            }

            await connection.query(
                "UPDATE city_hero SET in_city = 1 " +
                "WHERE id_hero = ? AND id_player = ? AND in_city = 0",
                [garrison.id_hero, garrison.id_player]
            );
            await connection.query(
                "DELETE FROM city_garrison WHERE id_hero = ? AND status = 'returning' " +
                "AND return_at <= UNIX_TIMESTAMP()",
                [garrison.id_hero]
            );
            returnedHeroes.push(garrison);
        }
        await connection.commit();
        for (const hero of returnedHeroes) {
            sendToPlayer(Number(hero.id_player), JSON.stringify({
                classPath: 'Hero.Back',
                idHero: Number(hero.id_hero),
                inCity: 1,
                Task: 5,
                task: 5,
                xTo: Number(hero.home_x),
                yTo: Number(hero.home_y)
            }));
        }
    } catch (error) {
        if (connection) {
            try {
                await connection.rollback();
            } catch (rollbackError) {
                console.error('[WS Garrison Return Rollback Error]:', rollbackError.message);
            }
        }
        console.error('[WS Garrison Arrival Error]:', error.message);
    } finally {
        if (connection) connection.release();
        processingCityGarrisonArrivals = false;
    }
}

let processingWorldUnitResets = false;
async function restorePersistedWorldUnitLevels() {
    const db = await getDb();
    const [states] = await db.query(
        'SELECT x_coord, y_coord, unit_type, unit_subtype, base_lvl, current_lvl, max_lvl, next_reset_at ' +
        'FROM world_unit_state'
    );
    const now = Math.floor(Date.now() / 1000);
    for (const state of states) {
        const mapUnit = worldUnits.get(`${state.x_coord}_${state.y_coord}`);
        const interval = getResetIntervalHours(state.unit_subtype, liveServerConfig);
        if (mapUnit && Number(mapUnit.ut) === Number(state.unit_type)
            && Number(mapUnit.t) === Number(state.unit_subtype)
            && interval && Number(state.next_reset_at) === 0) {
            const nextResetAt = getNextResetAt(state.unit_subtype, now, liveServerConfig);
            await db.query(
                'UPDATE world_unit_state SET next_reset_at = ? ' +
                'WHERE x_coord = ? AND y_coord = ? AND unit_type = ? AND unit_subtype = ? AND next_reset_at = 0',
                [nextResetAt, state.x_coord, state.y_coord, state.unit_type, state.unit_subtype]
            );
            state.next_reset_at = nextResetAt;
        }
    }
    applyPersistedWorldUnitLevels(worldUnits, states, now);
}

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
            const nextResetAt = getNextResetAt(unit.unit_subtype, now, liveServerConfig);
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

async function resetAllPersistedWorldUnitsNow() {
    if (processingWorldUnitResets) {
        throw new Error('World-unit reset is already running');
    }
    processingWorldUnitResets = true;
    let connection;
    try {
        connection = await (await getDb()).getConnection();
        await connection.beginTransaction();
        const [states] = await connection.query(
            'SELECT x_coord, y_coord, unit_type, unit_subtype, base_lvl FROM world_unit_state FOR UPDATE'
        );
        const now = Math.floor(Date.now() / 1000);
        const changed = [];
        for (const state of states) {
            const interval = getResetIntervalHours(state.unit_subtype, liveServerConfig);
            if (!interval) continue;
            const nextResetAt = getNextResetAt(state.unit_subtype, now, liveServerConfig);
            await connection.query(
                'UPDATE world_unit_state SET current_lvl = base_lvl, next_reset_at = ? ' +
                'WHERE x_coord = ? AND y_coord = ? AND unit_type = ? AND unit_subtype = ?',
                [nextResetAt, state.x_coord, state.y_coord, state.unit_type, state.unit_subtype]
            );
            changed.push(state);
        }
        await connection.commit();
        for (const state of changed) {
            const mapUnit = worldUnits.get(`${state.x_coord}_${state.y_coord}`);
            if (!mapUnit || Number(mapUnit.ut) !== Number(state.unit_type)
                || Number(mapUnit.t) !== Number(state.unit_subtype)) continue;
            mapUnit.l = Number(state.base_lvl);
        }
        for (let index = 0; index < changed.length; index += 500) {
            const batch = changed.slice(index, index + 500).filter(state => {
                const unit = worldUnits.get(`${state.x_coord}_${state.y_coord}`);
                return unit && Number(unit.ut) === Number(state.unit_type)
                    && Number(unit.t) === Number(state.unit_subtype);
            });
            if (!batch.length) continue;
            broadcast(JSON.stringify({
                classPath: 'World.RefereshWorldUnit',
                data: {
                    WorldUnits: batch.map(state => ({
                        x: Number(state.x_coord),
                        y: Number(state.y_coord),
                        t: Number(state.unit_subtype),
                        ut: Number(state.unit_type),
                        l: Number(state.base_lvl)
                    }))
                }
            }));
        }
        return changed.length;
    } catch (error) {
        if (connection) await connection.rollback();
        throw error;
    } finally {
        if (connection) connection.release();
        processingWorldUnitResets = false;
    }
}

async function reschedulePersistedWorldUnitResets() {
    if (processingWorldUnitResets) throw new Error('World-unit reset is already running');
    processingWorldUnitResets = true;
    let connection;
    const resetUnits = [];
    try {
        connection = await (await getDb()).getConnection();
        await connection.beginTransaction();
        const [states] = await connection.query(
            'SELECT x_coord, y_coord, unit_type, unit_subtype, base_lvl, next_reset_at ' +
            'FROM world_unit_state FOR UPDATE'
        );
        const now = Math.floor(Date.now() / 1000);
        for (const state of states) {
            if (!getResetIntervalHours(state.unit_subtype, liveServerConfig)) continue;
            const wasDue = Number(state.next_reset_at) > 0 && Number(state.next_reset_at) <= now;
            const nextResetAt = getNextResetAt(state.unit_subtype, now, liveServerConfig);
            if (wasDue) {
                await connection.query(
                    'UPDATE world_unit_state SET current_lvl = base_lvl, next_reset_at = ? ' +
                    'WHERE x_coord = ? AND y_coord = ? AND unit_type = ? AND unit_subtype = ?',
                    [nextResetAt, state.x_coord, state.y_coord, state.unit_type, state.unit_subtype]
                );
            } else {
                await connection.query(
                    'UPDATE world_unit_state SET next_reset_at = ? ' +
                    'WHERE x_coord = ? AND y_coord = ? AND unit_type = ? AND unit_subtype = ?',
                    [nextResetAt, state.x_coord, state.y_coord, state.unit_type, state.unit_subtype]
                );
            }
            if (wasDue) resetUnits.push(state);
        }
        await connection.commit();
        for (const state of resetUnits) {
            const mapUnit = worldUnits.get(`${state.x_coord}_${state.y_coord}`);
            if (!mapUnit || Number(mapUnit.ut) !== Number(state.unit_type)
                || Number(mapUnit.t) !== Number(state.unit_subtype)) continue;
            mapUnit.l = Number(state.base_lvl);
        }
        for (let index = 0; index < resetUnits.length; index += 500) {
            const batch = resetUnits.slice(index, index + 500).filter(state => {
                const unit = worldUnits.get(`${state.x_coord}_${state.y_coord}`);
                return unit && Number(unit.ut) === Number(state.unit_type)
                    && Number(unit.t) === Number(state.unit_subtype);
            });
            if (!batch.length) continue;
            broadcast(JSON.stringify({
                classPath: 'World.RefereshWorldUnit',
                data: {
                    WorldUnits: batch.map(state => ({
                        x: Number(state.x_coord),
                        y: Number(state.y_coord),
                        t: Number(state.unit_subtype),
                        ut: Number(state.unit_type),
                        l: Number(state.base_lvl)
                    }))
                }
            }));
        }
    } catch (error) {
        if (connection) await connection.rollback();
        throw error;
    } finally {
        if (connection) connection.release();
        processingWorldUnitResets = false;
    }
}

async function advanceWorldUnitLevel(db, { xCoord, yCoord, unitType, unitSubtype, baseLevel, maxLevel }) {
    const ownsConnection = typeof db.getConnection === 'function';
    const connection = ownsConnection ? await db.getConnection() : db;
    let updatedLevel;
    try {
        if (ownsConnection) await connection.beginTransaction();
        const now = Math.floor(Date.now() / 1000);
        const interval = getResetIntervalHours(unitSubtype, liveServerConfig);
        const initialNextResetAt = interval ? getNextResetAt(unitSubtype, now, liveServerConfig) : 0;
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
        if (ownsConnection) await connection.commit();
    } catch (error) {
        if (ownsConnection) await connection.rollback();
        throw error;
    } finally {
        if (ownsConnection) connection.release();
    }

    if (!ownsConnection) return updatedLevel;
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

let processingBattleTasks = false;
let battleTasksReady = false;
let recoveringBattleTasks = false;

async function getBattleResolutionContext(task) {
    let details;
    try {
        details = JSON.parse(task.details || '{}');
    } catch (error) {
        throw new Error(`Battle task ${task.id_task} has invalid details: ${error.message}`);
    }

    const battelId = Number(task.item_id);
    if (!Number.isSafeInteger(battelId) || battelId <= 0) {
        throw new Error(`Battle task ${task.id_task} has an invalid battle ID`);
    }
    const db = await getDb();
    const [rows] = await db.query(
        'SELECT b.id_battel, b.id_player, b.id_city, b.id_hero, b.task, b.x_city, b.y_city, b.time_start, b.time_arrive, ' +
        'b.x_coord, b.y_coord, b.return_duration, b.state, h.name AS hero_name, h.avatar AS hero_avatar, ' +
        'p.name AS player_name, p.id_guild, p.city_flag, p.prestige AS player_prestige, ' +
        'c.name AS city_name, c.x AS city_x, c.y AS city_y ' +
        'FROM world_battel b ' +
        'JOIN player p ON p.id_player = b.id_player ' +
        'JOIN city c ON c.id_city = b.id_city ' +
        'JOIN city_hero h ON h.id_hero = b.id_hero AND h.id_player = b.id_player ' +
        'WHERE b.id_battel = ? LIMIT 1',
        [battelId]
    );
    if (!rows.length || rows[0].state !== 'marching') return null;

    const battle = rows[0];
    const unitType = Number(details.unit_type);
    const unitSubtype = Number(details.unit_subtype) || 0;
    const unitLvl = Number(task.lvl_to);
    if (!Number.isSafeInteger(unitType) || unitType <= 0
        || !Number.isSafeInteger(unitSubtype) || unitSubtype < 0
        || !Number.isSafeInteger(unitLvl) || unitLvl <= 0) {
        throw new Error(`Battle task ${task.id_task} is missing its target type or level`);
    }
    const [targetCityRows] = await db.query(
        'SELECT id_city, id_player, name, wall_a, wall_b, wall_c FROM city WHERE x = ? AND y = ? LIMIT 1',
        [battle.x_coord, battle.y_coord]
    );
    const unitTypeData = unitData[unitType] || {};
    return {
        battelId,
        idHero: Number(battle.id_hero),
        battleStartTime: Number(battle.time_start) || 0,
        battleArrivalTime: Number(battle.time_arrive) || 0,
        returnDuration: Number(battle.return_duration) || 1,
        unitType,
        unitSubtype,
        unitLvl,
        unitTitle: unitType === 60 && targetCityRows.length
            ? `مدينة [${targetCityRows[0].name}]`
            : unitTypeData.Title || 'برية',
        attackTask: Number(battle.task),
        xCoord: Number(battle.x_coord),
        yCoord: Number(battle.y_coord),
        targetCityRows,
        isBarray: Boolean(details.is_barray),
        unitTypeData,
        baseUnit: worldUnits.get(`${battle.x_coord}_${battle.y_coord}`),
        hero: { id_hero: Number(battle.id_hero), name: battle.hero_name, avatar: Number(battle.hero_avatar) || 1 },
        player: {
            id_player: Number(battle.id_player),
            name: battle.player_name,
            id_guild: Number(battle.id_guild) || 0,
            city_flag: battle.city_flag,
            prestige: Number(battle.player_prestige) || 0
        },
        city: {
            id_city: Number(battle.id_city),
            name: battle.city_name,
            x: Number(battle.city_x),
            y: Number(battle.city_y)
        }
    };
}

async function processDueBattleResolutionTasks() {
    if (!battleTasksReady || processingBattleTasks) return;
    processingBattleTasks = true;
    let connection;
    try {
        const db = await getDb();
        connection = await db.getConnection();
        await connection.beginTransaction();
        const now = Math.floor(Date.now() / 1000);
        const [tasks] = await connection.query(
            "SELECT id_task, id_city, id_player, item_id, lvl_to, details FROM timed_task " +
            "WHERE task_type = 'battle' AND time_arrive <= ? ORDER BY time_arrive, id_task " +
            "LIMIT 10 FOR UPDATE",
            [now]
        );
        for (const task of tasks) {
            await connection.query(
                "UPDATE timed_task SET task_type = 'battle_processing' WHERE id_task = ? AND task_type = 'battle'",
                [task.id_task]
            );
        }
        await connection.commit();
        connection.release();
        connection = null;

        for (const task of tasks) {
            try {
                const context = await getBattleResolutionContext(task);
                if (context) {
                    await resolveBattle(context, {
                        getDb,
                        worldUnits,
                        unitData,
                        unitArmy,
                        prizeData,
                        equipPowerMap,
                        liveServerConfig,
                        SOL_STATS,
                        slots: ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'],
                        aggregateMemberArmy,
                        reportArmySlots,
                        creditCityResources,
                        advanceWorldUnitLevel,
                        allocateProportionally,
                        calculateArmyPower,
                        calculateArmyCapacity,
                        calculateKillBasedRewards,
                        distributeMemberTroopLosses,
                        didBattleSideWin,
                        calculateArmyCasualties,
                        simulateTacticalCombat,
                        hasActiveBattleAt,
                        publishBroadcast: broadcast,
                        publishToPlayer: sendToPlayer,
                        saveWsBattleReport
                    });
                }
                await db.query(
                    "DELETE FROM timed_task WHERE id_task = ? AND task_type = 'battle_processing'",
                    [task.id_task]
                );
            } catch (error) {
                console.error(`[Battle Resolution Error] Task ${task.id_task}:`, error.message);
                await db.query(
                    "UPDATE timed_task SET task_type = 'battle' WHERE id_task = ? AND task_type = 'battle_processing'",
                    [task.id_task]
                );
            }
        }
    } catch (error) {
        if (connection) {
            try {
                await connection.rollback();
            } finally {
                connection.release();
            }
        }
        console.error('[Battle Task Worker Error]:', error.message);
    } finally {
        processingBattleTasks = false;
    }
}

async function recoverPendingBattleTasks() {
    const db = await getDb();
    await db.query(
        "UPDATE timed_task SET task_type = 'battle' WHERE task_type = 'battle_processing'"
    );
    const [battles] = await db.query(
        "SELECT b.id_battel, b.id_city, b.id_player, b.x_coord, b.y_coord, b.time_start, b.time_arrive " +
        "FROM world_battel b LEFT JOIN timed_task t ON t.task_type IN ('battle', 'battle_processing') " +
        "AND CAST(t.item_id AS UNSIGNED) = b.id_battel " +
        "WHERE b.state = 'marching' AND t.id_task IS NULL ORDER BY b.id_battel LIMIT 500"
    );
    for (const battle of battles) {
        const mapUnit = worldUnits.get(`${battle.x_coord}_${battle.y_coord}`);
        const [cityRows] = await db.query(
            'SELECT id_city FROM city WHERE x = ? AND y = ? LIMIT 1',
            [battle.x_coord, battle.y_coord]
        );
        const [barrayRows] = await db.query(
            'SELECT Type, lvl FROM city_barray WHERE x_coord = ? AND y_coord = ? LIMIT 1',
            [battle.x_coord, battle.y_coord]
        );
        const unitType = cityRows.length ? 60 : Number(mapUnit && mapUnit.ut) || 0;
        const unitLvl = cityRows.length
            ? 1
            : Math.max(1, Number(barrayRows[0] && barrayRows[0].lvl) || Number(mapUnit && mapUnit.l) || 1);
        if (!unitType) {
            console.error(`[Battle Recovery Error] Cannot identify target for battle ${battle.id_battel}`);
            continue;
        }
        const isBarray = barrayRows.length > 0;
        await db.query(
            "INSERT INTO timed_task (id_city, id_player, task_type, item_id, lvl_to, start_time, time_arrive, details) " +
            "VALUES (?, ?, 'battle', ?, ?, ?, ?, ?)",
            [
                battle.id_city,
                battle.id_player,
                String(battle.id_battel),
                unitLvl,
                battle.time_start,
                battle.time_arrive,
                JSON.stringify({
                    unit_type: isBarray ? Number(barrayRows[0].Type) : unitType,
                    unit_subtype: Number(mapUnit && mapUnit.t) || 0,
                    is_barray: isBarray
                })
            ]
        );
    }
    battleTasksReady = true;
    console.log(`[Battle Recovery] Ready; restored ${battles.length} pending battle task(s).`);
}

async function initializeBattleTaskWorker() {
    if (battleTasksReady || recoveringBattleTasks) return;
    recoveringBattleTasks = true;
    try {
        await ensureLiveServerConfig();
        await reschedulePersistedWorldUnitResets();
        await restorePersistedWorldUnitLevels();
        await recoverPendingBattleTasks();
        await processDueBattleResolutionTasks();
    } catch (error) {
        console.error('[Battle Recovery Error]:', error.message);
    } finally {
        recoveringBattleTasks = false;
    }
}

function setCors(res) {
    res.setHeader('Access-Control-Allow-Origin' , '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
}

function requireInternalApiAccess(req, res) {
    if (req.method !== 'POST') {
        res.writeHead(405, { 'Allow': 'POST', 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify({ state: 'error', msg: 'Method not allowed' }));
        return false;
    }

    if ((!IS_PRODUCTION && !INTERNAL_API_TOKEN)
        || isAuthorizedInternalRequest(req, INTERNAL_API_TOKEN)) {
        return true;
    }

    res.writeHead(403, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify({ state: 'error', msg: 'Forbidden' }));
    return false;
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

    if (uriPath.startsWith('/api/internal/') && !requireInternalApiAccess(req, res)) {
        return;
    }

    // 0. LiveOps Internal Endpoints (From Admin Panel)
    if (uriPath === '/api/internal/reload_config') {
        try {
            await reloadLiveServerConfig();
            await reschedulePersistedWorldUnitResets();
        } catch (error) {
            console.error('[LiveOps Error] Config reload request failed:', error.message);
            res.writeHead(500, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ state: 'error', msg: 'Config reload failed' }));
            return;
        }
        if (liveServerConfig.maintenance_mode) {
            for (const playerClients of clients.values()) {
                for (const client of playerClients) {
                    if (client.readyState === 1) client.close(1012, 'Server maintenance');
                }
            }
        }
        broadcast(JSON.stringify({
            classPath: 'World.LiveConfigUpdated',
            data: {
                camp_reset_interval_hours: Number(liveServerConfig.camp_reset_interval_hours) || 12,
                gang_reset_interval_hours: Number(liveServerConfig.gang_reset_interval_hours) || 6,
                squad_reset_interval_hours: Number(liveServerConfig.squad_reset_interval_hours) || 4
            }
        }));
        res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify({ state: 'ok', msg: 'Config reloaded' }));
        return;
    }
    if (uriPath === '/api/internal/reset_camps') {
        try {
            const resetCount = await resetAllPersistedWorldUnitsNow();
            broadcast(JSON.stringify({
                classPath: 'Chat.WorldMsg',
                data: {
                    id_player: 0,
                    name: 'القيادة العسكرية',
                    msg: `🚩 تم تصفير مستوى ${resetCount} من وحدات الخريطة وإعادة فتحها.`,
                    time: Math.floor(Date.now() / 1000)
                }
            }));
            res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ state: 'ok', reset_count: resetCount }));
        } catch (error) {
            console.error('[Manual World Reset Error]:', error.message);
            res.writeHead(500, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ state: 'error', msg: 'World reset failed' }));
        }
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
        if (req.method !== 'POST') {
            res.writeHead(405, {
                'Allow': 'POST',
                'Content-Type': 'application/json; charset=utf-8'
            });
            res.end(JSON.stringify({ state: 'error', msg: 'Method not allowed' }));
            return;
        }

        let bodyChunks = [];
        let bodyBytes = 0;
        let bodyTooLarge = false;
        try {
            for await (const chunk of req) {
                bodyBytes += chunk.length;
                if (bodyBytes > 8 * 1024) {
                    bodyTooLarge = true;
                    bodyChunks = [];
                    continue;
                }
                if (!bodyTooLarge) bodyChunks.push(chunk);
            }
        } catch (e) {
            console.error('[World City Request Error]:', e.message);
            if (!res.destroyed && !res.headersSent) {
                res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
                res.end(JSON.stringify({ state: 'error', msg: 'Invalid request body' }));
            }
            return;
        }
        if (bodyTooLarge) {
            res.writeHead(413, { 'Content-Type': 'application/json; charset=utf-8' });
            res.end(JSON.stringify({ state: 'error', msg: 'Request body too large' }));
            return;
        }

        const formData = new URLSearchParams(Buffer.concat(bodyChunks).toString('utf8'));
        try {
            const db = await getDb();
            if (!await authenticateSocketPlayer(
                db,
                formData.get('idPlayer'),
                formData.get('token')
            )) {
                res.writeHead(401, { 'Content-Type': 'text/plain; charset=utf-8' });
                res.end(JSON.stringify({ state: 'error_auth' }));
                return;
            }

            const [rows] = await db.query(
                "SELECT c.id_city AS ic, c.id_player AS ip, c.x, c.y, c.lvl AS l, " +
                "CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag ELSE '0' END AS f, " +
                "CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag_expires_at ELSE 0 END AS fe, " +
                "CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag_tag ELSE '' END AS ft, " +
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
                fe: Number(r.fe) || 0,
                ft: String(r.ft || "")
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

    if (uriPath === '/api/AGodGate/getRankEffect') {
        return proxyToApi(req, res);
    }

    // 2. Exact file from caesar-ws (strip leading /api/ so it maps directly into API_DIR)
    const relApiPath = uriPath.startsWith('/api/') ? uriPath.slice(5) : uriPath.replace(/^\/+/, '');
    let filePath = path.join(API_DIR, relApiPath);
    if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
        return serveFile(filePath, res);
    }

    // 3. File with .html appended
    filePath = path.join(API_DIR, relApiPath + '.html');
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
function handleWs(ws) {
    let idPlayer = null;
    let token = null;
    let authenticated = false;
    let authenticating = false;
    const authTimeout = setTimeout(() => {
        if (!authenticated && ws.readyState === 1) {
            ws.close(1008, 'Authentication timeout');
        }
    }, 10000);
    authTimeout.unref();

    ws.on('message', async (raw) => {
        try {
            const msg = JSON.parse(raw.toString());
            if (!authenticated) {
                if (
                    authenticating ||
                    msg.url !== 'Auth/Authenticate' ||
                    !msg.data ||
                    typeof msg.data !== 'object' ||
                    Array.isArray(msg.data)
                ) {
                    if (ws.readyState === 1) ws.close(1008, 'Authentication required');
                    return;
                }

                authenticating = true;
                await ensureLiveServerConfig();
                if (liveServerConfig.maintenance_mode) {
                    clearTimeout(authTimeout);
                    if (ws.readyState === 1) ws.close(1012, 'Server maintenance');
                    return;
                }

                const requestedIdPlayer = String(msg.data.idPlayer || '');
                const requestedToken = msg.data.authToken;
                const db = await getDb();
                if (!await authenticateSocketPlayer(db, requestedIdPlayer, requestedToken)) {
                    clearTimeout(authTimeout);
                    if (ws.readyState === 1) ws.close(4003, 'AUTHENTICATION_FAILED');
                    return;
                }
                if (ws.readyState !== 1) return;

                idPlayer = requestedIdPlayer;
                token = requestedToken;
                authenticated = true;
                clearTimeout(authTimeout);
                ws.playerToken = token;
                const playerClients = clients.get(idPlayer) || new Set();
                playerClients.add(ws);
                clients.set(idPlayer, playerClients);
                ws.send(JSON.stringify({
                    classPath: 'Auth.Authenticated',
                    data: { state: 'ok' }
                }));
                ws.send(JSON.stringify({
                    classPath: 'World.LiveConfigUpdated',
                    data: {
                        camp_reset_interval_hours: Number(liveServerConfig.camp_reset_interval_hours) || 12,
                        gang_reset_interval_hours: Number(liveServerConfig.gang_reset_interval_hours) || 6,
                        squad_reset_interval_hours: Number(liveServerConfig.squad_reset_interval_hours) || 4
                    }
                }));
                ws.send(JSON.stringify({
                    classPath: 'World.LiveEventsUpdated',
                    data: liveServerConfig.active_events
                }));
                console.log(`[WS] Player connected: idPlayer=${idPlayer}`);
                return;
            }

            if (msg.url === 'Auth/Authenticate') {
                if (ws.readyState === 1) ws.close(1008, 'Already authenticated');
                return;
            }
            if (!await authenticateSocketPlayer(await getDb(), idPlayer, token)) {
                closeReplacedSession(ws);
                return;
            }
            if (liveServerConfig.maintenance_mode) {
                if (ws.readyState === 1) ws.close(1012, 'Server maintenance');
                return;
            }
            console.log('[WS] Handled action');

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

                const [playerRows] = await db.query(
                    'SELECT id_guild FROM player WHERE id_player = ? LIMIT 1',
                    [idPlayer]
                );
                const playerGuildId = playerRows[0] && playerRows[0].id_guild ? Number(playerRows[0].id_guild) : 0;

                const [rows] = await db.query(
                    "SELECT b.id_battel, b.id_player, p.id_guild, p.name AS PlayerName, c.name AS CityName, " +
                    "b.x_city, b.y_city, b.x_coord, b.y_coord, b.task, b.time_start, b.time_arrive " +
                    "FROM world_battel b " +
                    "JOIN player p ON p.id_player = b.id_player " +
                    "JOIN city c ON c.id_city = b.id_city " +
                    "WHERE b.state = 'marching' AND b.time_arrive > UNIX_TIMESTAMP() " +
                    "AND (" +
                    "    b.id_player = ? " +
                    "    OR EXISTS (SELECT 1 FROM city target_c WHERE target_c.x = b.x_coord AND target_c.y = b.y_coord AND target_c.id_player = ?) " +
                    "    OR (? > 0 AND p.id_guild = ?) " +
                    "    OR (? > 0 AND EXISTS (SELECT 1 FROM city target_c JOIN player target_p ON target_p.id_player = target_c.id_player WHERE target_c.x = b.x_coord AND target_c.y = b.y_coord AND target_p.id_guild = ?))" +
                    ") " +
                    "ORDER BY b.id_battel DESC",
                    [idPlayer, idPlayer, playerGuildId, playerGuildId, playerGuildId, playerGuildId]
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
                        // xCity/yCity (march origin) intentionally omitted — exposing them
                        // reveals troop movements to all players who share a guild or are
                        // the target. The map line is suppressed by the client guard too.
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
                    "CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag_expires_at ELSE 0 END AS fe, " +
                    "CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag_tag ELSE '' END AS tag " +
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
                        fe: Number(city.fe) || 0,
                        tag: String(city.tag || '')
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
                    "CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag_expires_at ELSE 0 END AS CityFlagExpiresAt, " +
                    "CASE WHEN c.flag_expires_at > UNIX_TIMESTAMP() THEN c.flag_tag ELSE '' END AS CityFlagTag " +
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
                        CityFlagExpiresAt: Number(city.CityFlagExpiresAt) || 0,
                        CityFlagTag: String(city.CityFlagTag || '')
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

            // 1. Route chat messages using the authenticated player's server-side identity.
            if (msg.url === 'Chat/sendMsg') {
                const data = msg.data;
                const chatMsg = data && typeof data.chat_msg === 'string' ? data.chat_msg.trim() : '';
                if (!chatMsg || Array.from(chatMsg).length > 1000) {
                    sendChatFailure(ws, 'invalid_message');
                    return;
                }

                const db = await getDb();
                const [players] = await db.query(
                    'SELECT id_player, name, avatar, user_group, chat_panne ' +
                    'FROM player WHERE id_player = ? AND p_token = ? LIMIT 1',
                    [idPlayer, token]
                );
                if (!players.length) {
                    closeReplacedSession(ws);
                    return;
                }

                const sender = players[0];
                const chatTo = data.chat_to === 'guild' ? 'guild' : data.chat_to || 'world';
                if (chatTo !== 'world' && chatTo !== 'guild') {
                    sendChatFailure(ws, 'invalid_channel');
                    return;
                }
                if (chatTo === 'world' && Number(sender.chat_panne) > Math.floor(Date.now() / 1000)) {
                    sendChatFailure(ws, 'muted');
                    return;
                }

                const message = makeChatMessage(sender, chatMsg);
                if (data.quoted === true) {
                    message.quoted = true;
                    message.quote = typeof data.quote === 'string' ? data.quote.slice(0, 1000) : '';
                    message.quoteFrom = typeof data.quoteFrom === 'string' ? data.quoteFrom.slice(0, 50) : '';
                }

                if (chatTo === 'world') {
                    broadcast(JSON.stringify({ classPath: 'Chat.WorldMsg', data: message }));
                    return;
                }

                const [memberships] = await db.query(
                    'SELECT id_guild FROM guild_member WHERE id_player = ? LIMIT 1',
                    [idPlayer]
                );
                if (!memberships.length) {
                    sendChatFailure(ws, 'not_in_guild');
                    return;
                }

                const [members] = await db.query(
                    'SELECT id_player FROM guild_member WHERE id_guild = ?',
                    [memberships[0].id_guild]
                );
                const out = JSON.stringify({ classPath: 'Chat.GuildMsg', data: message });
                for (const member of members) {
                    sendToPlayer(member.id_player, out);
                }
                return;
            }

            if (msg.url === 'Chat/sendPrivate') {
                const data = msg.data;
                const chatMsg = data && typeof data.chat_msg === 'string' ? data.chat_msg.trim() : '';
                const targetId = Number(data && data.idPlayerTo);
                if (!chatMsg || Array.from(chatMsg).length > 1000 ||
                    !Number.isSafeInteger(targetId) || targetId <= 0 ||
                    targetId === Number(idPlayer)) {
                    sendChatFailure(ws, 'invalid_message');
                    return;
                }

                const db = await getDb();
                const [players] = await db.query(
                    'SELECT id_player, name, avatar, user_group FROM player ' +
                    'WHERE id_player = ? AND p_token = ? LIMIT 1',
                    [idPlayer, token]
                );
                const [recipients] = await db.query(
                    'SELECT id_player, name, avatar FROM player WHERE id_player = ? LIMIT 1',
                    [targetId]
                );
                if (!players.length) {
                    closeReplacedSession(ws);
                    return;
                }
                if (!recipients.length || getPlayerClients(targetId).length === 0) {
                    ws.send(JSON.stringify({ classPath: 'Chat.NotOnline', data: {} }));
                    return;
                }

                const sender = players[0];
                const recipient = recipients[0];
                const recipientMessage = makeChatMessage(sender, chatMsg);
                sendToPlayer(targetId, JSON.stringify({
                    classPath: 'Chat.privateMsg',
                    data: recipientMessage
                }));

                const senderMessage = makeChatMessage({
                    id_player: recipient.id_player,
                    name: recipient.name,
                    avatar: recipient.avatar,
                    user_group: 0
                }, chatMsg);
                sendToPlayer(idPlayer, JSON.stringify({
                    classPath: 'Chat.privateMsg',
                    data: senderMessage
                }));
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
                const eventMultipliers = liveServerConfig.event_multipliers || {};
                const speedMult = Math.max(0.1, Number(liveServerConfig.march_speed_multiplier) || 1.0)
                    * Math.max(1, Number(eventMultipliers.speed) || 1);
                if (unitType === 60 && (liveServerConfig.server_truce_active || eventMultipliers.truce)) {
                    ws.send(JSON.stringify({ classPath: 'Battel.StartFailed', state: 'in_attackable', msg: 'السيرفر حالياً في هدنة عامة، لا يمكن الهجوم على مدن اللاعبين!' }));
                    return;
                }
                const unitTypeData = unitData[unitType] || {};
                if ((Number(unitTypeData.reqProm) || 0) > (Number(player.porm) || 0)) {
                    ws.send(JSON.stringify({ classPath: 'Battel.StartFailed', state: 'hero_cant_used' }));
                    return;
                }
                const isGuildTarget = (unitType >= 180 && unitType <= 182)
                    || (unitType >= 184 && unitType <= 186)
                    || unitType === 177
                    || (unitType >= 150 && unitType <= 155);
                if (isGuildTarget && (!player.id_guild || Number(player.id_guild) <= 0)) {
                    ws.send(JSON.stringify({
                        classPath: 'Battel.StartFailed',
                        state: 'not_in_guild',
                        msg: 'يجب ان تكون عضو فى حلف لتتمكن من الهجوم'
                    }));
                    return;
                }
                const isGuildDominanceTarget = (unitType >= 180 && unitType <= 182)
                    || (unitType >= 184 && unitType <= 186);
                if (isGuildDominanceTarget) {
                    const [dominanceRows] = await db.query(
                        'SELECT id_guild FROM guild_dominance WHERE x_coord = ? AND y_coord = ? LIMIT 1',
                        [xCoord, yCoord]
                    );
                    if (dominanceRows.length
                        && Number(dominanceRows[0].id_guild) === Number(player.id_guild)) {
                        ws.send(JSON.stringify({
                            classPath: 'Battel.StartFailed',
                            state: 'guild_owns_target'
                        }));
                        return;
                    }
                }

                // ── طابور الهجوم على القلاع (184-186) فقط ───────────────────────────────
                // الممالك (180-182): مفتوحة لأي حلف في أي وقت بدون طابور.
                // القلاع (184-186): يجب أن يكون الحلف في الطابور وأن تكون نافذته الزمنية مفتوحة.
                // كل حلف يحجز دوراً عبر API منفصل (applyForRoleInAttQue).
                // تُفتح نافذة الهجوم كل يوم الساعة 22:00 (10 مساءً) لمدة 24 ساعة.
                // الحلف الأول في الطابور يهجم اليوم الأول، الثاني بعد 24 ساعة، وهكذا.
                const isRepelCastle = (unitType >= 184 && unitType <= 186);
                if (isRepelCastle) {
                    const attackerGuildId = Number(player.id_guild) || 0;
                    const nowTs = Math.floor(Date.now() / 1000);
                    // جلب موقع الحلف في الطابور
                    const [queueRows] = await db.query(
                        "SELECT id_guild, time_start, time_end " +
                        "FROM castle_attack_queue " +
                        "WHERE x_coord = ? AND y_coord = ? AND id_guild = ? " +
                        "LIMIT 1",
                        [xCoord, yCoord, attackerGuildId]
                    );
                    if (!queueRows.length) {
                        ws.send(JSON.stringify({
                            classPath: 'Battel.StartFailed',
                            state: 'not_in_queue',
                            msg: 'حلفك غير مسجل في طابور الهجوم على هذه القلعة، اضغط "طلب" للتسجيل أولاً'
                        }));
                        return;
                    }
                    const slot = queueRows[0];
                    if (nowTs < Number(slot.time_start) || nowTs > Number(slot.time_end)) {
                        ws.send(JSON.stringify({
                            classPath: 'Battel.StartFailed',
                            state: 'queue_window_closed',
                            msg: `نافذة هجوم حلفك لم تُفتح بعد أو انتهت، موعدك: ${new Date(Number(slot.time_start) * 1000).toLocaleString('ar-EG')}`
                        }));
                        return;
                    }
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
                    : unitType >= 71 && unitType <= 80
                        ? Math.max(Number(liveServerConfig.camp_rally_duration_sec) || 120, calculatedDuration)
                    : isFixedDurationTarget(unitType) && unitType !== 177
                        ? (Number(liveServerConfig.camp_rally_duration_sec) || calculatedDuration)
                        : calculatedDuration;
                const marchDuration = Math.max(1, Math.ceil(configuredDuration / speedMult));
                const returnDuration = getReturnDuration({
                    x1: city.x,
                    y1: city.y,
                    x2: xCoord,
                    y2: yCoord,
                    army,
                    unitType,
                    maxDuration: Number(liveServerConfig.camp_rally_duration_sec) || 120
                });
                const now = Math.floor(Date.now() / 1000);
                const arrive = now + marchDuration;

                // 2d. Insert into world_battel
                const launchConnection = await db.getConnection();
                let ins;
                let recoveredPower;
                try {
                    await launchConnection.beginTransaction();
                    const [lockedHeroRows] = await launchConnection.query(
                        'SELECT id_city, lvl, power, power_max, power_ls FROM city_hero ' +
                        'WHERE id_hero = ? AND id_player = ? AND in_city = 1 FOR UPDATE',
                        [idHero, idPlayer]
                    );
                    const lockedHero = lockedHeroRows[0];
                    if (!lockedHero) {
                        const error = new Error('Hero is not available in city');
                        error.code = 'HERO_NOT_AVAILABLE';
                        throw error;
                    }
                    const [lockedCityRows] = await launchConnection.query(
                        'SELECT id_city FROM city WHERE id_city = ? AND id_player = ? FOR UPDATE',
                        [lockedHero.id_city, idPlayer]
                    );
                    if (!lockedCityRows.length) {
                        const error = new Error('Hero deployment city is unavailable');
                        error.code = 'HERO_NOT_AVAILABLE';
                        throw error;
                    }
                    const [plazaRows] = await launchConnection.query(
                        'SELECT COALESCE(MAX(lvl), 0) AS plaza_lvl FROM city_building ' +
                        'WHERE id_city = ? AND type = 11',
                        [lockedHero.id_city]
                    );
                    const outsideLimit = getHeroOutsideCityLimit(plazaRows[0] && plazaRows[0].plaza_lvl);
                    const [outsideRows] = await launchConnection.query(
                        'SELECT COUNT(*) AS amount FROM city_hero h ' +
                        'WHERE h.id_city = ? AND h.id_player = ? AND h.in_city = 0 AND h.loyal > 0 ' +
                        'AND NOT EXISTS (' +
                        "SELECT 1 FROM city_garrison g WHERE g.id_hero = h.id_hero " +
                        "AND g.id_city = h.id_city AND g.status = 'engaged')",
                        [lockedHero.id_city, idPlayer]
                    );
                    if (Number(outsideRows[0] && outsideRows[0].amount || 0) >= outsideLimit) {
                        const error = new Error('City plaza has no available hero departure slot');
                        error.code = 'PLAZA_CAPACITY';
                        throw error;
                    }
                    const heroPowerMax = Math.max(0, Number(lockedHero.power_max) || 0);
                    recoveredPower = recoverHeroPower(
                        Number(lockedHero.power) || 0,
                        heroPowerMax,
                        Number(lockedHero.power_ls) || 0,
                        now
                    );
                    await launchConnection.query(
                        'UPDATE city_hero SET power = ?, power_max = ?, power_ls = ? ' +
                        'WHERE id_hero = ? AND id_player = ?',
                        [
                            recoveredPower.power,
                            heroPowerMax,
                            recoveredPower.powerLastSync,
                            idHero,
                            idPlayer
                        ]
                    );
                    const requiredPower = getHeroRequiredPower(
                        attackTask,
                        Number(unitTypeData.reqFitness) || 0
                    );
                    if (recoveredPower.power < requiredPower) {
                        await launchConnection.commit();
                        ws.send(JSON.stringify({
                            classPath: 'Battel.StartFailed',
                            state: 'hero_not_enough_power'
                        }));
                        return;
                    }
                    await consumeUnitRequirements(launchConnection, idPlayer, unitTypeData.MakeReq);
                    const [heroUpdate] = await launchConnection.query(
                        'UPDATE city_hero SET in_city = 0, power = GREATEST(0, power - ?), power_ls = ? ' +
                        'WHERE id_hero = ? AND id_player = ? AND in_city = 1 AND power >= ?',
                        [HERO_ACTION_POWER_COST, now, idHero, idPlayer, requiredPower]
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
                    await launchConnection.query(
                        "INSERT INTO timed_task (id_city, id_player, task_type, item_id, lvl_to, start_time, time_arrive, details) " +
                        "VALUES (?, ?, 'battle', ?, ?, ?, ?, ?)",
                        [
                            city.id_city,
                            player.id_player,
                            String(ins.insertId),
                            unitLvl,
                            now,
                            arrive,
                            JSON.stringify({ unit_type: unitType, unit_subtype: unitSubtype, is_barray: isBarray })
                        ]
                    );
                    await launchConnection.commit();
                } catch (error) {
                    await launchConnection.rollback();
                    if (error.code === 'INSUFFICIENT_WORLD_UNIT_ITEM' || error.code === 'HERO_NOT_AVAILABLE'
                        || error.code === 'PLAZA_CAPACITY') {
                        ws.send(JSON.stringify({
                            classPath: 'Battel.StartFailed',
                            state: error.code === 'INSUFFICIENT_WORLD_UNIT_ITEM' ? 'no_enough_mat'
                                : error.code === 'PLAZA_CAPACITY' ? 'hero_count_over_plaze'
                                    : 'hero_cant_used'
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
                    idHero,
                    heroPower: Math.max(0, recoveredPower.power - HERO_ACTION_POWER_COST),
                    heroPowerLastSync: now,
                    task: attackTask,
                    timeStart: now,
                    timeEnd: arrive,
                    StartingPrice: []
                }));

                // World.Battel.Started broadcast intentionally suppressed —
                // the march indicator (arrow/line on the world map) is not shown to any party.

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
                                    "SELECT m.id_hero, m.id_player, m.task, m.x_city, m.y_city, " +
                                    "g.id_city AS garrison_city_id, h.id_city AS home_city_id " +
                                    "FROM world_battel_member m " +
                                    "JOIN city_hero h ON h.id_hero = m.id_hero " +
                                    "LEFT JOIN city_garrison g ON g.id_hero = m.id_hero " +
                                    "WHERE m.id_battel = ? AND m.state = 'marching' FOR UPDATE",
                                    [battle.id_battel]
                                );
                                await abortConnection.query(
                                    "UPDATE world_battel_member SET state = 'aborted' " +
                                    "WHERE id_battel = ? AND state = 'marching'",
                                    [battle.id_battel]
                                );
                                await abortConnection.query(
                                    "DELETE FROM timed_task WHERE task_type IN ('battle', 'battle_processing') AND item_id = ?",
                                    [String(battle.id_battel)]
                                );
                                for (const member of members) {
                                    const remainsGarrisoned = member.garrison_city_id !== null &&
                                        Number(member.garrison_city_id) !== Number(member.home_city_id);
                                    if (!remainsGarrisoned) {
                                        const [heroUpdate] = await abortConnection.query(
                                            "UPDATE city_hero SET in_city = 1 " +
                                            "WHERE id_hero = ? AND id_player = ? AND in_city = 0",
                                            [member.id_hero, member.id_player]
                                        );
                                        if (heroUpdate.affectedRows) returnedHeroes.push(member);
                                    }
                                    await abortConnection.query(
                                        "UPDATE city_garrison SET status = 'stationed', battle_id = NULL, return_at = 0 " +
                                        "WHERE id_hero = ? AND battle_id = ? AND status = 'engaged'",
                                        [member.id_hero, battle.id_battel]
                                    );
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
            if (!authenticated && ws.readyState === 1) {
                clearTimeout(authTimeout);
                const isInvalidMessage = e instanceof SyntaxError;
                ws.close(
                    isInvalidMessage ? 1008 : 1011,
                    isInvalidMessage ? 'Authentication required' : 'Authentication unavailable'
                );
            }
        }
    });

    ws.on('close', () => {
        clearTimeout(authTimeout);
        if (idPlayer) {
            const playerClients = clients.get(String(idPlayer));
            if (playerClients) {
                playerClients.delete(ws);
                if (playerClients.size === 0) clients.delete(String(idPlayer));
            }
            console.log(`[WS] Disconnected: idPlayer=${idPlayer}`);
        }
    });

    ws.on('error', (err) => {
        console.error('[WS] Error for', idPlayer, ':', err.message);
    });
}

// ── Server Start ─────────────────────────────────────────────────────────────
const server = TLS_CERT_FILE
    ? https.createServer({
        cert: fs.readFileSync(TLS_CERT_FILE),
        key: fs.readFileSync(TLS_KEY_FILE)
    }, handleHttp)
    : http.createServer(handleHttp);
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
const cityGarrisonTimer = setInterval(processDueCityGarrisonArrivals, 1000);
cityGarrisonTimer.unref();
const worldUnitResetTimer = setInterval(processDueWorldUnitResets, 60000);
worldUnitResetTimer.unref();
initializeBattleTaskWorker();
const battleTaskTimer = setInterval(processDueBattleResolutionTasks, 1000);
battleTaskTimer.unref();
const battleTaskRecoveryTimer = setInterval(initializeBattleTaskWorker, 5000);
battleTaskRecoveryTimer.unref();

server.listen(PORT, '127.0.0.1', () => {
    console.log(`[WS-Server] ✅ Listening on port ${PORT}`);
    console.log(`[WS-Server] WebSocket: ${TLS_CERT_FILE ? 'wss' : 'ws'}://localhost:${PORT}`);
    console.log(`[WS-Server] Combat, reports, and persistent hero returns are active.`);
});

process.on('uncaughtException', (err) => {
    console.error('[WS-Server] Uncaught:', err.message);
    process.exit(1);
});

const UNIT_RESET_INTERVALS = new Map([
    ...[30, 31, 56, 100, 101, 102, 103, 104, 105, 125, 126, 127, 130, 131, 132, 134, 135, 136, 150, 151, 152]
        .map(type => [type, 12]),
    ...[32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 153, 154, 155]
        .map(type => [type, 4]),
    ...[231, 232, 233, 234, 235, 236].map(type => [type, 12]),
    [55, 6]
]);

const TROOP_SPEEDS = [0, 300, 900, 600, 250, 150, 100];
const HERO_ACTION_POWER_COST = 10;
const BATTLE_TASK_DOMINATE = 1;
const HERO_POWER_RECOVERY_INTERVAL_SECONDS = 120;
const ROMAN_COMMANDERS = [
    'أغسطس',
    'تيبيريوس',
    'كاليغولا',
    'كلوديوس',
    'نيرون',
    'فسباسيان',
    'تيطس',
    'دوميتيان',
    'نيرفا',
    'تراجان',
    'هادريان',
    'أنطونينوس بيوس',
    'ماركوس أوريليوس',
    'كومودوس',
    'سبتيميوس سيفيروس',
    'قسطنطين'
];

function getRomanCommanderName(unitType, unitLevel, xCoord = 0, yCoord = 0) {
    const values = [unitType, unitLevel, xCoord, yCoord].map(Number);
    if (!values.every(Number.isSafeInteger) || values[0] <= 0 || values[1] <= 0) {
        throw new TypeError('Roman commander lookup requires valid unit and level values');
    }

    const index = (
        values[0] * 31 + values[2] * 17 + values[3] * 13 + values[1] - 1
    ) % ROMAN_COMMANDERS.length;
    return ROMAN_COMMANDERS[index];
}

function recoverHeroPower(power, powerMax, powerLastSync, nowSeconds) {
    const values = [power, powerMax, powerLastSync, nowSeconds].map(Number);
    if (!values.every(Number.isSafeInteger) || values[1] < 0 || values[3] < 0) {
        throw new TypeError('Hero power recovery requires safe integer values');
    }

    const [rawPower, maxPower, lastSync, now] = values;
    const currentPower = Math.min(maxPower, Math.max(0, rawPower));
    if (currentPower >= maxPower || lastSync <= 0) {
        return { power: currentPower, powerLastSync: now };
    }

    const recoveredPoints = Math.floor(Math.max(0, now - lastSync) / HERO_POWER_RECOVERY_INTERVAL_SECONDS);
    if (recoveredPoints === 0) {
        return { power: currentPower, powerLastSync: lastSync };
    }

    const nextPower = Math.min(maxPower, currentPower + recoveredPoints);
    return {
        power: nextPower,
        powerLastSync: nextPower >= maxPower
            ? now
            : lastSync + recoveredPoints * HERO_POWER_RECOVERY_INTERVAL_SECONDS
    };
}

function getHeroMaxPower(level) {
    const heroLevel = Number(level);
    if (!Number.isSafeInteger(heroLevel)) {
        throw new TypeError('Hero max power requires a safe integer level');
    }
    return 50 + (2 * Math.max(0, heroLevel));
}

function getHeroRequiredPower(task, targetRequiredPower = 0) {
    const values = [task, targetRequiredPower].map(Number);
    if (!values.every(Number.isSafeInteger) || values[1] < 0) {
        throw new TypeError('Hero power requirement requires safe integer values');
    }
    return values[0] === BATTLE_TASK_DOMINATE
        ? 0
        : Math.max(HERO_ACTION_POWER_COST, values[1]);
}

function getWrappedDistance(x1, y1, x2, y2) {
    let deltaX = Math.abs(Number(x1) - Number(x2));
    let deltaY = Math.abs(Number(y1) - Number(y2));
    if (![deltaX, deltaY].every(Number.isFinite)) {
        throw new TypeError('Battle coordinates must be finite numbers');
    }
    if (deltaX > 249) deltaX = 500 - deltaX;
    if (deltaY > 249) deltaY = 500 - deltaY;
    return Math.floor(Math.hypot(deltaX, deltaY)) * 6000;
}

function getArmyMinimumSpeed(army) {
    const speeds = [];
    for (const slot of ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3']) {
        const type = Number(army[`${slot}_type`]) || 0;
        const count = Number(army[`${slot}_num`]) || 0;
        if (count > 0 && TROOP_SPEEDS[type]) speeds.push(TROOP_SPEEDS[type]);
    }
    return speeds.length ? Math.min(...speeds) : 900;
}

function isFixedDurationTarget(unitType) {
    unitType = Number(unitType) || 0;
    return unitType === 70
        || (unitType >= 81 && unitType <= 105)
        || (unitType >= 150 && unitType <= 155)
        || (unitType >= 175 && unitType <= 177)
        || (unitType >= 200 && unitType <= 205);
}

function normalizeDynamicWorldUnitSeedLevels(worldUnits, unitData) {
    if (!(worldUnits instanceof Map) || !unitData || typeof unitData !== 'object' || Array.isArray(unitData)) {
        throw new TypeError('World-unit seed normalization requires a map and unit data');
    }

    let normalized = 0;
    for (const mapUnit of worldUnits.values()) {
        if (unitData[Number(mapUnit.ut)] && unitData[Number(mapUnit.ut)].lvlChange === true) {
            mapUnit.l = 1;
            mapUnit.baseL = 1;
            normalized++;
        }
    }
    return normalized;
}

function getMarchDuration({ x1, y1, x2, y2, army, unitType, isCity }) {
    unitType = Number(unitType) || 0;
    if (unitType === 177) return 300;
    if (unitType >= 184 && unitType <= 186) return 3600;
    if ((unitType >= 180 && unitType <= 182) || (unitType >= 231 && unitType <= 236)) return 900;
    const duration = Math.max(1, Math.ceil(getWrappedDistance(x1, y1, x2, y2) / getArmyMinimumSpeed(army)));
    if (unitType >= 71 && unitType <= 80) return Math.max(120, duration);
    if (isFixedDurationTarget(unitType)) return 120;
    return (isCity || (unitType >= 60 && unitType <= 63)) ? Math.max(15 * 60, duration) : duration;
}

function getReturnDuration({ x1, y1, x2, y2, army, unitType, maxDuration = 120 }) {
    const duration = Math.max(1, Math.ceil(getWrappedDistance(x1, y1, x2, y2) / getArmyMinimumSpeed(army)));
    unitType = Number(unitType) || 0;
    const hasMaximumReturnDuration = (unitType >= 70 && unitType <= 105)
        || (unitType >= 150 && unitType <= 155)
        || (unitType >= 175 && unitType <= 176)
        || (unitType >= 200 && unitType <= 205);
    if (!hasMaximumReturnDuration) return duration;

    const configuredMaximum = Number(maxDuration);
    const maximum = Number.isFinite(configuredMaximum) && configuredMaximum > 0
        ? Math.floor(configuredMaximum)
        : 120;
    return Math.min(duration, Math.max(1, maximum));
}

function getResetIntervalHours(unitSubtype, config = {}) {
    const defaultInterval = UNIT_RESET_INTERVALS.get(Number(unitSubtype)) || 0;
    if (!defaultInterval) return 0;
    const key = defaultInterval === 12
        ? 'camp_reset_interval_hours'
        : defaultInterval === 6
            ? 'gang_reset_interval_hours'
            : 'squad_reset_interval_hours';
    const configuredInterval = Number(config[key]);
    return Number.isInteger(configuredInterval) && configuredInterval >= 1 && configuredInterval <= 48
        ? configuredInterval
        : defaultInterval;
}

function getNextResetAt(unitSubtype, nowSeconds, config = {}) {
    const defaultInterval = UNIT_RESET_INTERVALS.get(Number(unitSubtype)) || 0;
    const intervalHours = getResetIntervalHours(unitSubtype, config);
    if (!intervalHours) return 0;

    const now = new Date(Number(nowSeconds) * 1000);
    const anchorHour = defaultInterval === 6 ? 2 : defaultInterval === 12 ? 8 : 0;
    const resetSeconds = defaultInterval === 6 ? 2 : 0;
    const next = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(), anchorHour, 0, resetSeconds));
    while (next.getTime() <= now.getTime()) {
        next.setUTCHours(next.getUTCHours() + intervalHours);
    }
    return Math.floor(next.getTime() / 1000);
}

function applyPersistedWorldUnitLevels(worldUnits, states, nowSeconds) {
    if (!(worldUnits instanceof Map) || !Array.isArray(states)) {
        throw new TypeError('World-unit restoration requires a map and state rows');
    }
    const now = Number(nowSeconds);
    if (!Number.isSafeInteger(now)) {
        throw new TypeError('World-unit restoration time must be a safe integer');
    }

    let restored = 0;
    for (const state of states) {
        const x = Number(state.x_coord);
        const y = Number(state.y_coord);
        const unitType = Number(state.unit_type);
        const unitSubtype = Number(state.unit_subtype);
        const mapUnit = worldUnits.get(`${x}_${y}`);
        if (!mapUnit
            || Number(mapUnit.ut) !== unitType
            || Number(mapUnit.t) !== unitSubtype) {
            continue;
        }

        const baseLevel = Number(state.base_lvl);
        const currentLevel = Number(state.current_lvl);
        const maxLevel = Number(state.max_lvl);
        const nextResetAt = Number(state.next_reset_at);
        if (![x, y, unitType, unitSubtype, baseLevel, currentLevel, maxLevel, nextResetAt]
            .every(Number.isSafeInteger)
            || baseLevel < 1 || currentLevel < 1 || maxLevel < baseLevel || nextResetAt < 0) {
            throw new Error(`Invalid persisted world-unit level at ${x}_${y}`);
        }
        mapUnit.l = nextResetAt > 0 && nextResetAt <= now
            ? baseLevel
            : Math.min(currentLevel, maxLevel);
        restored++;
    }
    return restored;
}

module.exports = {
    HERO_ACTION_POWER_COST,
    getHeroMaxPower,
    getHeroRequiredPower,
    applyPersistedWorldUnitLevels,
    getArmyMinimumSpeed,
    getMarchDuration,
    getNextResetAt,
    getResetIntervalHours,
    recoverHeroPower,
    getReturnDuration,
    getRomanCommanderName,
    getWrappedDistance,
    isFixedDurationTarget,
    normalizeDynamicWorldUnitSeedLevels,
    ROMAN_COMMANDERS
};

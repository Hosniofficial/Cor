const UNIT_RESET_INTERVALS = new Map([
    ...[30, 31, 56, 100, 101, 102, 103, 104, 105, 125, 126, 127, 130, 131, 132, 134, 135, 136, 150, 151, 152]
        .map(type => [type, 12]),
    ...[32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 153, 154, 155]
        .map(type => [type, 4]),
    [55, 6]
]);

const TROOP_SPEEDS = [0, 300, 900, 600, 250, 150, 100];

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
    return (unitType >= 70 && unitType <= 105)
        || (unitType >= 150 && unitType <= 155)
        || (unitType >= 175 && unitType <= 177)
        || (unitType >= 200 && unitType <= 205);
}

function getMarchDuration({ x1, y1, x2, y2, army, unitType, isCity }) {
    if (unitType === 177) return 300;
    if (isFixedDurationTarget(unitType)) return 120;
    const duration = Math.max(1, Math.ceil(getWrappedDistance(x1, y1, x2, y2) / getArmyMinimumSpeed(army)));
    return isCity ? Math.max(15 * 60, duration) : duration;
}

function getReturnDuration({ x1, y1, x2, y2, army }) {
    return Math.max(1, Math.ceil(getWrappedDistance(x1, y1, x2, y2) / getArmyMinimumSpeed(army)));
}

function getResetIntervalHours(unitSubtype) {
    return UNIT_RESET_INTERVALS.get(Number(unitSubtype)) || 0;
}

function getNextResetAt(unitSubtype, nowSeconds) {
    const intervalHours = getResetIntervalHours(unitSubtype);
    if (!intervalHours) return 0;

    const now = new Date(Number(nowSeconds) * 1000);
    const anchorHour = intervalHours === 6 ? 2 : intervalHours === 12 ? 8 : 0;
    const resetSeconds = intervalHours === 12 ? 0 : 2;
    const next = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(), anchorHour, 0, resetSeconds));
    while (next.getTime() <= now.getTime()) {
        next.setUTCHours(next.getUTCHours() + intervalHours);
    }
    return Math.floor(next.getTime() / 1000);
}

module.exports = {
    getArmyMinimumSpeed,
    getMarchDuration,
    getNextResetAt,
    getResetIntervalHours,
    getReturnDuration,
    getWrappedDistance,
    isFixedDurationTarget
};

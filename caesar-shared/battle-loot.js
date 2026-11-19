const { allocateProportionally, allocateResourceLoot } = require('./battle-party');

const RESOURCES = ['food', 'wood', 'stone', 'metal', 'coin'];
const PRIZE_RATE_SCALE = 1000;

function isPrizeDropSuccessful(rate, randomValue = Math.random()) {
    const threshold = Math.max(0, Math.min(PRIZE_RATE_SCALE, Number(rate) || 0));
    return Math.floor(randomValue * PRIZE_RATE_SCALE) + 1 <= threshold;
}

function randomInteger(min, max, random = Math.random) {
    const minimum = Number.isSafeInteger(min) ? min : 0;
    const maximum = Number.isSafeInteger(max) ? max : minimum;
    if (maximum <= minimum) return minimum;
    return minimum + Math.floor(random() * (maximum - minimum + 1));
}

function getWildernessResource(unitType) {
    const type = Number(unitType);
    if (!Number.isSafeInteger(type)) return null;
    if (type >= 1 && type <= 10) return 'food';
    if (type >= 30 && type <= 39) return 'metal';
    if (type >= 40 && type <= 49) return 'stone';
    if (type >= 50 && type <= 59) return 'wood';
    return null;
}

function getWildernessPrizeUnitType(unitType, lvl) {
    const level = Math.max(1, Math.min(10, Number(lvl) || 1));
    const ut = Number(unitType);
    if (ut >= 1 && ut <= 10) return level;
    if (ut >= 30 && ut <= 39) return 29 + level;
    if (ut >= 40 && ut <= 49) return 39 + level;
    if (ut >= 50 && ut <= 59) return 49 + level;
    return ut;
}

function calculateWildernessSpoils(targetLevel, unitType, random = Math.random) {
    const level = Math.max(1, Number(targetLevel) || 1);
    const baseLoot = level * 15000;
    const loot = Object.fromEntries(RESOURCES.map(resource => [resource, 0]));
    const resource = getWildernessResource(unitType);
    if (!resource) return loot;

    const rewardRanges = {
        food: [baseLoot, 5000, 15000],
        wood: [Math.floor(baseLoot * 0.8), 3000, 10000],
        stone: [Math.floor(baseLoot * 0.6), 2000, 8000],
        metal: [Math.floor(baseLoot * 0.5), 1000, 6000]
    };
    const [baseAmount, randomMin, randomMax] = rewardRanges[resource];
    loot[resource] = baseAmount + randomInteger(randomMin, randomMax, random);
    return loot;
}

async function transferBattleLoot({ db, targetCity, attackers, wildernessLoot, creditCityResources, wildernessRecipient = null }) {
    let lootPool = { ...wildernessLoot };
    if (targetCity) {
        const [rows] = await db.query(
            'SELECT id_city, id_player, food, wood, stone, metal, coin FROM city WHERE id_city = ? FOR UPDATE',
            [targetCity.id_city]
        );
        if (!rows.length) {
            throw new Error(`Battle target city ${targetCity.id_city} is no longer available`);
        }
        lootPool = Object.fromEntries(RESOURCES.map(resource => [
            resource,
            Math.max(0, Number(rows[0][resource]) || 0)
        ]));
        targetCity = { id_city: Number(rows[0].id_city), id_player: Number(rows[0].id_player) };
    }

    for (const member of attackers) {
        member.resourceLoot = Object.fromEntries(RESOURCES.map(resource => [resource, 0]));
    }

    const cargoCarriers = attackers.filter(member => member.cargoCapacity > 0);
    const shares = targetCity
        ? allocateResourceLoot(lootPool, cargoCarriers)
        : wildernessRecipient
            ? [{
                member: wildernessRecipient,
                resources: Object.fromEntries(RESOURCES.map(resource => [
                    resource,
                    Number(lootPool[resource]) || 0
                ]))
            }]
            : attackers.map(member => ({
                member,
                resources: Object.fromEntries(RESOURCES.map(resource => [resource, 0]))
            }));
    if (!targetCity && !wildernessRecipient) {
        const sharesByMember = new Map(shares.map(share => [share.member, share]));
        for (const [resource, amount] of Object.entries(lootPool)) {
            for (const share of allocateProportionally(amount, attackers)) {
                sharesByMember.get(share.member).resources[resource] = share.amount;
            }
        }
    }
    for (const share of shares) {
        if (!Object.values(share.resources).some(amount => amount > 0)) continue;
        const credited = await creditCityResources(
            db,
            share.member.city.id_city,
            share.resources
        );
        share.member.resourceLoot = {
            ...share.member.resourceLoot,
            ...(credited || {})
        };
    }

    const creditedLoot = Object.fromEntries(RESOURCES.map(resource => [
        resource,
        attackers.reduce((total, member) => total + (Number(member.resourceLoot[resource]) || 0), 0)
    ]));
    const affectedCities = new Map();
    for (const member of attackers) {
        if (Object.values(member.resourceLoot).some(amount => amount > 0)) {
            affectedCities.set(Number(member.city.id_city), Number(member.player.id_player));
        }
    }

    if (targetCity && Object.values(creditedLoot).some(amount => amount > 0)) {
        const setClauses = RESOURCES.map(resource => `\`${resource}\` = \`${resource}\` - ?`);
        const guards = RESOURCES.map(resource => `\`${resource}\` >= ?`);
        const debitAmounts = RESOURCES.map(resource => creditedLoot[resource]);
        const [debit] = await db.query(
            `UPDATE city SET ${setClauses.join(', ')} WHERE id_city = ? AND ${guards.join(' AND ')}`,
            [...debitAmounts, targetCity.id_city, ...debitAmounts]
        );
        if (!debit.affectedRows) {
            throw new Error(`Battle target city ${targetCity.id_city} could not be debited`);
        }
        affectedCities.set(targetCity.id_city, targetCity.id_player);
    }

    const cityIds = [...affectedCities.keys()];
    if (!cityIds.length) return { lootPool, creditedLoot, cityUpdates: [] };
    const placeholders = cityIds.map(() => '?').join(', ');
    const [cityRows] = await db.query(
        `SELECT id_city, id_player, food, wood, stone, metal, coin FROM city WHERE id_city IN (${placeholders})`,
        cityIds
    );
    const cityUpdates = cityRows.map(row => ({
        idCity: Number(row.id_city),
        idPlayer: Number(row.id_player),
        resources: Object.fromEntries(RESOURCES.map(resource => [
            resource,
            Number(row[resource]) || 0
        ]))
    }));
    return { lootPool, creditedLoot, cityUpdates };
}

module.exports = {
    calculateWildernessSpoils,
    getWildernessResource,
    getWildernessPrizeUnitType,
    isPrizeDropSuccessful,
    randomInteger,
    transferBattleLoot
};

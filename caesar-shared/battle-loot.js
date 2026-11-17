const { allocateResourceLoot } = require('./battle-party');

const RESOURCES = ['food', 'wood', 'stone', 'metal', 'coin'];

async function transferBattleLoot({ db, targetCity, attackers, wildernessLoot, creditCityResources }) {
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
    const shares = allocateResourceLoot(lootPool, cargoCarriers);
    for (const share of shares) {
        share.member.resourceLoot = await creditCityResources(
            db,
            share.member.city.id_city,
            share.resources
        );
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

module.exports = { transferBattleLoot };

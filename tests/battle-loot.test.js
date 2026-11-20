const test = require('node:test');
const assert = require('node:assert/strict');
const worldUnitPrizes = require('../caesar-private/data/world-unit-prizes.json');
const {
    calculateWildernessSpoils,
    getWildernessResource,
    getWildernessPrizeUnitType,
    isPrizeDropSuccessful,
    randomInteger,
    transferBattleLoot
} = require('../caesar-shared/battle-loot');

test('city battle loot is capped by army capacity, debited from the target, and returns fresh city balances', async () => {
    const cities = new Map([
        [10, { id_city: 10, id_player: 1, food: 100, wood: 100, stone: 100, metal: 100, coin: 100 }],
        [20, { id_city: 20, id_player: 2, food: 80, wood: 40, stone: 0, metal: 0, coin: 0 }]
    ]);
    const queries = [];
    const db = {
        async query(sql, params = []) {
            queries.push(sql);
            if (sql.startsWith('SELECT id_city, id_player, food, wood, stone, metal, coin FROM city WHERE id_city =')) {
                const city = cities.get(Number(params[0]));
                return [city ? [{ ...city }] : [], []];
            }
            if (sql.startsWith('UPDATE city SET')) {
                const cityId = Number(params[5]);
                const city = cities.get(cityId);
                for (const resource of ['food', 'wood', 'stone', 'metal', 'coin']) {
                    city[resource] -= Number(params[['food', 'wood', 'stone', 'metal', 'coin'].indexOf(resource)]);
                }
                return [{ affectedRows: 1 }, []];
            }
            if (sql.startsWith('SELECT id_city, id_player, food, wood, stone, metal, coin FROM city WHERE id_city IN')) {
                return [[...params].map(id => ({ ...cities.get(Number(id)) })), []];
            }
            throw new Error(`Unexpected query: ${sql}`);
        }
    };
    const attackers = [{
        cargoCapacity: 50,
        city: { id_city: 10 },
        player: { id_player: 1 }
    }];

    const result = await transferBattleLoot({
        db,
        targetCity: { id_city: 20 },
        attackers,
        wildernessLoot: { food: 0, wood: 0, stone: 0, metal: 0, coin: 0 },
        async creditCityResources(connection, cityId, requested) {
            const city = cities.get(Number(cityId));
            const credited = {};
            for (const [resource, amount] of Object.entries(requested)) {
                credited[resource] = amount;
                city[resource] += amount;
            }
            return credited;
        }
    });

    assert.deepEqual(result.creditedLoot, { food: 33, wood: 17, stone: 0, metal: 0, coin: 0 });
    assert.deepEqual(cities.get(10), {
        id_city: 10, id_player: 1, food: 133, wood: 117, stone: 100, metal: 100, coin: 100
    });
    assert.deepEqual(cities.get(20), {
        id_city: 20, id_player: 2, food: 47, wood: 23, stone: 0, metal: 0, coin: 0
    });
    assert.deepEqual(result.cityUpdates, [
        {
            idCity: 10,
            idPlayer: 1,
            resources: { food: 133, wood: 117, stone: 100, metal: 100, coin: 100 }
        },
        {
            idCity: 20,
            idPlayer: 2,
            resources: { food: 47, wood: 23, stone: 0, metal: 0, coin: 0 }
        }
    ]);
    assert.equal(queries.filter(sql => sql.startsWith('UPDATE city SET')).length, 1);
});

test('wilderness spoils grant only the resource assigned to the wilderness type', () => {
    const zeroLoot = { food: 0, wood: 0, stone: 0, metal: 0, coin: 0 };
    assert.deepEqual(calculateWildernessSpoils(1, 44, () => 0), {
        ...zeroLoot,
        stone: 11000,
    });
    assert.deepEqual(calculateWildernessSpoils(5, 44, () => 0), {
        ...zeroLoot,
        stone: 47000,
    });
    assert.deepEqual(calculateWildernessSpoils(1, 1, () => 0), {
        ...zeroLoot,
        food: 20000
    });
    assert.deepEqual(calculateWildernessSpoils(1, 34, () => 0), {
        ...zeroLoot,
        metal: 8500
    });
    assert.deepEqual(calculateWildernessSpoils(1, 50, () => 0), {
        ...zeroLoot,
        wood: 15000
    });
    assert.deepEqual(calculateWildernessSpoils(1, 12, () => 0), zeroLoot);
    for (const [type, resource] of [
        [1, 'food'], [10, 'food'],
        [30, 'metal'], [39, 'metal'],
        [40, 'stone'], [49, 'stone'],
        [50, 'wood'], [59, 'wood']
    ]) {
        assert.equal(getWildernessResource(type), resource);
    }

    const randomValues = [0.25];
    let randomCalls = 0;
    assert.deepEqual(calculateWildernessSpoils(1, 44, () => {
        randomCalls++;
        return randomValues.shift();
    }), {
        ...zeroLoot,
        stone: 12500,
    });
    assert.equal(randomCalls, 1);
    assert.equal(randomInteger(5000, 15000, () => 0), 5000);
});

test('wilderness prize resource types match the raw prize table', () => {
    for (const [type, resource] of [
        [1, 'food'], [10, 'food'],
        [30, 'metal'], [39, 'metal'],
        [40, 'stone'], [49, 'stone'],
        [50, 'wood'], [59, 'wood']
    ]) {
        const resources = new Set(worldUnitPrizes.Win
            .filter(prize => Number(prize.unitType) === type && /^(food|wood|stone|metal)_[a-e]$/.test(prize.prize))
            .map(prize => prize.prize.split('_')[0]));
        assert.deepEqual([...resources], [resource]);
    }
});

test('prize drop rates use the configured 1,000-point scale', () => {
    assert.equal(isPrizeDropSuccessful(100, 0.0999), true);
    assert.equal(isPrizeDropSuccessful(100, 0.1), false);
    assert.equal(isPrizeDropSuccessful(1000, 0.9999), true);
    assert.equal(isPrizeDropSuccessful(1500, 0.9999), true);
    assert.equal(isPrizeDropSuccessful(0, 0), false);
});

test('wilderness resource rewards credit cities directly without cargo capacity limits', async () => {
    const cities = new Map([
        [10, { id_city: 10, id_player: 1, food: 100, wood: 100, stone: 100, metal: 100, coin: 100 }],
        [20, { id_city: 20, id_player: 2, food: 50, wood: 50, stone: 50, metal: 50, coin: 50 }]
    ]);
    const db = {
        async query(sql, params = []) {
            if (sql.startsWith('SELECT id_city, id_player, food, wood, stone, metal, coin FROM city WHERE id_city IN')) {
                return [[...params].map(id => ({ ...cities.get(Number(id)) })), []];
            }
            throw new Error(`Unexpected query: ${sql}`);
        }
    };
    const attackers = [
        { cargoCapacity: 0, combatPower: 3, city: { id_city: 10 }, player: { id_player: 1 } },
        { cargoCapacity: 0, combatPower: 1, city: { id_city: 20 }, player: { id_player: 2 } }
    ];

    const result = await transferBattleLoot({
        db,
        targetCity: null,
        attackers,
        wildernessLoot: { stone: 400 },
        async creditCityResources(connection, cityId, requested) {
            const city = cities.get(Number(cityId));
            for (const [resource, amount] of Object.entries(requested)) {
                city[resource] += amount;
            }
            return requested;
        }
    });

    assert.deepEqual(result.creditedLoot, { food: 0, wood: 0, stone: 400, metal: 0, coin: 0 });
    assert.equal(cities.get(10).stone, 400);
    assert.equal(cities.get(20).stone, 150);
    assert.deepEqual(attackers.map(member => member.resourceLoot.stone), [300, 100]);
    assert.deepEqual(attackers.map(member => member.resourceLoot), [
        { food: 0, wood: 0, stone: 300, metal: 0, coin: 0 },
        { food: 0, wood: 0, stone: 100, metal: 0, coin: 0 }
    ]);
});

test('wilderness battles with no direct resource drops leave zero balances without crediting cities', async () => {
    const attackers = [{
        cargoCapacity: 0,
        combatPower: 1,
        city: { id_city: 10 },
        player: { id_player: 1 }
    }];
    const result = await transferBattleLoot({
        db: {
            async query() {
                throw new Error('No city query expected when there is no resource loot');
            }
        },
        targetCity: null,
        attackers,
        wildernessLoot: {},
        async creditCityResources() {
            throw new Error('No resource credit expected when there is no resource loot');
        }
    });

    assert.deepEqual(result.creditedLoot, { food: 0, wood: 0, stone: 0, metal: 0, coin: 0 });
    assert.deepEqual(attackers[0].resourceLoot, { food: 0, wood: 0, stone: 0, metal: 0, coin: 0 });
    assert.deepEqual(result.cityUpdates, []);
});

test('wilderness prize unit type maps all terrain categories and levels 1 to 10', () => {
    // Lakes (1..10)
    for (let lvl = 1; lvl <= 10; lvl++) {
        assert.equal(getWildernessPrizeUnitType(1, lvl), lvl);
        assert.equal(getWildernessPrizeUnitType(lvl, lvl), lvl);
    }
    // Hills and Mountains (30..39)
    for (let lvl = 1; lvl <= 10; lvl++) {
        assert.equal(getWildernessPrizeUnitType(30, lvl), 29 + lvl);
        assert.equal(getWildernessPrizeUnitType(35, lvl), 29 + lvl);
    }
    // Sands and Deserts (40..49)
    for (let lvl = 1; lvl <= 10; lvl++) {
        assert.equal(getWildernessPrizeUnitType(40, lvl), 39 + lvl);
        assert.equal(getWildernessPrizeUnitType(44, lvl), 39 + lvl);
    }
    // Thickets and Forests (50..59)
    for (let lvl = 1; lvl <= 10; lvl++) {
        assert.equal(getWildernessPrizeUnitType(50, lvl), 49 + lvl);
        assert.equal(getWildernessPrizeUnitType(58, lvl), 49 + lvl);
    }
    // Non-wilderness keeps original type
    assert.equal(getWildernessPrizeUnitType(70, 5), 70);
    assert.equal(getWildernessPrizeUnitType(100, 3), 100);
});

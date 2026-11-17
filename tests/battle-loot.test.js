const test = require('node:test');
const assert = require('node:assert/strict');
const { transferBattleLoot } = require('../caesar-shared/battle-loot');

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

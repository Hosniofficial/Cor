const { transferBattleLoot } = require('./battle-loot');
const { getRomanCommanderName } = require('./battle-mechanics');

const BATTLE_WRITE_BATCH_SIZE = 100;
const ARMY_SLOTS = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];

function calculateCityConquestLoyalty(currentLoyalty, isVictory, attackTask, unitType) {
    if (!isVictory || Number(attackTask) !== 0 || Number(unitType) !== 60) return null;
    return Math.max(0, (Number(currentLoyalty) || 0) - 5);
}

async function applyCityConquest(db, options) {
    const {
        targetCity, isVictory, attackTask, unitType,
        attackerPlayerId, sovereignCityId, now
    } = options;
    if (!targetCity || Number(targetCity.id_player) === Number(attackerPlayerId)) return null;

    const loyalty = calculateCityConquestLoyalty(
        targetCity.loy,
        isVictory,
        attackTask,
        unitType
    );
    if (loyalty === null) return null;

    const targetCityId = Number(targetCity.id_city);
    const [lockedTargetCities] = await db.query(
        'SELECT id_city, id_player, loy, loy_max, dis_loy FROM city WHERE id_city = ? FOR UPDATE',
        [targetCityId]
    );
    const lockedTargetCity = lockedTargetCities[0];
    if (!lockedTargetCity || Number(lockedTargetCity.id_player) === Number(attackerPlayerId)) return null;

    const [existingTributaries] = await db.query(
        'SELECT id_city FROM city_tributary WHERE id_city = ? FOR UPDATE',
        [targetCityId]
    );
    if (existingTributaries.length) return null;

    const nextLoyalty = calculateCityConquestLoyalty(
        lockedTargetCity.loy,
        isVictory,
        attackTask,
        unitType
    );
    if (nextLoyalty === null) return null;

    const subordinated = nextLoyalty === 0;
    const newLoyalty = subordinated
        ? Math.max(1, Number(lockedTargetCity.loy_max) || 90)
        : nextLoyalty;
    if (subordinated) {
        await db.query(
            'INSERT INTO city_tributary ' +
            '(id_city, id_sovereign_city, colonized_at, last_tribute_at) VALUES (?, ?, ?, ?)',
            [targetCityId, sovereignCityId, now, now]
        );
        await db.query(
            'INSERT INTO city_tributary_balance (id_sovereign_city) VALUES (?) ' +
            'ON DUPLICATE KEY UPDATE id_sovereign_city = VALUES(id_sovereign_city)',
            [sovereignCityId]
        );
    }

    await db.query(
        'UPDATE city SET loy = ?, dis_loy = ?, loy_lss = ? WHERE id_city = ?',
        [newLoyalty, subordinated ? 0 : Number(lockedTargetCity.dis_loy) || 0, now, targetCityId]
    );

    return {
        idCity: targetCityId,
        idSovereignCity: Number(sovereignCityId),
        idOwner: Number(lockedTargetCity.id_player),
        loyalty: newLoyalty,
        subordinated
    };
}

async function updateMemberArmies(db, members) {
    const armyColumns = ARMY_SLOTS.flatMap(slot => [`${slot}_type`, `${slot}_num`]);
    const rows = members.map(member => ({
            heroId: member.hero.id_hero,
            playerId: member.player.id_player,
            values: ARMY_SLOTS.flatMap(slot => {
                const amount = member.remainingArmy[slot];
                const type = amount > 0 ? Number(member.army[`${slot}_type`]) || 0 : 0;
                return [type, amount];
            })
    }));
    const columns = ['id_hero', 'id_player', ...armyColumns];
    const rowPlaceholders = `(${columns.map(() => '?').join(', ')})`;
    const updates = armyColumns.map(column => `${column} = VALUES(${column})`).join(', ');
    for (let index = 0; index < rows.length; index += BATTLE_WRITE_BATCH_SIZE) {
        const batch = rows.slice(index, index + BATTLE_WRITE_BATCH_SIZE);
        const values = batch.flatMap(row => [row.heroId, row.playerId, ...row.values]);
        await db.query(
            `INSERT INTO hero_army (${columns.join(', ')}) VALUES ${batch.map(() => rowPlaceholders).join(', ')} ` +
            `ON DUPLICATE KEY UPDATE ${updates}`,
            values
        );
    }
}

async function updateWoundedByCity(db, members) {
    const woundedByCity = new Map();
    for (const member of members) {
        const wounded = member.woundedTroops;
        if (!wounded || !Object.values(wounded).some(amount => amount > 0)) continue;
        const cityId = member.city.id_city;
        if (!woundedByCity.has(cityId)) {
            woundedByCity.set(cityId, { id_city: cityId, army_a: 0, army_b: 0, army_c: 0, army_d: 0, army_e: 0, army_f: 0 });
        }
        const total = woundedByCity.get(cityId);
        for (const column of ['army_a', 'army_b', 'army_c', 'army_d', 'army_e', 'army_f']) {
            total[column] += Number(wounded[column]) || 0;
        }
    }
    const rows = [...woundedByCity.values()];
    const columns = ['id_city', 'army_a', 'army_b', 'army_c', 'army_d', 'army_e', 'army_f'];
    const rowPlaceholders = `(${columns.map(() => '?').join(', ')})`;
    const updates = columns.slice(1)
        .map(column => `${column} = ${column} + VALUES(${column})`)
        .join(', ');
    for (let index = 0; index < rows.length; index += BATTLE_WRITE_BATCH_SIZE) {
        const batch = rows.slice(index, index + BATTLE_WRITE_BATCH_SIZE);
        await db.query(
            `INSERT INTO city_wounded (${columns.join(', ')}) VALUES ${batch.map(() => rowPlaceholders).join(', ')} ` +
            `ON DUPLICATE KEY UPDATE ${updates}`,
            batch.flatMap(row => columns.map(column => row[column]))
        );
    }
}

async function applyMemberRewards(db, rewards) {
    for (let index = 0; index < rewards.length; index += BATTLE_WRITE_BATCH_SIZE) {
        const batch = rewards.slice(index, index + BATTLE_WRITE_BATCH_SIZE);
        const heroCases = batch.map(() => 'WHEN ? THEN ?').join(' ');
        const heroIds = batch.map(reward => reward.member.hero.id_hero);
        const heroParams = batch.flatMap(reward => [
            reward.member.hero.id_hero,
            reward.expGained
        ]);
        await db.query(
            `UPDATE city_hero SET exp = exp + CASE id_hero ${heroCases} ELSE 0 END, ` +
            `power = LEAST(power_max, power + 5) ` +
            `WHERE id_hero IN (${heroIds.map(() => '?').join(', ')})`,
            [...heroParams, ...heroIds]
        );
    }

    const players = new Map();
    const guilds = new Map();
    for (const reward of rewards) {
        const member = reward.member;
        const playerId = Number(member.player.id_player);
        if (!players.has(playerId)) players.set(playerId, { id: playerId, honor: 0 });
        const player = players.get(playerId);
        player.honor += reward.honorGained;
        const guildId = Number(member.player.id_guild) || 0;
        if (guildId > 0) {
            if (!guilds.has(guildId)) guilds.set(guildId, { id: guildId, honor: 0 });
            const guild = guilds.get(guildId);
            guild.honor += reward.honorGained;
        }
    }

    for (const [table, records] of [['player', [...players.values()]], ['guild', [...guilds.values()]]]) {
        for (let index = 0; index < records.length; index += BATTLE_WRITE_BATCH_SIZE) {
            const batch = records.slice(index, index + BATTLE_WRITE_BATCH_SIZE)
                .filter(record => record.honor);
            if (!batch.length) continue;
            const ids = batch.map(record => record.id);
            const honorCases = batch.map(() => 'WHEN ? THEN ?').join(' ');
            const params = [
                ...batch.flatMap(record => [record.id, record.honor]),
                ...ids
            ];
            await db.query(
                `UPDATE ${table} SET honor = honor + CASE id_${table} ${honorCases} ELSE 0 END ` +
                `WHERE id_${table} IN (${ids.map(() => '?').join(', ')})`,
                params
            );
        }
    }
}

async function enlistCityGarrisonDefenders(db, {
    battleId,
    cityId,
    battleArrival,
    now,
    maxDefenders = 250
}) {
    const [countRows] = await db.query(
        "SELECT COUNT(*) AS amount FROM world_battel_member " +
        "WHERE id_battel = ? AND side = 0 AND state IN ('marching', 'won', 'lost')",
        [battleId]
    );
    const availableSlots = Math.max(0, maxDefenders - Number(countRows[0] && countRows[0].amount || 0));
    if (!availableSlots) return [];

    const [garrisons] = await db.query(
        "SELECT g.id_hero, g.id_player, g.ord, h.id_city AS home_city_id, h.in_city, " +
        "home.x AS home_x, home.y AS home_y, " +
        "a.f_1_num, a.f_2_num, a.f_3_num, a.b_1_num, a.b_2_num, a.b_3_num " +
        "FROM city_garrison g " +
        "JOIN city_hero h ON h.id_hero = g.id_hero AND h.id_player = g.id_player " +
        "JOIN city home ON home.id_city = h.id_city " +
        "JOIN hero_army a ON a.id_hero = h.id_hero " +
        "WHERE g.id_city = ? AND g.status = 'stationed' AND h.in_city IN (0, 1) " +
        "AND NOT EXISTS (" +
        "SELECT 1 FROM world_battel_member active_member " +
        "WHERE active_member.id_hero = g.id_hero " +
        "AND active_member.state IN ('marching', 'won', 'lost') " +
        "AND (active_member.time_back = 0 OR active_member.time_back > ?)" +
        ") ORDER BY g.ord ASC, g.id_hero ASC FOR UPDATE",
        [cityId, now]
    );
    const enlisted = [];
    for (const garrison of garrisons) {
        const troopCount = ['f_1_num', 'f_2_num', 'f_3_num', 'b_1_num', 'b_2_num', 'b_3_num']
            .reduce((sum, slot) => sum + Math.max(0, Number(garrison[slot]) || 0), 0);
        if (!troopCount) continue;

        if (Number(garrison.in_city) === 1) {
            const [heroUpdate] = await db.query(
                'UPDATE city_hero SET in_city = 0 WHERE id_hero = ? AND id_player = ? AND in_city = 1',
                [garrison.id_hero, garrison.id_player]
            );
            if (!heroUpdate.affectedRows) continue;
        }

        await db.query(
            "INSERT INTO world_battel_member " +
            "(id_battel, id_player, id_city, id_hero, side, task, x_city, y_city, " +
            "time_start, time_arrive, return_duration, state) " +
            "VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?, ?, 1, 'marching')",
            [
                battleId,
                garrison.id_player,
                garrison.home_city_id,
                garrison.id_hero,
                garrison.home_x,
                garrison.home_y,
                now,
                battleArrival
            ]
        );
        await db.query(
            "UPDATE city_garrison SET status = 'engaged', battle_id = ? " +
            "WHERE id_hero = ? AND status = 'stationed'",
            [battleId, garrison.id_hero]
        );
        enlisted.push(Number(garrison.id_hero));
        if (enlisted.length >= availableSlots) break;
    }

    return enlisted;
}

async function resolveBattle(context, dependencies) {
    const {
        battelId, idHero, battleStartTime, battleArrivalTime, returnDuration, unitType, unitSubtype, unitLvl, unitTitle,
        attackTask, xCoord, yCoord, targetCityRows, isBarray, unitTypeData,
        baseUnit, hero, player, city
    } = context;
    const {
        getDb, worldUnits, unitData, unitArmy, prizeData, equipPowerMap,
        liveServerConfig, SOL_STATS, aggregateMemberArmy,
        reportArmySlots, creditCityResources, advanceWorldUnitLevel,
        allocateProportionally, calculateArmyPower, didBattleSideWin,
        calculateArmyCapacity, calculateArmyCasualties,
        calculateKillBasedRewards, simulateTacticalCombat,
        hasActiveBattleAt, publishBroadcast,
        publishToPlayer, saveWsBattleReport
    } = dependencies;
    const pendingBroadcasts = [];
    const pendingPlayerMessages = [];
    const pendingBattleReports = [];
    const broadcast = message => pendingBroadcasts.push(message);
    const sendToPlayer = (playerId, message) => pendingPlayerMessages.push([playerId, message]);
    const battlePool = await getDb();
    const battleDb = await battlePool.getConnection();
    let transactionOpen = false;
    let advancedWorldUnitLevel = null;
    try {
        await battleDb.beginTransaction();
        transactionOpen = true;
        
        const [bCheck] = await battleDb.query(
            "SELECT state, time_arrive FROM world_battel WHERE id_battel = ? FOR UPDATE",
            [battelId]
        );
        if (!bCheck[0] || bCheck[0].state !== 'marching') return;

        if (Number(unitType) === 60 && targetCityRows.length) {
            await enlistCityGarrisonDefenders(battleDb, {
                battleId: battelId,
                cityId: Number(targetCityRows[0].id_city),
                battleArrival: Number(bCheck[0].time_arrive),
                now: Math.floor(Date.now() / 1000)
            });
        }
        
        // ── COMBAT RESOLUTION ENGINE ──
        const slots = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
        const [memberRows] = await battleDb.query(
            "SELECT m.id_member, m.id_player, m.id_city, m.id_hero, m.side, m.task, " +
            "m.x_city, m.y_city, m.return_duration, h.name AS hero_name, h.avatar AS hero_avatar, " +
            "h.lvl AS hero_lvl, h.power AS hero_power, h.power_max AS hero_power_max, " +
            "h.point_b, h.point_b_plus, h.point_c, h.point_c_plus, " +
            "p.name AS player_name, p.id_guild AS player_id_guild, p.city_flag, p.prestige AS player_prestige, " +
            "c.name AS city_name, c.x AS city_x, c.y AS city_y " +
            "FROM world_battel_member m " +
            "JOIN city_hero h ON h.id_hero = m.id_hero AND h.id_player = m.id_player " +
            "JOIN player p ON p.id_player = m.id_player " +
            "JOIN city c ON c.id_city = m.id_city " +
            "LEFT JOIN city_garrison g ON g.id_hero = m.id_hero " +
            "AND g.battle_id = m.id_battel AND g.status = 'engaged' " +
            "WHERE m.id_battel = ? AND m.state = 'marching' " +
            "AND m.time_arrive <= (SELECT time_arrive FROM world_battel WHERE id_battel = ?) " +
            "ORDER BY CASE WHEN m.side = 0 THEN 0 ELSE 1 END, " +
            "CASE WHEN m.side = 0 THEN COALESCE(g.ord, 2147483647) ELSE m.id_member END, " +
            "m.id_member ASC",
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
        const [memberMedals] = await battleDb.query(
            `SELECT id_hero, medal_den, medal_leo FROM hero_medal WHERE id_hero IN (${memberPlaceholders})`,
            memberIds
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
        const medalsByHero = new Map(memberMedals.map(medal => [Number(medal.id_hero), medal]));
        const now = Math.floor(Date.now() / 1000);
        const battleMembers = memberRows.map(member => {
            const heroMedals = medalsByHero.get(Number(member.id_hero)) || {};
            const equipmentBonuses = equipsByHero.get(Number(member.id_hero)) || [];
            const memberHero = {
                id_hero: Number(member.id_hero),
                id_player: Number(member.id_player),
                name: member.hero_name,
                avatar: Number(member.hero_avatar) || 1,
                lvl: Number(member.hero_lvl) || 1,
                power: Number(member.hero_power) || 0,
                power_max: Number(member.hero_power_max) || 0,
                point_b: Number(member.point_b) || 0,
                point_b_plus: Number(member.point_b_plus) || 0,
                point_c: Number(member.point_c) || 0,
                point_c_plus: Number(member.point_c_plus) || 0,
                medalDenActive: Number(heroMedals.medal_den) > now,
                medalLeoActive: Number(heroMedals.medal_leo) > now
            };
            const memberPlayer = {
                id_player: Number(member.id_player),
                name: member.player_name,
                id_guild: Number(member.player_id_guild) || 0,
                city_flag: member.city_flag,
                prestige: Number(member.player_prestige) || 0
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
                equipmentBonuses,
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

        const npcDefenderTroops = Object.fromEntries(
            Object.entries(defenderTroops)
                .filter(([type]) => Number.isSafeInteger(Number(type)) && Number(type) > 0)
                .map(([type, amount]) => [Number(type), Number(amount) || 0])
        );
        const allDefenderTroops = { ...npcDefenderTroops };
        for (const member of joinedDefenders) {
            for (const slot of slots) {
                const type = Number(member.army[`${slot}_type`]) || 0;
                const amount = Math.max(0, Number(member.army[`${slot}_num`]) || 0);
                if (type > 0 && amount > 0) {
                    allDefenderTroops[type] = (Number(allDefenderTroops[type]) || 0) + amount;
                }
            }
        }
        let enemyEquipBonus = 0;
        if (unitType >= 97 && unitType <= 105) {
            enemyEquipBonus = unitLvl * 300;
        } else if (unitType >= 70 && unitType <= 96) {
            enemyEquipBonus = unitLvl * 150;
        } else if (unitType >= 150 && unitType <= 236) {
            enemyEquipBonus = unitLvl * 600;
        }
        const combatResult = simulateTacticalCombat(attackers, allDefenderTroops, {
            solStats: SOL_STATS,
            commanderAttackBonus: enemyEquipBonus,
            npcCombatPowerMultiplier: 1 + unitLvl * 0.05,
            npcDefenderTroops,
            defendingMembers: joinedDefenders,
            wallFortifications: {
                traps: Number(targetCityRows[0] && targetCityRows[0].wall_a) || 0,
                towers: Number(targetCityRows[0] && targetCityRows[0].wall_b) || 0,
                stones: Number(targetCityRows[0] && targetCityRows[0].wall_c) || 0
            }
        });
        const simulatedAttackerArmies = new Map(
            combatResult.remainingAttackers.map(result => [result.member.id_member, result.army])
        );
        const simulatedDefenderArmies = new Map(
            combatResult.remainingDefenders.map(result => [result.member.id_member, result.army])
        );
        
        // 3. Calculate Defender Combat Power (with joined defenders)
        let defPower = 0;
        for (const [type, num] of Object.entries(npcDefenderTroops)) {
            const t = parseInt(type, 10);
            const n = parseInt(num, 10) || 0;
            if (t > 0 && n > 0 && SOL_STATS[t]) {
                defPower += (SOL_STATS[t].att * 0.6 + SOL_STATS[t].hp * 0.4) * n;
            }
        }
        defPower *= (1.0 + unitLvl * 0.05);
        defPower += joinedDefenders.reduce((total, member) => total + member.combatPower, 0);
        
        // Enemy commander equipment & power boost
        defPower += enemyEquipBonus;
        
        // 4. Compare Power & Determine Victory vs Defeat
        const ratio = attPower / Math.max(1, defPower);
        const isVictory = combatResult.attackersWon;
        
        // 5. Calculate loss rate and recovery mechanics using the game rules.
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
            const configuredWoundedRate = Number(liveServerConfig.wounded_recovery_rate);
            const woundedRate = memberWon && Number.isFinite(configuredWoundedRate)
                ? Math.max(0, Math.min(1, configuredWoundedRate))
                : memberWon
                    ? Math.min(0.60, 0.10 + member.medicineLevel * 0.05)
                    : 0;
            const casualties = calculateArmyCasualties(
                member.army,
                memberLossRate,
                memberWon ? recoveryRate : 0,
                woundedRate,
                memberWon,
                member.side === 1
                    ? simulatedAttackerArmies.get(member.id_member)
                    : simulatedDefenderArmies.get(member.id_member)
            );
            member.casualties = casualties;
            member.remainingArmy = {};
            const survivingArmy = {};
            for (const slot of slots) {
                const result = casualties.remainingArmy[slot];
                member.remainingArmy[slot] = result.amount;
                survivingArmy[`${slot}_type`] = result.type;
                survivingArmy[`${slot}_num`] = result.amount;
            }
            member.cargoCapacity = calculateArmyCapacity(survivingArmy);
        
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
        await updateMemberArmies(battleDb, battleMembers);
        await updateWoundedByCity(battleDb, battleMembers);
        const joinedDefenderWoundedByType = {};
        for (const member of joinedDefenders) {
            const woundedBySlot = member.casualties && member.casualties.woundedBySlot || {};
            for (const slot of slots) {
                const type = Number(member.army[`${slot}_type`]) || 0;
                const wounded = Number(woundedBySlot[slot]) || 0;
                if (!type || !wounded) continue;
                joinedDefenderWoundedByType[type] = (joinedDefenderWoundedByType[type] || 0) + wounded;
            }
        }
        const opposingDefenseReport = Object.fromEntries(
            Object.entries(allDefenderTroops).map(([type, amount]) => [
                type,
                {
                    pre: Number(amount) || 0,
                    cur: Number(combatResult.livingDefenders[type]) || 0,
                    injured: Number(joinedDefenderWoundedByType[type]) || 0
                }
            ])
        );
        const attackReport = aggregateMemberArmy(attackers);
        const hostMember = battleMembers.find(member => member.hero.id_hero === Number(idHero));
        if (!hostMember) {
            throw new Error(`Battle ${battelId} is missing the initiating hero`);
        }
        let defenderPrestige = 0;
        const defenderPlayerId = Number(targetCityRows[0] && targetCityRows[0].id_player) || 0;
        if (defenderPlayerId > 0) {
            const [defenderPlayers] = await battleDb.query(
                "SELECT prestige FROM player WHERE id_player = ?",
                [defenderPlayerId]
            );
            defenderPrestige = Number(defenderPlayers[0] && defenderPlayers[0].prestige) || 0;
        }
        const attackingCasualties = casualtiesBySide[1];
        const averageAttackerPrestige = attackers.reduce(
            (total, member) => total + (Number(member.player.prestige) || 0),
            0
        ) / attackers.length;
        const applySideRewards = async (members, sideWon, opponentPrestige) => {
            if (!members.length) return [];
            const rewards = calculateKillBasedRewards({
                attackers: members,
                isVictory: sideWon,
                targetType: unitType,
                targetLvl: unitLvl,
                defenderPrestige: opponentPrestige
            });
            for (const reward of rewards) {
                const member = reward.member;
                member.expGained = reward.expGained;
                member.honorGained = reward.honorGained;
                member.itemLoot = [];
            }
            await applyMemberRewards(battleDb, rewards);
            return rewards;
        };
        const attackerRewards = await applySideRewards(attackers, isVictory, defenderPrestige);
        await applySideRewards(joinedDefenders, !isVictory, averageAttackerPrestige);
        const honorGained = attackerRewards.reduce((sum, reward) => sum + reward.honorGained, 0);

        let cityColonization = null;
        if (isVictory) {
            // ── VICTORY RESOLUTION ──
            if (attackTask === 0 && targetCityRows.length) {
                cityColonization = await applyCityConquest(battleDb, {
                    targetCity: targetCityRows[0],
                    isVictory,
                    attackTask,
                    unitType,
                    attackerPlayerId: Number(player.id_player),
                    sovereignCityId: Number(city.id_city),
                    now: Math.floor(Date.now() / 1000)
                });
                if (cityColonization) {
                    const updateMessage = JSON.stringify({
                        classPath: 'City.TributaryUpdated',
                        ...cityColonization
                    });
                    sendToPlayer(cityColonization.idOwner, updateMessage);
                    sendToPlayer(Number(player.id_player), updateMessage);
                }
            }
            if (!targetCityRows.length && !isBarray && unitTypeData.lvlChange === true
                && Number(unitTypeData.maxLvl) > unitLvl) {
                const baseUnit = worldUnits.get(`${xCoord}_${yCoord}`);
                if (baseUnit) {
                    advancedWorldUnitLevel = await advanceWorldUnitLevel(battleDb, {
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
            const loot = !targetCityRows.length && unitType >= 1 && unitType <= 63
                ? Math.min(100000, Math.max(2000, unitLvl * 3500))
                : 0;
            const wildernessLoot = {
                food: loot,
                wood: loot,
                stone: loot,
                metal: loot,
                coin: loot * 2
            };
            const { creditedLoot, cityUpdates } = await transferBattleLoot({
                db: battleDb,
                targetCity: targetCityRows[0] || null,
                attackers,
                wildernessLoot,
                creditCityResources
            });
            for (const update of cityUpdates) {
                sendToPlayer(update.idPlayer, JSON.stringify({
                    classPath: 'Base.CityResourcesUpdated',
                    idCity: update.idCity,
                    resources: update.resources
                }));
            }
        
            // Participant rewards were applied from per-hero kill scores before branch-specific loot.
            for (const member of battleMembers) {
                if (!member.itemLoot) member.itemLoot = [];
            }
        
            // 6c. Prize Drop Tables Evaluation (with Dynamic Multipliers & Wilderness Luxuries)
            const droppedItems = [];
            const winPrizes = prizeData.Win ? prizeData.Win.filter(x => x.unitType == unitType && x.lvl == unitLvl) : [];
            const eventMultipliers = liveServerConfig.event_multipliers || {};
            const globalMult = Math.max(0.1, Number(liveServerConfig.global_drop_rate_multiplier) || 1.0)
                * Math.max(1, Number(eventMultipliers.global) || 1);
            const luxuryMult = Math.max(0.1, Number(liveServerConfig.wilderness_luxury_drop_multiplier) || 1.0)
                * Math.max(1, Number(eventMultipliers.luxury) || 1);
            const luxuryKeys = ['luxury_1', 'luxury_2', 'bread', 'necklace_1', 'necklace_2', 'necklace_3', 'necklace_4', 'necklace_5', 't_map'];
        
            for (const p of winPrizes) {
                let effectiveRate = Math.min(1000, Number(p.win_rate) * globalMult);
                if (luxuryKeys.includes(p.prize)) {
                    effectiveRate = Math.min(1000, effectiveRate * luxuryMult);
                }
                if (Math.random() * 1000 <= effectiveRate) {
                    const amt = Math.floor(Math.random() * (p.amount_max - p.amount_min + 1)) + p.amount_min;
                    if (amt > 0) {
                        droppedItems.push({ prize: p.prize, amount: amt });
                    }
                }
            }
            for (const drop of droppedItems) {
                const prizeShares = unitData[unitType] && unitData[unitType].lvlChange === true
                    ? [{ member: hostMember, amount: drop.amount }]
                    : allocateProportionally(drop.amount, attackers);
                for (const share of prizeShares) {
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
            const customEquipChance = (Number(liveServerConfig.equipment_drop_chance_percent) || 25) / 100
                * Math.max(1, Number(eventMultipliers.equipment) || 1);
            if (unitType >= 97 && unitType <= 105 && unitLvl >= 3) {
                equipDropChance = Math.min(1, customEquipChance); // e.g. 25% on Carthage & Gangs lvl 3+
                possibleEquipTypes = ['mili', 'peace', 'victory', 'mars'];
            } else if (unitType >= 70 && unitType <= 96 && unitLvl >= 3) {
                equipDropChance = Math.min(1, customEquipChance * 0.8); // e.g. on Camps & Maneuvers lvl 3+
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
                const equipRecipient = unitData[unitType] && unitData[unitType].lvlChange === true
                    ? hostMember
                    : allocateProportionally(1, attackers).find(share => share.amount > 0).member;
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
                const [pBuilding] = await battleDb.query(
                    "SELECT COALESCE(MAX(lvl), 0) AS lvl FROM city_building " +
                    "WHERE id_city = ? AND (type = 12 OR place = 'palace')",
                    [city.id_city]
                );
                const palaceLvl = Math.max(0, Number(pBuilding && pBuilding[0] && pBuilding[0].lvl) || 0);
                const maxWilds = Math.min(10, Math.max(1, palaceLvl));
        
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
                        msg: `🏆 انتصار مجيد! سحق البطل [${hero.name}] قوات [${unitTitle} مستوى ${unitLvl}] (${targetCategoryName}). ${lossReport}${woundedSummary}، غنائم الموارد: ${Object.entries(creditedLoot).map(([resource, amount]) => `${resource} ${amount}`).join('، ')}، +${honorGained} شرف${itemsSummary}${equipSummary}!${dominated ? ' 🚩 تم الاستيلاء على البرية وضمها للمدينة بنجاح!' : ''}${cityColonization ? (cityColonization.subordinated ? ' أصبحت المدينة تابعة لمدينتك السيادية مع بقاء ملكيتها لصاحبها الأصلي.' : ` انخفض ولاء المدينة إلى ${cityColonization.loyalty}%.`) : ''}`,
                        time: Math.floor(Date.now() / 1000)
                    }
                }));
            }
        
        } else {
            // ── DEFEAT RESOLUTION ──
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
                memberWon,
                member.casualties && member.casualties.woundedBySlot
            );
            pendingBattleReports.push({
                player: member.player,
                hero: member.hero,
                battleId: battelId,
                battleStartTime,
                battleArrivalTime,
                returnDuration: Number(member.returnDuration) || returnDuration,
                xCoord,
                yCoord,
                unitType,
                unitLvl,
                attackTask: member.task,
                isVictory,
                expGained: member.expGained,
                honorGained: member.honorGained,
                roundCount: combatResult.roundsCount,
                roundsLog: combatResult.roundsLog,
                killsByType: member.killsByType,
                resourceLoot: member.resourceLoot || { food: 0, wood: 0, stone: 0, metal: 0, coin: 0 },
                itemLoot: member.itemLoot,
                attackerSlots: memberSlots.attackerSlots,
                defenderSlots: memberSlots.defenderSlots,
                writeBattleLog: member.hero.id_hero === Number(idHero),
                heroSide: member.side,
                opponentName: member.side === 1
                    ? targetCityRows.length
                        ? 'حامية الأعداء'
                        : getRomanCommanderName(unitType, unitLvl, xCoord, yCoord)
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
        

        await battleDb.commit();
        transactionOpen = false;
        await Promise.all(pendingBattleReports.map(async report => {
            try {
                await saveWsBattleReport(battlePool, report);
            } catch (reportError) {
                console.error(`[Battle Report Error] Hero ${report.hero.id_hero}:`, reportError.message);
            }
        }));
    } catch (error) {
        if (transactionOpen) await battleDb.rollback();
        throw error;
    } finally {
        if (transactionOpen) await battleDb.rollback();
        battleDb.release();
    }
    if (advancedWorldUnitLevel !== null) {
        const mapUnit = worldUnits.get(`${xCoord}_${yCoord}`);
        if (mapUnit && Number(mapUnit.ut) === Number(unitType)) {
            mapUnit.l = advancedWorldUnitLevel;
            publishBroadcast(JSON.stringify({
                classPath: 'World.RefereshWorldUnit',
                data: { WorldUnits: [{ x: xCoord, y: yCoord, t: Number(baseUnit && baseUnit.t) || 0, ut: unitType, l: advancedWorldUnitLevel }] }
            }));
        }
    }
    for (const message of pendingBroadcasts) publishBroadcast(message);
    for (const [playerId, message] of pendingPlayerMessages) publishToPlayer(playerId, message);
}

module.exports = {
    applyCityConquest,
    calculateCityConquestLoyalty,
    enlistCityGarrisonDefenders,
    resolveBattle
};

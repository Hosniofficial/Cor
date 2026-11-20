const ARMY_SLOTS = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
const ARMY_CAPACITY = { 1: 40, 2: 100, 3: 120, 4: 25, 5: 35, 6: 75, 7: 1 };
const UNIT_EXP_WEIGHTS = { 1: 4, 2: 18, 3: 36, 4: 5, 5: 20, 6: 150, 7: 6 };
const NPC_DEFENDER_SLOT_ORDER = [1, 2, 4, 3, 5, 6, 7];

function calculateMemberKillScore(killsByType) {
    return Object.entries(killsByType || {}).reduce((score, [type, count]) => {
        const numericType = Number(type);
        const numericCount = Number(count);
        if (!Number.isSafeInteger(numericCount) || numericCount < 0) {
            throw new TypeError('Kill counts must be non-negative safe integers');
        }
        const nextScore = score + numericCount * (UNIT_EXP_WEIGHTS[numericType] || 0);
        if (!Number.isSafeInteger(nextScore)) {
            throw new RangeError('Weighted kill score exceeds the safe integer range');
        }
        return nextScore;
    }, 0);
}

function distributeMemberTroopLosses(members, lossesByType) {
    const remaining = new Map(members.map(member => [
        member.id_member,
        Object.fromEntries(ARMY_SLOTS.map(slot => [
            slot,
            Math.max(0, Number(member.army[`${slot}_num`]) || 0)
        ]))
    ]));
    const slotsByType = new Map();
    for (const member of members) {
        for (const slot of ARMY_SLOTS) {
            const type = Number(member.army[`${slot}_type`]) || 0;
            const amount = Number(member.army[`${slot}_num`]) || 0;
            if (!type || amount <= 0) continue;
            if (!slotsByType.has(type)) slotsByType.set(type, []);
            slotsByType.get(type).push({ member, slot, amount });
        }
    }

    for (const [type, rawLosses] of Object.entries(lossesByType || {})) {
        const numericType = Number(type);
        const amount = Number(rawLosses);
        if (!Number.isSafeInteger(amount) || amount < 0) {
            throw new TypeError('Troop losses must be non-negative safe integers');
        }
        const eligible = slotsByType.get(numericType) || [];
        const total = eligible.reduce((sum, entry) => sum + entry.amount, 0);
        if (!total || !amount) continue;
        const shares = allocateProportionally(
            Math.min(total, amount),
            eligible,
            entry => entry.amount
        );
        for (const share of shares) {
            const entry = share.member;
            const memberArmy = remaining.get(entry.member.id_member);
            memberArmy[entry.slot] = Math.max(0, memberArmy[entry.slot] - share.amount);
        }
    }
    return remaining;
}

function simulateTacticalCombat(attackers, defenderTroops, options = {}) {
    const soldierStats = options.solStats || {};
    const walls = options.wallFortifications || {};
    const traps = Math.max(0, Number(walls.traps) || 0);
    const towerDamage = Math.max(0, Number(walls.towers) || 0) * 18;
    const stoneDamage = Math.max(0, Number(walls.stones) || 0) * 50;
    const commanderAttackBonus = Math.max(0, Number(options.commanderAttackBonus) || 0);
    const defendingMembers = options.defendingMembers || [];
    const npcAttackMultiplier = Math.max(0, Number(options.npcAttackMultiplier) || 1);
    const npcCombatPowerMultiplier = Math.max(0, Number(options.npcCombatPowerMultiplier) || 1);
    const livingNpcDefenders = Object.fromEntries(
        Object.entries(options.npcDefenderTroops || defenderTroops || {}).map(([type, count]) => [
            Number(type),
            validateTroopCount(count, 'NPC defender')
        ])
    );
    const maxRounds = options.maxRounds === undefined
        ? Number.POSITIVE_INFINITY
        : Math.max(1, Math.floor(Number(options.maxRounds) || 1));
    const livingNpcDefenderCounts = { ...livingNpcDefenders };
    const createMemberState = (member, index) => {
        member.killsByType = Object.fromEntries(
            Object.keys(UNIT_EXP_WEIGHTS).map(type => [type, 0])
        );
        return {
            member,
            id: Number.isSafeInteger(Number(member.id_member)) && Number(member.id_member) > 0
                ? Number(member.id_member)
                : Number.isSafeInteger(Number(member.hero.id_hero)) && Number(member.hero.id_hero) > 0
                    ? Number(member.hero.id_hero)
                    : index,
            army: Object.fromEntries(ARMY_SLOTS.map(slot => [
                slot,
                {
                    type: Number(member.army[`${slot}_type`]) || 0,
                    amount: validateTroopCount(member.army[`${slot}_num`] || 0, 'Participant')
                }
            ])),
            combat: getMemberCombatProfile(member)
        };
    };
    const sortByStableMemberId = (left, right) => left.id - right.id;
    const livingAttackers = attackers.map(createMemberState).sort(sortByStableMemberId);
    const livingDefenderMembers = defendingMembers.map(createMemberState).sort(sortByStableMemberId);
    const roundsLog = [];
    for (const state of [...livingAttackers, ...livingDefenderMembers]) {
        const equipmentBonuses = state.combat.equipmentBonuses;
        if (!equipmentBonuses.length) continue;
        const equipment = getEquipmentCombatStats(state.member);
        const side = state.member.side === 1 ? 'المهاجم' : 'المدافع';
        roundsLog.push(
            `معدات بطل ${side} (${equipmentBonuses.length} قطع مجهزة) منحت الجيش تعزيزات حربية: ` +
            `هجوم +${equipment.attack}، دفاع +${equipment.defence}، حيوية +${equipment.vitality}، ضرر +${equipment.damage}.`
        );
    }

    let roundsCount = 0;
    for (let round = 1; round <= maxRounds; round++) {
        const defendersAlive = getTotalTroopCount(livingDefenderMembers, livingNpcDefenderCounts);
        const attackersAlive = getTotalTroopCount(livingAttackers);
        if (defendersAlive <= 0 || attackersAlive <= 0) break;
        roundsCount = round;
        let trapKills = {};
        if (round === 1 && traps > 0) {
            const trapDamage = Math.min(traps * 50, Math.floor(attackersAlive * 25));
            trapKills = applyDamageToMembers(trapDamage, livingAttackers, soldierStats);
        }

        const attackerPower = getMemberAttackPowers(livingAttackers, soldierStats);
        const defenderPower = getMemberAttackPowers(livingDefenderMembers, soldierStats);
        const npcAttackPower = getNpcAttackPower(livingNpcDefenderCounts, soldierStats) * npcAttackMultiplier;
        const attackDamage = attackerPower.total;
        const defenseDamage = npcAttackPower
            + defenderPower.total
            + commanderAttackBonus
            + towerDamage
            + stoneDamage;

        const attackerKills = getTeamCasualties(
            attackDamage,
            livingDefenderMembers,
            livingNpcDefenderCounts,
            soldierStats,
            true,
            true
        );
        const defenderKills = getTeamCasualties(
            defenseDamage,
            livingAttackers,
            {},
            soldierStats,
            true,
            false
        );

        applyMemberCasualties(livingDefenderMembers, livingNpcDefenderCounts, attackerKills);
        applyMemberCasualties(livingAttackers, {}, defenderKills);
        allocateKillCredit(livingAttackers, attackerPower.byMember, attackerKills.byType);
        allocateKillCredit(livingDefenderMembers, defenderPower.byMember, defenderKills.byType);

        const attackerKillCount = sumTroopCounts(attackerKills.byType);
        const defenderKillCount = sumTroopCounts(defenderKills.byType);
        const trapKillCount = sumTroopCounts(trapKills);
        const wallLog = trapKillCount > 0
            ? `أوقعت كمائن السور ${trapKillCount} من المهاجمين. `
            : '';
        const attackerLossLog = formatCasualties(attackerKills.byType);
        const defenderLossLog = formatCasualties(defenderKills.byType);
        roundsLog.push(
            `الجولة ${round}: ${wallLog}خسائر المدافعين ${attackerKillCount}${attackerLossLog ? ` (${attackerLossLog})` : ''}، ` +
            `وخسائر المهاجمين ${defenderKillCount}${defenderLossLog ? ` (${defenderLossLog})` : ''}.`
        );

        if (!attackerKillCount && !defenderKillCount) {
            const attackersStillAlive = getTotalTroopCount(livingAttackers);
            const defendersStillAlive = getTotalTroopCount(livingDefenderMembers, livingNpcDefenderCounts);
            if (options.maxRounds === undefined && attackersStillAlive > 0 && defendersStillAlive > 0) {
                throw new Error('Tactical combat made no progress before either side was eliminated');
            }
            break;
        }
    }

    const remainingAttackers = livingAttackers.map(state => ({
        member: state.member,
        army: Object.fromEntries(ARMY_SLOTS.map(slot => [slot, state.army[slot].amount]))
    }));
    const remainingDefenders = livingDefenderMembers.map(state => ({
        member: state.member,
        army: Object.fromEntries(ARMY_SLOTS.map(slot => [slot, state.army[slot].amount]))
    }));
    const attackersRemaining = getTotalTroopCount(livingAttackers);
    const attackerPower = getRemainingMemberCombatPower(livingAttackers, soldierStats);
    const defendersRemaining = getTotalTroopCount(livingDefenderMembers, livingNpcDefenderCounts);
    const defenderPower = getRemainingMemberCombatPower(livingDefenderMembers, soldierStats)
        + getNpcCombatPower(livingNpcDefenderCounts, soldierStats, npcCombatPowerMultiplier)
        + commanderAttackBonus;
    return {
        attackersWon: defendersRemaining <= 0 && attackersRemaining > 0
            || options.maxRounds !== undefined
                && attackersRemaining > 0
                && defendersRemaining > 0
                && attackerPower > 0
                && attackerPower > defenderPower,
        roundsCount,
        roundsLog,
        livingDefenders: getTroopCounts(livingDefenderMembers, livingNpcDefenderCounts),
        remainingAttackers,
        remainingDefenders
    };
}

function validateTroopCount(value, owner) {
    const amount = Number(value) || 0;
    if (!Number.isSafeInteger(amount) || amount < 0) {
        throw new TypeError(`${owner} troop counts must be non-negative safe integers`);
    }
    return amount;
}

function getEquipmentCombatStats(member) {
    return (member.equipmentBonuses || []).reduce((total, bonus) => {
        for (const key of ['attack', 'defence', 'vitality', 'damage', 'break', 'anti_break', 'strike', 'immunity']) {
            const value = Number(bonus[key]) || 0;
            if (!Number.isFinite(value)) throw new TypeError('Equipment combat stats must be finite');
            total[key] += value;
        }
        return total;
    }, { attack: 0, defence: 0, vitality: 0, damage: 0, break: 0, anti_break: 0, strike: 0, immunity: 0 });
}

function getMemberCombatProfile(member) {
    const hero = member.hero || {};
    const equipment = getEquipmentCombatStats(member);
    const level = Math.max(1, Number(hero.lvl) || 1);
    let bravery = (Number(hero.point_b) || 0) + (Number(hero.point_b_plus) || 0);
    let defense = (Number(hero.point_c) || 0) + (Number(hero.point_c_plus) || 0);
    if (hero.medalDenActive) bravery += Math.ceil(bravery * 0.25);
    if (hero.medalLeoActive) defense += Math.ceil(defense * 0.25);
    return {
        attackModifier: 1 + (bravery + level * 2 + equipment.attack) / 100,
        defenseModifier: Math.max(0.01, 1 + (defense + level * 2 + equipment.defence) / 100),
        equipmentDamage: equipment.damage * 12 + equipment.break * 8 + equipment.strike * 5,
        equipmentHp: equipment.vitality * 10 + equipment.anti_break * 8 + equipment.immunity * 6,
        equipmentBonuses: member.equipmentBonuses || []
    };
}

function getMemberArmy(state) {
    return Object.fromEntries(ARMY_SLOTS.flatMap(slot => [
        [`${slot}_type`, state.army[slot].type],
        [`${slot}_num`, state.army[slot].amount]
    ]));
}

function getTotalTroopCount(members, npcTroops = {}) {
    let total = Object.values(npcTroops).reduce((sum, amount) => sum + amount, 0);
    for (const state of members) {
        for (const slot of ARMY_SLOTS) total += state.army[slot].amount;
    }
    if (!Number.isSafeInteger(total)) throw new RangeError('Total battle troop count exceeds the safe integer range');
    return total;
}

function getTroopCounts(members, npcTroops = {}) {
    const counts = { ...npcTroops };
    for (const state of members) {
        for (const slot of ARMY_SLOTS) {
            const { type, amount } = state.army[slot];
            if (type && amount) counts[type] = (counts[type] || 0) + amount;
        }
    }
    return counts;
}

function getMemberAttackPowers(members, soldierStats) {
    const byMember = new Map();
    let total = 0;
    for (const state of members) {
        let power = 0;
        let hasTroops = false;
        for (const slot of ARMY_SLOTS) {
            const { type, amount } = state.army[slot];
            const attack = Number(soldierStats[type] && soldierStats[type].att) || 0;
            if (!type || !amount || !attack) continue;
            hasTroops = true;
            power += amount * attack * state.combat.attackModifier;
        }
        if (hasTroops) power += state.combat.equipmentDamage;
        byMember.set(state, power);
        total += power;
    }
    if (!Number.isFinite(total)) throw new RangeError('Battle attack power is not finite');
    return { total, byMember };
}

function getNpcAttackPower(npcTroops, soldierStats) {
    return Object.entries(npcTroops).reduce((total, [type, amount]) => {
        return total + amount * (Number(soldierStats[type] && soldierStats[type].att) || 0);
    }, 0);
}

function getEffectiveTeamDamage(rawDamage, members, npcTroops, soldierStats) {
    if (!(rawDamage > 0)) return 0;
    let totalHealth = 0;
    let weightedDefense = 0;
    let weightedEquipmentHp = 0;
    const addProfile = (health, defenseModifier, equipmentHp) => {
        if (!health) return;
        totalHealth += health;
        weightedDefense += health * defenseModifier;
        weightedEquipmentHp += health * equipmentHp;
    };
    for (const state of members) {
        let health = 0;
        for (const slot of ARMY_SLOTS) {
            const { type, amount } = state.army[slot];
            health += amount * (Number(soldierStats[type] && soldierStats[type].hp) || 60);
        }
        addProfile(health, state.combat.defenseModifier, state.combat.equipmentHp);
    }
    for (const [type, amount] of Object.entries(npcTroops)) {
        addProfile(amount * (Number(soldierStats[type] && soldierStats[type].hp) || 60), 1, 0);
    }
    if (!totalHealth) return 0;
    const defenseModifier = weightedDefense / totalHealth;
    const equipmentReduction = weightedEquipmentHp / totalHealth * 0.05;
    return Math.max(0, rawDamage / defenseModifier - equipmentReduction);
}

function getTeamCasualties(rawDamage, members, npcTroops, soldierStats, applyDefenses = true, allowChip = false) {
    let damage = applyDefenses
        ? getEffectiveTeamDamage(rawDamage, members, npcTroops, soldierStats)
        : rawDamage;
    const casualties = {
        byType: {},
        npcByType: {},
        memberSlots: new Map()
    };
    const applyDamageToTarget = target => {
        if (target.amount <= 0 || damage <= 0) return;
        const defenderHp = Number(soldierStats[target.type] && soldierStats[target.type].hp) || 60;
        let killed = Math.min(target.amount, Math.floor(damage / defenderHp));
        if (allowChip && killed <= 0 && damage > 0) killed = 1;
        if (killed <= 0) return;

        casualties.byType[target.type] = (casualties.byType[target.type] || 0) + killed;
        if (target.member) {
            if (!casualties.memberSlots.has(target.member)) {
                casualties.memberSlots.set(target.member, {});
            }
            const memberLosses = casualties.memberSlots.get(target.member);
            memberLosses[target.slot] = (memberLosses[target.slot] || 0) + killed;
        } else {
            casualties.npcByType[target.type] = (casualties.npcByType[target.type] || 0) + killed;
        }
        damage -= killed * defenderHp;
    };

    for (const defenderType of NPC_DEFENDER_SLOT_ORDER) {
        applyDamageToTarget({
            type: defenderType,
            amount: Number(npcTroops[defenderType]) || 0
        });
    }
    for (const state of members) {
        for (const slot of ARMY_SLOTS) {
            const { type, amount } = state.army[slot];
            if (!type || amount <= 0) continue;
            applyDamageToTarget({ type, amount, member: state, slot });
        }
    }
    return casualties;
}

function applyMemberCasualties(members, npcTroops, casualties) {
    for (const [type, killed] of Object.entries(casualties.npcByType)) {
        npcTroops[type] = Math.max(0, (Number(npcTroops[type]) || 0) - killed);
    }
    for (const state of members) {
        const memberLosses = casualties.memberSlots.get(state);
        if (!memberLosses) continue;
        for (const slot of ARMY_SLOTS) {
            state.army[slot].amount = Math.max(
                0,
                state.army[slot].amount - (Number(memberLosses[slot]) || 0)
            );
        }
    }
}

function allocateKillCredit(members, attackPowerByMember, casualtiesByType) {
    const activeMembers = members.filter(state => attackPowerByMember.get(state) > 0);
    if (!activeMembers.length) return;
    for (const [type, casualties] of Object.entries(casualtiesByType)) {
        const shares = allocateProportionally(
            casualties,
            activeMembers,
            state => attackPowerByMember.get(state)
        );
        for (const share of shares) {
            share.member.member.killsByType[type] += share.amount;
        }
    }
}

function sumTroopCounts(troopsByType) {
    return Object.values(troopsByType).reduce((sum, amount) => sum + amount, 0);
}

function formatCasualties(casualtiesByType) {
    return Object.entries(casualtiesByType)
        .map(([type, amount]) => `الوحدة ${type}: ${amount}`)
        .join('، ');
}

function applyDamageToMembers(rawDamage, members, soldierStats) {
    const casualties = getTeamCasualties(rawDamage, members, {}, soldierStats, false, false);
    applyMemberCasualties(members, {}, casualties);
    return casualties.byType;
}

function getRemainingMemberCombatPower(members, soldierStats) {
    return members.reduce((total, state) => {
        return total + calculateArmyPower(
            getMemberArmy(state),
            state.member.hero,
            soldierStats,
            state.combat.equipmentBonuses
        );
    }, 0);
}

function getNpcCombatPower(npcTroops, soldierStats, multiplier) {
    const power = Object.entries(npcTroops).reduce((total, [type, count]) => {
        const stats = soldierStats[type];
        if (!stats || count <= 0) return total;
        return total + ((Number(stats.att) || 0) * 0.6 + (Number(stats.hp) || 0) * 0.4) * count;
    }, 0);
    return power * multiplier;
}

function calculateKillBasedRewards({
    attackers,
    isVictory,
    targetType,
    targetLvl,
    defenderPrestige = 0
}) {
    if (!Array.isArray(attackers) || !attackers.length) {
        throw new TypeError('Kill-based rewards require at least one attacker');
    }
    const category = targetType >= 150 && targetType <= 236
        ? { expMultiplier: 2 }
        : targetType >= 97 && targetType <= 105
            ? { expMultiplier: 1.5 }
            : targetType >= 70 && targetType <= 96
                ? { expMultiplier: 1.25 }
                : targetType >= 1 && targetType <= 59
                    ? { expMultiplier: 1 }
                    : { expMultiplier: 1 };
    attackers.reduce((sum, attacker) => {
        const nextScore = sum + calculateMemberKillScore(attacker.killsByType);
        if (!Number.isSafeInteger(nextScore)) {
            throw new RangeError('Battle kill score exceeds the safe integer range');
        }
        return nextScore;
    }, 0);
    const killScores = new Map(attackers.map((attacker, index) => [
        attacker,
        calculateMemberKillScore(attacker.killsByType)
    ]));
    const players = new Map();
    for (const [index, attacker] of attackers.entries()) {
        const idPlayer = Number(attacker.player.id_player);
        const playerKey = Number.isSafeInteger(idPlayer) && idPlayer > 0
            ? idPlayer
            : `member:${Number(attacker.id_member) || index}`;
        if (!players.has(playerKey)) players.set(playerKey, []);
        players.get(playerKey).push(attacker);
    }

    const playerRewards = new Map();
    for (const [playerKey, members] of players) {
        const playerKillScore = members.reduce((sum, member) => sum + killScores.get(member), 0);
        const player = members[0].player || {};
        let honorGained = isVictory
            ? Math.floor(playerKillScore / 2)
            : Math.floor(playerKillScore / 4);
        if (targetType >= 1 && targetType <= 59) {
            const maxKillHonor = targetLvl * 50;
            honorGained = Math.min(honorGained, maxKillHonor);
        }
        if (isVictory && members.some(member => member.task === 1) && targetType >= 1 && targetType <= 59) {
            honorGained += targetLvl * 50 + 50;
        }
        if (targetType >= 60 && targetType <= 63) {
            const attackerPrestige = Number(player.prestige) || 0;
            const targetPrestige = Number(defenderPrestige) || 0;
            if (attackerPrestige > targetPrestige) {
                const ratio = attackerPrestige > 0 ? targetPrestige / attackerPrestige : 0;
                honorGained = Math.floor(honorGained * Math.max(0, 2 * ratio - 0.5));
            }
        }

        const honorShares = allocateProportionally(
            honorGained,
            members,
            member => killScores.get(member)
        );
        members.forEach((member, index) => {
            playerRewards.set(member, {
                honorGained: honorShares[index].amount
            });
        });
    }

    return attackers.map(attacker => {
        const killScore = killScores.get(attacker);
        const bravery = (Number(attacker.hero.point_b) || 0)
            + (Number(attacker.hero.point_b_plus) || 0);
        const wisdomMultiplier = 1 + bravery / 1000;
        const expGained = isVictory
            ? Math.floor((killScore * category.expMultiplier + targetLvl * 20) * wisdomMultiplier)
            : Math.floor(killScore * 0.5 * wisdomMultiplier);
        return {
            member: attacker,
            killScore,
            expGained,
            ...playerRewards.get(attacker)
        };
    });
}

function calculateArmyPower(army, hero, soldierStats, equipmentBonuses = []) {
    let power = 0;
    for (const slot of ARMY_SLOTS) {
        const type = Number(army[`${slot}_type`]) || 0;
        const amount = Number(army[`${slot}_num`]) || 0;
        const stats = soldierStats[type];
        if (type > 0 && amount > 0 && stats) {
            power += (stats.att * 0.6 + stats.hp * 0.4) * amount;
        }
    }

    const bravery = (Number(hero.point_b) || 0) + (Number(hero.point_b_plus) || 0);
    power *= 1 + ((Number(hero.power) || 50) + bravery) / 100;
    for (const bonus of equipmentBonuses) {
        power += (bonus.attack || 0) * 1.5
            + (bonus.vitality || 0) * 0.8
            + (bonus.damage || 0) * 2
            + (bonus.break || 0) * 1.5
            + (bonus.strike || 0);
    }
    return power;
}

function calculateArmyCapacity(army) {
    return ARMY_SLOTS.reduce((capacity, slot) => {
        const type = Number(army[`${slot}_type`]) || 0;
        const amount = Math.max(0, Number(army[`${slot}_num`]) || 0);
        return capacity + (ARMY_CAPACITY[type] || 0) * amount;
    }, 0);
}

function allocateProportionally(total, members, getWeight = member => member.combatPower) {
    if (!Number.isSafeInteger(total) || total < 0) {
        throw new TypeError('Allocation total must be a non-negative safe integer');
    }
    if (!members.length) return [];

    const weights = members.map(member => Math.max(0, Number(getWeight(member)) || 0));
    const totalWeight = weights.reduce((sum, weight) => sum + weight, 0);
    const effectiveWeights = totalWeight > 0
        ? weights
        : members.map(() => 1);
    const effectiveTotal = effectiveWeights.reduce((sum, weight) => sum + weight, 0);
    const allocations = members.map((member, index) => {
        const exact = total * effectiveWeights[index] / effectiveTotal;
        return { member, amount: Math.floor(exact), remainder: exact - Math.floor(exact) };
    });
    let remaining = total - allocations.reduce((sum, entry) => sum + entry.amount, 0);
    const remainderOrder = allocations
        .map((entry, index) => ({ index, remainder: entry.remainder, id: Number(entry.id_member) || 0 }))
        .sort((left, right) => right.remainder - left.remainder || left.id - right.id);
    for (let index = 0; remaining > 0; index++, remaining--) {
        allocations[remainderOrder[index % remainderOrder.length].index].amount++;
    }
    return allocations.map(({ member, amount }) => ({ member, amount }));
}

function allocateResourceLoot(resourcePool, members) {
    const resources = Object.entries(resourcePool);
    for (const [resource, amount] of resources) {
        if (!resource || !Number.isSafeInteger(amount) || amount < 0) {
            throw new TypeError('Resource loot must contain non-negative safe integer amounts');
        }
    }

    const capacities = members.map(member => {
        const capacity = Number(member.cargoCapacity);
        if (!Number.isSafeInteger(capacity) || capacity < 0) {
            throw new TypeError('Member cargo capacity must be a non-negative safe integer');
        }
        return capacity;
    });
    const totalCapacity = capacities.reduce((sum, capacity) => sum + capacity, 0);
    if (!Number.isSafeInteger(totalCapacity)) {
        throw new TypeError('Total cargo capacity must be a safe integer');
    }
    const totalAvailable = resources.reduce((sum, [, amount]) => sum + amount, 0);
    if (!Number.isSafeInteger(totalAvailable)) {
        throw new TypeError('Total available resource loot must be a safe integer');
    }

    const allocations = members.map(member => ({
        member,
        resources: Object.fromEntries(resources.map(([resource]) => [resource, 0]))
    }));
    const totalLoot = Math.min(totalCapacity, totalAvailable);
    if (!totalLoot || !resources.length || !members.length) return allocations;

    const resourceShares = allocateProportionally(
        totalLoot,
        resources.map(([resource, available]) => ({ resource, available })),
        resource => resource.available
    );
    const remainingCapacity = [...capacities];
    for (const share of resourceShares) {
        if (!share.amount) continue;
        const eligibleMembers = allocations.filter((allocation, index) => remainingCapacity[index] > 0);
        const memberShares = allocateProportionally(
            share.amount,
            eligibleMembers,
            allocation => remainingCapacity[allocations.indexOf(allocation)]
        );
        for (const memberShare of memberShares) {
            const index = allocations.indexOf(memberShare.member);
            allocations[index].resources[share.member.resource] = memberShare.amount;
            remainingCapacity[index] -= memberShare.amount;
        }
    }
    return allocations;
}

function didBattleSideWin(attackersWon, side) {
    if (side !== 0 && side !== 1) {
        throw new RangeError('Battle side must be 0 (defense) or 1 (attack)');
    }
    return side === 1 ? Boolean(attackersWon) : !attackersWon;
}

function calculateArmyCasualties(army, lossRate, recoveryRate, woundedRate, isVictory, combatRemainingArmy = null) {
    const remainingArmy = {};
    const wounded = {};
    const woundedBySlot = {};
    const totals = { fallen: 0, recovered: 0, permanentLoss: 0, wounded: 0 };
    for (const slot of ARMY_SLOTS) {
        const amount = Math.max(0, Number(army[`${slot}_num`]) || 0);
        const type = Math.max(0, Number(army[`${slot}_type`]) || 0);
        let remaining = amount;
        let woundedAmount = 0;
        if (amount > 0) {
            const simulatedRemaining = combatRemainingArmy && Number(combatRemainingArmy[slot]);
            const fallen = Number.isSafeInteger(simulatedRemaining)
                ? Math.max(0, amount - Math.max(0, Math.min(amount, simulatedRemaining)))
                : isVictory
                    ? Math.min(amount, Math.ceil(amount * lossRate))
                    : Math.min(amount, Math.max(1, Math.ceil(amount * lossRate)));
            const recovered = Math.floor(fallen * recoveryRate);
            const unrecovered = fallen - recovered;
            woundedAmount = Math.floor(unrecovered * woundedRate);
            const permanentLoss = unrecovered - woundedAmount;
            remaining = Math.max(0, amount - unrecovered);
            totals.fallen += fallen;
            totals.recovered += recovered;
            totals.permanentLoss += permanentLoss;
            totals.wounded += woundedAmount;
            if (woundedAmount > 0) wounded[type] = (wounded[type] || 0) + woundedAmount;
        }
        remainingArmy[slot] = {
            type: remaining > 0 ? type : 0,
            amount: remaining
        };
        woundedBySlot[slot] = woundedAmount;
    }
    return { remainingArmy, wounded, woundedBySlot, totals };
}

module.exports = {
    allocateProportionally,
    allocateResourceLoot,
    calculateArmyCasualties,
    calculateArmyCapacity,
    calculateArmyPower,
    calculateKillBasedRewards,
    calculateMemberKillScore,
    distributeMemberTroopLosses,
    didBattleSideWin,
    simulateTacticalCombat
};

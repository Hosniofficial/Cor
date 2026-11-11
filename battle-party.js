const ARMY_SLOTS = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];

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

function allocateProportionally(total, members) {
    if (!Number.isSafeInteger(total) || total < 0) {
        throw new TypeError('Allocation total must be a non-negative safe integer');
    }
    if (!members.length) return [];

    const weights = members.map(member => Math.max(0, Number(member.combatPower) || 0));
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

function didBattleSideWin(attackersWon, side) {
    if (side !== 0 && side !== 1) {
        throw new RangeError('Battle side must be 0 (defense) or 1 (attack)');
    }
    return side === 1 ? Boolean(attackersWon) : !attackersWon;
}

function calculateArmyCasualties(army, lossRate, recoveryRate, woundedRate, isVictory) {
    const remainingArmy = {};
    const wounded = {};
    const totals = { fallen: 0, recovered: 0, permanentLoss: 0, wounded: 0 };
    for (const slot of ARMY_SLOTS) {
        const amount = Math.max(0, Number(army[`${slot}_num`]) || 0);
        const type = Math.max(0, Number(army[`${slot}_type`]) || 0);
        let remaining = amount;
        if (amount > 0) {
            const fallen = isVictory
                ? Math.min(amount, Math.ceil(amount * lossRate))
                : Math.min(amount, Math.max(1, Math.ceil(amount * lossRate)));
            const recovered = Math.floor(fallen * recoveryRate);
            const unrecovered = fallen - recovered;
            const woundedAmount = Math.floor(unrecovered * woundedRate);
            const permanentLoss = unrecovered - woundedAmount;
            remaining = Math.max(0, amount - unrecovered - woundedAmount);
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
    }
    return { remainingArmy, wounded, totals };
}

module.exports = {
    allocateProportionally,
    calculateArmyCasualties,
    calculateArmyPower,
    didBattleSideWin
};

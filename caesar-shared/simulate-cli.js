#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const { simulateTacticalCombat } = require('./battle-party');

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

let inputStr = '';
if (process.argv[2]) {
    inputStr = process.argv[2];
} else {
    try {
        inputStr = fs.readFileSync(0, 'utf8');
    } catch (e) {
        // no stdin
    }
}

if (!inputStr) {
    console.log(JSON.stringify({ state: 'error', message: 'No input JSON provided' }));
    process.exit(1);
}

try {
    const payload = JSON.parse(inputStr);
    const attackers = payload.attackers || [];
    const npcDefenderTroops = payload.npcDefenderTroops || {};
    const options = {
        solStats: Array.isArray(payload.solStats) ? payload.solStats : SOL_STATS,
        commanderAttackBonus: Number(payload.commanderAttackBonus) || 0,
        npcCombatPowerMultiplier: Number(payload.npcCombatPowerMultiplier) || 1.0,
        npcDefenderTroops: { ...npcDefenderTroops },
        defendingMembers: Array.isArray(payload.defendingMembers) ? payload.defendingMembers : [],
        wallFortifications: payload.wallFortifications || { traps: 0, towers: 0, stones: 0 }
    };

    const result = simulateTacticalCombat(attackers, npcDefenderTroops, options);
    const roundsCount = Math.max(1, result.roundsLog.filter(l => l.startsWith('الجولة')).length);

    console.log(JSON.stringify({
        state: 'ok',
        result: {
            attackersWon: result.attackersWon,
            roundsCount,
            roundsLog: result.roundsLog,
            remainingAttackers: result.remainingAttackers,
            remainingDefenders: result.remainingDefenders
        }
    }));
} catch (err) {
    console.log(JSON.stringify({ state: 'error', message: err.message }));
    process.exit(1);
}

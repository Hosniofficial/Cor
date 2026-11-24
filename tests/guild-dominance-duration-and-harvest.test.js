const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const clientJsPath = path.join(__dirname, '..', 'caesar-assets', 'web', 'js-3.4.113', 'caesar-client.js');
const worldCtrlPath = path.join(__dirname, '..', 'caesar-api', 'controllers', 'WorldController.php');
const migrationPath = path.join(__dirname, '..', 'migrations', '20261123_guild_dominance_player_harvest.sql');

test('Rank list and dominance panel in caesar-client.js format duration in days/hours/minutes/seconds', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');

    // 1. Check rank list duration calculation (not minutes-only)
    assert.ok(
        clientJs.includes('rdDays') && clientJs.includes('rdHours') && clientJs.includes('rdMins'),
        'Rank list duration must calculate days, hours, and minutes instead of minutes-only'
    );
    assert.ok(
        clientJs.includes('${rdDays} يوم ${rdHours} س') && clientJs.includes('${rdHours} س ${rdMins} د'),
        'Rank list duration must format days and hours properly'
    );

    // 2. Check dominance status panel duration
    assert.ok(
        clientJs.includes('Math.floor(totalSeconds / 86400)') && clientJs.includes('Math.floor((totalSeconds % 86400) / 3600)'),
        'Dominance panel must break down totalSeconds into days and hours'
    );
});

test('Live ticking countdown timer in caesar-client.js updates second-by-second and enables harvest button', () => {
    const clientJs = fs.readFileSync(clientJsPath, 'utf8');

    // 1. Live countdown interval
    assert.ok(
        clientJs.includes('window[timerKey] = setInterval('),
        'Must set ticking countdown interval on window[timerKey]'
    );
    assert.ok(
        clientJs.includes('buildCountdownText'),
        'Must have countdown text formatter'
    );
    assert.ok(
        clientJs.includes('harvestButton.prop("disabled", false)'),
        'Must enable harvest button when countdown hits zero'
    );
    assert.ok(
        clientJs.includes('الغنيمة متاحة الآن!'),
        'Must display ready message when countdown reaches zero'
    );

    // 2. Cleanup when dialog is closed or element removed
    assert.ok(
        clientJs.includes('dominance_timer_'),
        'Must track dominance timers by prefix'
    );
    assert.ok(
        clientJs.includes('dialogBoxClose') && clientJs.includes('dominance_timer_'),
        'dialogBoxClose must clear active dominance countdown timers'
    );
});

test('WorldController implements per-player harvest tracking and dual token/session authentication', () => {
    const worldCtrl = fs.readFileSync(worldCtrlPath, 'utf8');

    // 1. Uses guild_dominance_harvest table
    assert.ok(
        worldCtrl.includes('guild_dominance_harvest'),
        'WorldController must use guild_dominance_harvest table for per-player tracking'
    );

    // 2. Dual token + session authentication helper
    assert.ok(
        worldCtrl.includes('function authenticatePlayer'),
        'WorldController must define authenticatePlayer helper'
    );
    assert.ok(
        worldCtrl.includes('p_token') && worldCtrl.includes('$_SESSION'),
        'authenticatePlayer must support both token and $_SESSION'
    );

    // 3. harvestDominance inserts per-player record
    assert.ok(
        worldCtrl.includes('INSERT INTO guild_dominance_harvest'),
        'harvestDominance must record per-player harvest'
    );
    assert.ok(
        worldCtrl.includes('ON DUPLICATE KEY UPDATE'),
        'harvestDominance must update on duplicate key'
    );
});

test('Migration script creates guild_dominance_harvest with composite unique index', () => {
    const migration = fs.readFileSync(migrationPath, 'utf8');

    assert.ok(
        migration.includes('CREATE TABLE IF NOT EXISTS `guild_dominance_harvest`'),
        'Migration must create guild_dominance_harvest table'
    );
    assert.ok(
        migration.includes('UNIQUE KEY `uq_player_location` (`x_coord`, `y_coord`, `id_player`)'),
        'Migration must enforce unique key per location and player'
    );
});

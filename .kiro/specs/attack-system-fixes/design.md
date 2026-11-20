# Design Document — Attack System Fixes

## Overview

Three surgical bug fixes to the Caesar game's attack system, touching the PHP API layer, the Node.js battle resolution engine, and the client-side Phaser world-map renderer. All changes are additive guards or loop restructurings; no data models or database schemas change.

---

## Architecture

```
Browser (caesar-client.js)
  └─ Caesar.World.MapBattel.AddBattels()
       └─ reads BattelList (populated via HTTP poll + WS events)
            └─ draws BattelLine per entry

PHP API (WorldController.php)
  ├─ getBattels()       ← /api/AWorld/getBattels
  └─ getUnitBattel()    ← /api/AWorld/getUnitBattel

Node.js Battle Engine (caesar-shared/battle-resolution.js)
  └─ resolveBattle()
       ├─ prize loop        ← Fix 2 (luxury rate) + Fix 3 (per-attacker rolls)
       └─ transferBattleLoot()
```

The three fixes operate at three independent layers and have no cross-cutting dependencies.

---

## Fix 1 — March Line Hidden from All Players

### Component: `WorldController::getBattels()` and `WorldController::getUnitBattel()`

**Current behaviour**: The `foreach` cast loop includes `x_city` and `y_city` in the integer-cast list, so both fields are serialised in the JSON response. Any player calling the endpoint can read the attacker's origin coordinates.

**Change**: Remove `x_city` and `y_city` from the cast-and-include list in both methods, then `unset()` them before `sendJson()`. Alternatively, omit them from the cast list and add explicit `unset($r['x_city']); unset($r['y_city']);` lines after the loop.

**Chosen approach**: Unset after the existing loop — this is the smallest diff and leaves the SELECT query intact (no SQL change needed; unused columns from `SELECT b.*` are simply dropped before output).

```php
// In getBattels() — after the foreach cast loop:
foreach ($rows as &$r) {
    foreach ([
        'id_battel', 'id_player', 'id_city', 'id_hero',
        'task', 'x_coord', 'y_coord',
        'time_start', 'time_arrive', 'battle_pending'
    ] as $col) {
        if (isset($r[$col])) $r[$col] = (int)$r[$col];
    }
    $r['attackNum']  = (int)$r['attackNum'];
    $r['defenceNum'] = (int)$r['defenceNum'];
    unset($r['x_city'], $r['y_city']);   // ← strip origin coords
}
```

Apply the identical `unset` to `getUnitBattel()`.

### Component: `Caesar.World.MapBattel.AddBattels()` (caesar-client.js)

**Current behaviour**: After the server stops sending `xCity`/`yCity`, those fields will be `undefined` on existing in-memory `BattelList` entries. `posX(undefined, undefined)` returns a position near (0, 0), making `x1 ≈ x2` and `y1 ≈ y2` — a zero-length or near-zero-length line. Passing `LineWidth = 0` to `tileSprite` raises a Phaser error.

**Change**: Add a guard just before the coordinate computation that skips any entry where `xCity` is falsy (undefined, null, or 0):

```js
// In Caesar.World.MapBattel.AddBattels — after the timeEnd check:
if (!OneBattel.xCity || !OneBattel.yCity) continue;
```

This guard is placed after the `timeEnd` expiry check and before the coordinate computation block, so expired entries are still cleaned up correctly.

**Why not suppress on the WS side**: The WebSocket server (`ws-server.js`) still includes `xCity`/`yCity` in its `World.Battel.Started` push (requirements note WS broadcast is already suppressed at the event level, Requirement 1.4). The client guard is the safety net for any residual BattelList entries that arrive via the HTTP poll response with no coords.

---

## Fix 2 — Barrary Duplicate Resource Prevention + Luxury Rate Boost

### Component: `battle-resolution.js` — prize loop

Both sub-fixes live inside the `for (const p of matchingPrizes)` loop.

#### Sub-fix 2a — `isResourcePackItem` guard (verify already in place)

The guard was added in a prior pass:

```js
if (Boolean(context.isBarray) && isResourcePackItem(p.prize)) continue;
```

No code change required. The design records this as a verified invariant.

#### Sub-fix 2b — Luxury rate multiplier

After computing `effectiveRate` and before the `isPrizeDropSuccessful` call, apply the 1.5× boost when both conditions hold:

```js
const rawRate = Number(p.win_rate) || 0;
const effectiveRate = isWilderness && rawRate <= 1000
    ? Math.min(10000, rawRate * 10)
    : Math.min(10000, rawRate);

// Luxury boost for wilderness / barray targets
const isLuxury = isWilderness && /^luxury_[1-9]$/.test(String(p.prize || ''));
const finalRate = isLuxury
    ? Math.min(10000, Math.floor(effectiveRate * 1.5))
    : effectiveRate;

if (isPrizeDropSuccessful(finalRate)) {
```

`effectiveRate` is the already-normalised rate (scaled from 1 000→10 000 for classic wilderness tables). The 1.5× multiplier is applied to that normalised value, capped at 10 000.

**Why `Math.floor`**: `effectiveRate` is already integer after `Math.min(10000, ...)` and the multiplication could produce a float; `Math.floor` keeps it integer for consistent comparison with the random integer produced by `isPrizeDropSuccessful`.

---

## Fix 3 — Joined Attackers Receive Item Prizes

### Component: `battle-resolution.js` — section 6c (store non-resource item drops)

#### Current structure

```js
for (const drop of droppedItems) {
    const prizeShares = [{ member: hostMember, amount: drop.amount }];
    for (const share of prizeShares) {
        // INSERT INTO player_item ... for hostMember only
    }
}
```

`droppedItems` is a single shared list rolled once. Only `hostMember` ever appears in `prizeShares`.

#### Target structure

For non-wilderness, non-city targets (`!isWilderness && !targetCityRows.length`), each attacker rolls independently for each prize type. For wilderness/city targets the existing host-only path is preserved.

```js
// 6c. Store non-resource item drops.
if (!isWilderness && !targetCityRows.length) {
    // Non-wilderness, non-city target: each attacker rolls independently.
    for (const attacker of attackers) {
        for (const p of matchingPrizes) {
            const rawRate = Number(p.win_rate) || 0;
            const effectiveRate = Math.min(10000, rawRate);
            if (!isPrizeDropSuccessful(effectiveRate)) continue;
            const minAmt = Number.isSafeInteger(p.amount_min) ? p.amount_min : 1;
            const maxAmt = Number.isSafeInteger(p.amount_max) ? p.amount_max : minAmt;
            const amt = Math.floor(Math.random() * (maxAmt - minAmt + 1)) + minAmt;
            if (amt <= 0) continue;
            await battleDb.query(
                "INSERT INTO player_item (id_player, id_item, amount) VALUES (?, ?, ?) " +
                "ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)",
                [attacker.player.id_player, p.prize, amt]
            );
            attacker.itemLoot.push({ prize: p.prize, amount: amt });
        }
        // Fallback necklace guarantee (mirrors the existing droppedItems fallback)
        const hasNecklace = attacker.itemLoot.some(d => d.prize && d.prize.startsWith('necklace_'));
        if (!hasNecklace && unitType >= 70 && unitType <= 105) {
            const campNecklace = unitLvl >= 30 ? 'necklace_5' : (unitLvl >= 15 ? 'necklace_4' : 'necklace_1');
            await battleDb.query(
                "INSERT INTO player_item (id_player, id_item, amount) VALUES (?, ?, 1) " +
                "ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)",
                [attacker.player.id_player, campNecklace]
            );
            attacker.itemLoot.push({ prize: campNecklace, amount: 1 });
            if (unitLvl >= 20) {
                await battleDb.query(
                    "INSERT INTO player_item (id_player, id_item, amount) VALUES (?, ?, 1) " +
                    "ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)",
                    [attacker.player.id_player, 'bread']
                );
                attacker.itemLoot.push({ prize: 'bread', amount: 1 });
            }
        }
    }
} else {
    // Wilderness or city target: host-only drop (unchanged).
    for (const drop of droppedItems) {
        const prizeShares = [{ member: hostMember, amount: drop.amount }];
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
}
```

**Key design decisions**:

- `droppedItems` (the pre-rolled shared list) is used only for the wilderness/city branch. The non-wilderness branch re-rolls per attacker directly from `matchingPrizes`, so each attacker's outcome is statistically independent.
- The fallback necklace guarantee is replicated per-attacker in the non-wilderness path, using `attacker.itemLoot` to check whether that specific attacker already received a necklace.
- Equipment drops (section 6d) remain `hostMember`-only — `equipRecipient = hostMember` is not changed.
- `wildernessRecipient: hostMember` in `transferBattleLoot` is not changed.

---

## Data Models

No new tables or columns. All changes are in-memory computation and existing DB INSERT patterns.

---

## Error Handling

| Scenario | Handling |
|---|---|
| `OneBattel.xCity` is undefined/null/0 on client | `continue` — skip drawing, no Phaser error |
| `effectiveRate * 1.5` overflows 10000 | `Math.min(10000, ...)` clamps it |
| `matchingPrizes` is empty for non-wilderness target | Fallback necklace loop runs per-attacker, no DB error |
| `attacker.player.id_player` is falsy | Existing `attackers` construction validates member data upstream; guard is not added here to stay minimal |

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — formally, a universal statement about what the system must do.*

### Property 1: getAllWorldBattels omits origin coordinates

*For any* battle row stored in `world_battel` that contains `x_city` and `y_city` columns, the JSON object returned by `WorldController::getBattels()` or `WorldController::getUnitBattel()` SHALL contain neither the key `x_city` nor the key `y_city`.

**Validates: Requirements 1.1, 1.5**

---

### Property 2: getAllWorldBattels preserves required fields

*For any* battle row in `world_battel`, the JSON object in the response from `getBattels()` SHALL contain all of: `id_battel`, `id_player`, `id_city`, `id_hero`, `task`, `x_coord`, `y_coord`, `time_start`, `time_arrive`, `battle_pending`, `attackNum`, `defenceNum`.

**Validates: Requirements 1.3**

---

### Property 3: BattelLine is never drawn for battles without origin coordinates

*For any* battle entry in `BattelList` where `xCity` is falsy (undefined, null, 0), `Caesar.World.MapBattel.AddBattels()` SHALL not invoke `BattelLine` for that entry.

**Validates: Requirements 1.2**

---

### Property 4: Barrary battles produce no ResourcePackItem in inventory

*For any* set of matching prize entries that includes items whose key matches `(food|wood|stone|metal)_[abc]`, when the battle context has `isBarray = true`, none of those items SHALL appear in `droppedItems` after the prize loop completes.

**Validates: Requirements 2.1, 2.2**

---

### Property 5: Luxury drop rate is boosted exactly 1.5× for wilderness targets

*For any* prize entry whose key matches `luxury_[1-9]` and any raw `win_rate` value in `[0, 10000]`, when `isWilderness = true`, the final effective rate used for the drop check SHALL equal `min(10000, floor(normalised_rate × 1.5))`, where `normalised_rate` is the rate after the existing 1 000→10 000 scaling is applied.

**Validates: Requirements 2.3, 2.4**

---

### Property 6: Non-luxury prize rates are unchanged for wilderness targets

*For any* prize entry whose key does NOT match `luxury_[1-9]`, the final effective rate used for the drop check SHALL equal the standard `effectiveRate` without any 1.5× multiplication, regardless of `isWilderness`.

**Validates: Requirements 2.5**

---

### Property 7: Every attacker receives an independent prize roll for non-wilderness non-city targets

*For any* set of N attackers on a non-wilderness, non-city target (unitType 70–96, 97–105, or 150–236) where the battle is victorious, the battle resolution engine SHALL perform an independent `isPrizeDropSuccessful` roll for each attacker, so that each attacker's prize outcome is determined by a separate random draw from `matchingPrizes`.

**Validates: Requirements 3.1, 3.2, 3.7**

---

### Property 8: Winning attacker prize is recorded in both DB and itemLoot

*For any* attacker who wins a prize roll on a non-wilderness non-city target, the engine SHALL execute an `INSERT INTO player_item ... ON DUPLICATE KEY UPDATE` with that attacker's `id_player` AND SHALL append the prize to that attacker's `itemLoot` array.

**Validates: Requirements 3.3**

---

### Property 9: Equipment drops always go to hostMember only

*For any* set of attackers, the equipment drop recipient (`equipRecipient`) SHALL always equal `hostMember`, regardless of the number or identity of joined attackers.

**Validates: Requirements 3.6**

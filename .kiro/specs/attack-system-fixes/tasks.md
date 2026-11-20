# Implementation Plan: Attack System Fixes

## Overview

Three surgical fixes applied to the PHP API layer, the Node.js battle resolution engine, and the client-side world-map renderer. Each fix is independent; they can be implemented and validated in any order.

---

## Tasks

- [ ] 1. Fix 1 — Strip march origin coordinates from PHP API responses
  - [ ] 1.1 Remove `x_city` and `y_city` from `WorldController::getBattels()`
    - Open `caesar-api/controllers/WorldController.php`
    - In the `getBattels()` method, after the existing `foreach` cast loop, add `unset($r['x_city'], $r['y_city']);` for each row `$r`
    - Confirm `x_coord`, `y_coord`, `id_battel`, `id_player`, `id_city`, `id_hero`, `task`, `time_start`, `time_arrive`, `battle_pending`, `attackNum`, `defenceNum` remain in the output
    - _Requirements: 1.1, 1.3_

  - [ ] 1.2 Apply the same suppression to `WorldController::getUnitBattel()`
    - In the `getUnitBattel()` method, add identical `unset($r['x_city'], $r['y_city']);` after the cast loop
    - _Requirements: 1.5_

  - [ ]* 1.3 Write property test for coordinate suppression
    - **Property 1: getAllWorldBattels omits origin coordinates**
    - **Validates: Requirements 1.1, 1.5**
    - Construct a mock battle row with `x_city` and `y_city` set, pass it through the serialisation logic, assert neither key appears in output
    - Also verify the 12 required fields (Property 2) are all present
    - **Property 2: getAllWorldBattels preserves required fields**
    - **Validates: Requirements 1.3**

- [ ] 2. Fix 1 (client) — Guard against drawing BattelLine when origin is absent
  - [ ] 2.1 Add falsy-check guard in `Caesar.World.MapBattel.AddBattels()`
    - Open the relevant file in `caesar-assets/web/js-3.4.113/caesar-client.js`
    - Locate the `AddBattels` function and find the per-entry loop
    - After the `timeEnd` expiry check and before the coordinate computation block, insert:
      `if (!OneBattel.xCity || !OneBattel.yCity) continue;`
    - _Requirements: 1.2_

  - [ ]* 2.2 Write property test for BattelLine guard
    - **Property 3: BattelLine is never drawn for battles without origin coordinates**
    - **Validates: Requirements 1.2**
    - Spy on `BattelLine`; pass a `BattelList` entry with `xCity = undefined` through `AddBattels`; assert `BattelLine` was not called

- [ ] 3. Checkpoint — Verify Fix 1 end-to-end
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 4. Fix 2a — Confirm `isResourcePackItem` guard is active for Barrary targets
  - [ ] 4.1 Verify the existing guard in `battle-resolution.js`
    - Open `caesar-shared/battle-resolution.js`
    - Locate the `for (const p of matchingPrizes)` loop
    - Confirm the line `if (Boolean(context.isBarray) && isResourcePackItem(p.prize)) continue;` is present and correctly placed at the top of the loop body
    - If the guard is missing or malformed, add/correct it
    - _Requirements: 2.1, 2.2_

  - [ ]* 4.2 Write property test for Barrary resource-pack suppression
    - **Property 4: Barrary battles produce no ResourcePackItem in inventory**
    - **Validates: Requirements 2.1, 2.2**
    - Build a `matchingPrizes` array that contains both `food_a` (a ResourcePackItem) and `necklace_1`; set `isBarray = true`; run the prize loop; assert `food_a` does not appear in `droppedItems` and `necklace_1` may appear

- [ ] 5. Fix 2b — Apply luxury rate multiplier for wilderness and Barrary targets
  - [ ] 5.1 Implement 1.5× luxury `win_rate` multiplier in `battle-resolution.js`
    - In the `for (const p of matchingPrizes)` loop, after computing `effectiveRate`, add:
      ```js
      const isLuxury = isWilderness && /^luxury_[1-9]$/.test(String(p.prize || ''));
      const finalRate = isLuxury
          ? Math.min(10000, Math.floor(effectiveRate * 1.5))
          : effectiveRate;
      ```
    - Replace the `isPrizeDropSuccessful(effectiveRate)` call with `isPrizeDropSuccessful(finalRate)`
    - _Requirements: 2.3, 2.4_

  - [ ]* 5.2 Write property tests for luxury rate boost
    - **Property 5: Luxury drop rate is boosted exactly 1.5× for wilderness targets**
    - **Validates: Requirements 2.3, 2.4**
    - For a range of `win_rate` values (0, 100, 1000, 6667, 10000), assert `finalRate === min(10000, floor(normalised * 1.5))` when `isWilderness = true` and prize is `luxury_3`
    - **Property 6: Non-luxury prize rates are unchanged for wilderness targets**
    - **Validates: Requirements 2.5**
    - For a non-luxury prize (e.g. `necklace_1`) with `isWilderness = true`, assert `finalRate === effectiveRate`

- [ ] 6. Checkpoint — Verify Fix 2 end-to-end
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 7. Fix 3 — Grant item prizes to joined attackers
  - [ ] 7.1 Restructure section 6c of `battle-resolution.js` to roll prizes per attacker
    - Wrap the existing `droppedItems` distribution inside an `else` branch (for wilderness/city targets)
    - Add a new `if (!isWilderness && !targetCityRows.length)` branch that iterates `attackers` and for each attacker iterates `matchingPrizes`, calling `isPrizeDropSuccessful(effectiveRate)` per attacker per prize
    - On a successful roll, `INSERT INTO player_item ... ON DUPLICATE KEY UPDATE` using the attacker's `id_player` and push to `attacker.itemLoot`
    - _Requirements: 3.1, 3.2, 3.3_

  - [ ] 7.2 Replicate the fallback necklace guarantee per attacker
    - Inside the per-attacker loop (non-wilderness branch), after iterating `matchingPrizes`, add the necklace fallback using `attacker.itemLoot` to determine if that specific attacker already received a necklace
    - Only trigger the fallback for `unitType` 70–105
    - Also grant `bread` to attackers whose `unitLvl >= 20` (mirrors existing host behaviour)
    - _Requirements: 3.1, 3.2_

  - [ ] 7.3 Confirm equipment drops remain host-only and wilderness path is unchanged
    - Verify that `equipRecipient = hostMember` (section 6d) is not modified
    - Verify that `wildernessRecipient: hostMember` in `transferBattleLoot` is not modified
    - _Requirements: 3.4, 3.6_

  - [ ]* 7.4 Write property tests for per-attacker prize rolls
    - **Property 7: Every attacker receives an independent prize roll for non-wilderness non-city targets**
    - **Validates: Requirements 3.1, 3.2, 3.7**
    - Create a mock battle with 3 attackers (host + 2 joined) on a camp (unitType 80); seed the RNG deterministically; assert that `isPrizeDropSuccessful` is called once per attacker per matchingPrizes entry (3 × N calls total)
    - **Property 8: Winning attacker prize is recorded in both DB and itemLoot**
    - **Validates: Requirements 3.3**
    - Assert that for each attacker who wins a roll, `battleDb.query` is called with that attacker's `id_player` and the prize is in `attacker.itemLoot`
    - **Property 9: Equipment drops always go to hostMember only**
    - **Validates: Requirements 3.6**
    - Assert `equipRecipient === hostMember` after resolution regardless of joined attacker count

- [ ] 8. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

---

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation before moving on
- Fix 2a (task 4.1) may require no code change if the guard is already correct — treat it as a verification step
- Property tests validate universal correctness properties; unit tests validate specific examples and edge cases

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "2.1", "4.1"] },
    { "id": 1, "tasks": ["1.2", "5.1"] },
    { "id": 2, "tasks": ["1.3", "2.2", "4.2", "7.1"] },
    { "id": 3, "tasks": ["5.2", "7.2"] },
    { "id": 4, "tasks": ["7.3"] },
    { "id": 5, "tasks": ["7.4"] }
  ]
}
```

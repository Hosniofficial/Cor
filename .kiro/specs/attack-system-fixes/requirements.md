# Requirements Document

## Introduction

This document defines the requirements for three bug fixes in the Caesar game attack system:

1. **March Indicator Visibility** — The dashed march line drawn on the world map is visible to all players, exposing troop movements that should be hidden.
2. **Barrary Duplicate Resource Rewards** — Attacking a Barrary (colonized wilderness) previously granted both direct city resources and duplicate resource-pack inventory items. A guard (`isResourcePackItem`) was added to `caesar-shared/battle-resolution.js` to fix this; the fix needs verification and luxury drop rates from Barrary and wilderness need a slight increase.
3. **Joined Attackers Receive No Prizes** — When attacking camps (unitType 70–96), battalions/teams (70–105), or war statues (unitType 150–236), only the host (battle initiator) receives item prizes; joined attackers receive nothing.

## Glossary

- **BattleResolutionEngine**: The server-side module `caesar-shared/battle-resolution.js` that resolves battle outcomes, calculates loot, and distributes rewards.
- **BattleLootModule**: The server-side module `caesar-shared/battle-loot.js` that implements resource spoil and prize-drop calculations.
- **WorldController**: The PHP backend file `caesar-api/controllers/WorldController.php` that handles HTTP API endpoints for the world map, including `getAllWorldBattels`.
- **MapBattelRenderer**: The client-side JavaScript component in `caesar-assets/web/js-3.4.113/caesar-client.js` responsible for drawing battle march lines on the world map (`Caesar.World.MapBattel.AddBattels` and `Caesar.World.MapBattel.BattelLine`).
- **WsBroadcast**: The WebSocket server `caesar-ws/ws-server.js` that pushes real-time battle events to connected clients.
- **MarchLine**: The dashed arrow drawn from an attacker's city coordinates (`x_city`, `y_city`) to the battle target coordinates (`x_coord`, `y_coord`) on the world map.
- **HostMember**: The player who initiated the battle (the hero identified by `idHero` in the battle record).
- **JoinedAttacker**: A player who joined an existing battle via the `Battel/join` endpoint; recorded in `world_battel_member` with `side = 1` but whose `id_hero` differs from the battle's `idHero`.
- **DroppedItems**: The list of non-resource inventory items (necklaces, bread, luxury items, etc.) awarded to players upon a victorious battle.
- **PrizeData**: The prize table loaded from `caesar-private/data/world-unit-prizes.json`, keyed by `Win`, containing rows with `unitType`, `lvl`, `prize`, `win_rate`, `amount_min`, and `amount_max`.
- **LuxuryItem**: Any item whose key matches `luxury_1` through `luxury_9` in PrizeData.
- **ResourcePackItem**: An inventory item whose key matches the pattern `(food|wood|stone|metal)_[abc]`, which duplicates resources already credited as direct city spoils.
- **Barrary**: A colonized wilderness tile (tracked in `city_barray`), identified by `isBarray = true` in battle context.
- **Camp**: A world unit with `unitType` in range 70–96.
- **Battalion/Team**: A world unit with `unitType` in range 70–105.
- **WarStatue**: A world unit with `unitType` in range 150–236.
- **PrizeRateScale**: The integer `10000` used as the denominator for prize drop probability (a `win_rate` of `10000` means 100% chance).

## Requirements

### Requirement 1 — March Line Hidden from All Players

**User Story:** As a game player, I want troop movements to remain hidden on the world map so that opponents cannot see where attacks are marching from or to.

#### Acceptance Criteria

1. WHEN the `WorldController` handles a request to `/Battel/getAllWorldBattels` or `/api/Battel/getAllWorldBattels`, THE `WorldController` SHALL omit the `x_city` and `y_city` fields from every battle object in the JSON response.

2. WHEN the `MapBattelRenderer` processes a battle object that has no `xCity` or `yCity` values, THE `MapBattelRenderer` SHALL skip drawing a `BattelLine` for that battle.

3. THE `WorldController` SHALL continue to include `x_coord`, `y_coord`, `time_start`, `time_arrive`, `id_battel`, `id_player`, `id_city`, `id_hero`, `task`, `attackNum`, `defenceNum`, and `battle_pending` in the `getAllWorldBattels` response.

4. WHEN the WsBroadcast would emit a `World.Battel.Started` event, THE `WsBroadcast` SHALL continue to suppress that broadcast so that real-time march events are not sent to other players.

5. THE `WorldController` `getUnitBattel` endpoint SHALL also omit `x_city` and `y_city` from its response, applying the same suppression as `getAllWorldBattels`.

---

### Requirement 2 — Barrary Duplicate Resource Reward Prevention and Luxury Rate Increase

**User Story:** As a game player, I want attacking a Barrary to grant city resources exactly once and want a slightly higher chance of obtaining luxury items from wilderness and Barrary attacks.

#### Acceptance Criteria

1. WHEN a player wins a battle against a Barrary target (`isBarray = true`), THE `BattleResolutionEngine` SHALL award direct city resources via `calculateWildernessSpoils` and `transferBattleLoot` exactly once, without also adding any `ResourcePackItem` to the player's inventory.

2. WHEN the `BattleResolutionEngine` iterates `matchingPrizes` for a Barrary target, THE `BattleResolutionEngine` SHALL skip any prize entry where `isResourcePackItem(p.prize)` returns `true`.

3. WHEN the `BattleResolutionEngine` applies an effective drop rate to a `LuxuryItem` prize entry for a wilderness or Barrary target, THE `BattleResolutionEngine` SHALL multiply the base `win_rate` from `PrizeData` by a factor of `1.5` before converting it to the `PrizeRateScale` range, capped at `10000`.

4. THE `BattleResolutionEngine` SHALL apply the luxury rate multiplier only to items whose prize key matches `luxury_[1-9]` and only when `isWilderness` is `true` (i.e., `unitType` 1–59 or `isBarray = true`).

5. THE `BattleResolutionEngine` SHALL leave all other (non-luxury) prize `win_rate` values unchanged for wilderness and Barrary targets.

---

### Requirement 3 — Joined Attackers Receive Item Prizes

**User Story:** As a player who joins another player's attack on a camp, battalion, or war statue, I want to receive item prizes when the battle is won so that joining an attack is as rewarding as initiating one.

#### Acceptance Criteria

1. WHEN a victorious battle resolves against a Camp (`unitType` 70–96), Battalion/Team (`unitType` 70–105), or WarStatue (`unitType` 150–236), THE `BattleResolutionEngine` SHALL grant each `JoinedAttacker` an independent prize roll using the same `PrizeData`, `win_rate`, and `amount_min`/`amount_max` values as applied to the `HostMember`.

2. WHEN the `BattleResolutionEngine` awards `DroppedItems` for a non-wilderness, non-city target, THE `BattleResolutionEngine` SHALL iterate over all members in the `attackers` array (not only `hostMember`) and perform a prize roll per attacker per dropped item type.

3. WHEN a `JoinedAttacker` wins a prize roll, THE `BattleResolutionEngine` SHALL insert the prize item into `player_item` for that attacker's `id_player` using `ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)`, and SHALL append the prize to that attacker's `itemLoot` array.

4. THE `BattleResolutionEngine` SHALL preserve the existing `wildernessRecipient: hostMember` behavior for wilderness (`unitType` 1–59) and Barrary targets, so that resource spoils go only to the host.

5. THE `BattleResolutionEngine` SHALL preserve the existing prize distribution logic for Carthaginian gang targets (`unitType` 97–105) where all participants already receive prizes correctly, and SHALL NOT alter that logic.

6. WHEN the `BattleResolutionEngine` awards equipment drops (via the direct hero equipment drop chance block for camps 70–96, gangs 97–105, or arenas 150–236), THE `BattleResolutionEngine` SHALL continue to give equipment drops to `hostMember` only, as equipment drops are a separate mechanic from item prizes.

7. WHEN a victorious battle resolves against a WarStatue (`unitType` 150–236), THE `BattleResolutionEngine` SHALL apply the same per-attacker independent prize roll as for Camps and Battalions.

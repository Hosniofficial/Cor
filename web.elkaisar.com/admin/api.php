<?php
// web.elkaisar.com/admin/api.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/auth.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$readOnlyActions = [
    'get_stats',
    'get_catalog',
    'get_players',
    'get_player_details',
    'get_admins',
    'get_logs',
];
$isReadOnlyAction = in_array($action, $readOnlyActions, true);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
if (($isReadOnlyAction && $requestMethod !== 'GET')
    || (!$isReadOnlyAction && $requestMethod !== 'POST')) {
    http_response_code(405);
    header('Allow: ' . ($isReadOnlyAction ? 'GET' : 'POST'));
    echo json_encode(['state' => 'error', 'message' => 'طريقة الطلب غير مدعومة']);
    exit;
}

requireLogin(true);
if (!$isReadOnlyAction) {
    requireValidAdminCsrf();
}

$db = getDB();

// Helper for sending API response
function jsonSuccess($data = [], $message = 'تمت العملية بنجاح') {
    echo json_encode([
        'state'   => 'ok',
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message = 'حدث خطأ أثناء تنفيذ العملية', $code = 400) {
    http_response_code($code);
    echo json_encode([
        'state'   => 'error',
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Load catalogues
$itemsCatalogPath = __DIR__ . '/../../app.elkaisar.com/js-0.0.24/json/ItemLang/ar.json';
$equipsCatalogPath = __DIR__ . '/../../app.elkaisar.com/js-0.0.24/json/equipment/ar.json';

$itemsCatalog = file_exists($itemsCatalogPath) ? json_decode(file_get_contents($itemsCatalogPath), true) : [];
$equipsCatalog = file_exists($equipsCatalogPath) ? json_decode(file_get_contents($equipsCatalogPath), true) : [];
$flatEquipsCatalog = is_array($equipsCatalog) ? $equipsCatalog : [];
$equipsCatalog = [];
$primaryEquipParts = ['sword', 'helmet', 'shield', 'armor', 'boot'];

foreach ($flatEquipsCatalog as $equipId => $meta) {
    if (!preg_match('/^(.+)_(sword|helmet|shield|armor|boot|belt|necklace|pendant|ring|steed)_(\d+)$/', $equipId, $matches)) {
        continue;
    }

    $setKey = $matches[1];
    $part = $matches[2];
    $level = (int)$matches[3];
    if ($level < 1) {
        continue;
    }

    if (in_array($part, $primaryEquipParts, true)) {
        if ($level === 1) {
            $equipsCatalog[$setKey][$part] = $meta;
        }
    } else {
        $equipsCatalog[$setKey]['sec'][$part][$level - 1] = $meta;
    }
}

switch ($action) {

    // ==========================================
    // 1. Dashboard Overview Stats
    // ==========================================
    case 'get_stats':
        $totalPlayers = (int)$db->query("SELECT COUNT(*) FROM `player`")->fetchColumn();
        $onlinePlayers = (int)$db->query("SELECT COUNT(*) FROM `player` WHERE `online` = 1")->fetchColumn();
        $totalCities = (int)$db->query("SELECT COUNT(*) FROM `city`")->fetchColumn();
        $totalGuilds = (int)$db->query("SELECT COUNT(*) FROM `guild`")->fetchColumn();
        $totalGold = (int)$db->query("SELECT SUM(`gold`) FROM `player`")->fetchColumn();
        $bannedPlayers = (int)$db->query("SELECT COUNT(*) FROM `player` WHERE `panned` > UNIX_TIMESTAMP() OR `panned` = 1")->fetchColumn();
        $mutedPlayers = (int)$db->query("SELECT COUNT(*) FROM `player` WHERE `chat_panne` > UNIX_TIMESTAMP() OR `chat_panne` = 1")->fetchColumn();

        // Recent 5 logs
        $stmt = $db->query("SELECT * FROM `admin_log` ORDER BY `id_log` DESC LIMIT 6");
        $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        jsonSuccess([
            'total_players'  => $totalPlayers,
            'online_players' => $onlinePlayers,
            'total_cities'   => $totalCities,
            'total_guilds'   => $totalGuilds,
            'total_gold'     => $totalGold,
            'banned_players' => $bannedPlayers,
            'muted_players'  => $mutedPlayers,
            'recent_logs'    => $recentLogs
        ]);
        break;

    // ==========================================
    // 2. Catalogues for Selectors
    // ==========================================
    case 'get_catalog':
        $formattedItems = [];
        foreach ($itemsCatalog as $id => $meta) {
            $formattedItems[] = [
                'id'    => $id,
                'name'  => $meta['name'] ?? $id,
                'image' => $meta['image'] ?? 'images/items/item000.jpg',
                'desc'  => $meta['desc'] ?? ''
            ];
        }

        $formattedEquips = [];
        $setTitles = [
            'loy' => 'الولاء', 'lion' => 'الأسد', 'dark' => 'الظلام', 'fearless' => 'الجسارة',
            'saton' => 'ساتون', 'holy' => 'المقدس', 'sparta' => 'سبارتا', 'volcan' => 'فولكان',
            'mili' => 'الحربي', 'fart' => 'فارتومنوس', 'peace' => 'القيصر/السلام', 'victory' => 'النصر',
            'mars' => 'مارس', 'bright' => 'الضوئي', 'khaled' => 'الخالد/الأخضر', 'blue' => 'الأبيض',
            'worrior' => 'المحارب', 'thunder' => 'الرعد', 'neptune' => 'نيبتون', 'thor' => 'ثور',
            'light' => 'النور'
        ];

        foreach ($equipsCatalog as $setKey => $setData) {
            $setName = $setTitles[$setKey] ?? $setKey;
            // 1. Primary parts (الأساسية: سيف، خوذة، درع، وقاء/معطف، حذاء)
            $primaryParts = ['sword', 'helmet', 'shield', 'armor', 'boot'];
            foreach ($primaryParts as $p) {
                if (isset($setData[$p])) {
                    $formattedEquips[] = [
                        'id'          => "{$setKey}_{$p}",
                        'type'        => $setKey,
                        'part'        => $p,
                        'category'    => 'أساسية (لا تتطور)',
                        'name'        => $setData[$p]['name'] ?? "{$setName} - {$p}",
                        'image'       => $setData[$p]['image'] ?? 'images/equip/equip0.jpg',
                        'desc'        => $setData[$p]['desc'] ?? '',
                        'can_upgrade' => false,
                        'max_lvl'     => 1
                    ];
                }
            }
            // 2. Secondary parts (الثانوية: حزام، قلادة، مشكاة، خاتم، حصان - تتطور م1 إلى م9)
            if (isset($setData['sec']) && is_array($setData['sec'])) {
                $secondaryParts = ['belt', 'necklace', 'pendant', 'ring', 'steed'];
                foreach ($secondaryParts as $sp) {
                    if (isset($setData['sec'][$sp]) && is_array($setData['sec'][$sp])) {
                        $firstLvl = $setData['sec'][$sp][0] ?? [];
                        $cleanName = preg_replace('/(\s*م\d+|\s*lvl\s*\d+)/u', '', $firstLvl['name'] ?? "{$setName} - {$sp}");
                        $formattedEquips[] = [
                            'id'          => "{$setKey}_{$sp}",
                            'type'        => $setKey,
                            'part'        => $sp,
                            'category'    => 'ثانوية (تتطور م1-م9)',
                            'name'        => trim($cleanName),
                            'image'       => $firstLvl['image'] ?? 'images/equip/equip5.jpg',
                            'desc'        => $firstLvl['desc'] ?? '',
                            'can_upgrade' => true,
                            'max_lvl'     => count($setData['sec'][$sp])
                        ];
                    }
                }
            }
        }

        jsonSuccess([
            'items'  => $formattedItems,
            'equips' => $formattedEquips
        ]);
        break;

    // ==========================================
    // 3. Players Management
    // ==========================================
    case 'get_players':
        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status'] ?? 'all';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = (int)($_GET['limit'] ?? 15);
        $limit = $limit > 0 ? min($limit, 250) : 15;
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if (!empty($search)) {
            $where[] = "(`name` LIKE ? OR `id_player` = ? OR `guild` LIKE ? OR EXISTS (
                SELECT 1 FROM `user_auth` WHERE `user_auth`.`id_player` = `player`.`id_player` AND `user_auth`.`username` LIKE ?
            ))";
            $params[] = "%$search%";
            $params[] = is_numeric($search) ? (int)$search : 0;
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $now = time();
        if ($status === 'banned') {
            $where[] = "(`panned` > $now OR `panned` = 1)";
        } elseif ($status === 'muted') {
            $where[] = "(`chat_panne` > $now OR `chat_panne` = 1)";
        } elseif ($status === 'online') {
            $where[] = "`online` = 1";
        }

        $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $countStmt = $db->prepare("SELECT COUNT(*) FROM `player` $whereSql");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $query = "SELECT `id_player`, `name`, `gold`, `prestige`, `honor`, `guild`, `porm`, `rank`, `user_group`, `online`, `panned`, `chat_panne`, `last_seen` 
                  FROM `player` $whereSql 
                  ORDER BY `id_player` ASC 
                  LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Check active bans
        foreach ($players as &$p) {
            $p['is_banned'] = ($p['panned'] > $now || $p['panned'] == 1);
            $p['is_muted']  = ($p['chat_panne'] > $now || $p['chat_panne'] == 1);
        }

        jsonSuccess([
            'players'     => $players,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => ceil($total / $limit)
        ]);
        break;

    case 'get_player_details':
        $idPlayer = (int)($_GET['id_player'] ?? 0);
        if (!$idPlayer) jsonError('معرف اللاعب غير صحيح');

        // Player basic
        $pStmt = $db->prepare("SELECT * FROM `player` WHERE `id_player` = ?");
        $pStmt->execute([$idPlayer]);
        $player = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$player) jsonError('لم يتم العثور على اللاعب المطلوب');

        // Cities
        $cStmt = $db->prepare("SELECT `id_city`, `name`, `x`, `y`, `lvl`, `coin`, `food`, `wood`, `stone`, `metal`, `pop`, `loy`, `dis_loy`, `army_a`, `army_b`, `army_c`, `army_d`, `army_e`, `army_f` FROM `city` WHERE `id_player` = ?");
        $cStmt->execute([$idPlayer]);
        $cities = $cStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Items with catalog enrichment
        $iStmt = $db->prepare("SELECT * FROM `player_item` WHERE `id_player` = ? ORDER BY `amount` DESC");
        $iStmt->execute([$idPlayer]);
        $items = $iStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$it) {
            $meta = $itemsCatalog[$it['id_item']] ?? null;
            $it['name'] = $meta['name'] ?? $it['id_item'];
            $it['image'] = $meta['image'] ?? 'images/items/item000.jpg';
        }

        // Equips with catalog enrichment
        $eStmt = $db->prepare("SELECT * FROM `player_equip` WHERE `id_player` = ? ORDER BY `lvl` DESC");
        $eStmt->execute([$idPlayer]);
        $equips = $eStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($equips as &$eq) {
            $set = $eq['type'];
            $part = $eq['part'];
            $meta = null;
            if (isset($equipsCatalog[$set][$part])) {
                $meta = $equipsCatalog[$set][$part];
            } elseif (isset($equipsCatalog[$set]['sec'][$part])) {
                $lvlIdx = max(0, min(8, ((int)$eq['lvl']) - 1));
                $meta = $equipsCatalog[$set]['sec'][$part][$lvlIdx] ?? ($equipsCatalog[$set]['sec'][$part][0] ?? null);
            }
            $eq['name'] = $meta['name'] ?? "{$set} {$part}";
            $eq['image'] = $meta['image'] ?? 'images/equip/equip0.jpg';
        }

        // Luxuries & State
        $sStmt = $db->prepare("SELECT * FROM `player_state` WHERE `id_player` = ?");
        $sStmt->execute([$idPlayer]);
        $state = $sStmt->fetch(PDO::FETCH_ASSOC);
        if (!$state) {
            $db->prepare("INSERT IGNORE INTO `player_state` (`id_player`) VALUES (?)")->execute([$idPlayer]);
            $state = ['id_player' => $idPlayer, 'peace' => 0, 'motiv' => 0, 'prot_pop' => 0, 'medical' => 0, 'wheat' => 0, 'wood' => 0, 'stone' => 0, 'metal' => 0, 'coin' => 0, 'attack_10' => 0, 'defence_10' => 0];
        }

        // Heroes
        $hStmt = $db->prepare("SELECT `id_hero`, `name`, `lvl`, `point_b` as attack, `point_c` as defense, `point_a` as leadership, `power`, `loyal` AS `loy` FROM `city_hero` WHERE `id_player` = ?");
        $hStmt->execute([$idPlayer]);
        $heroes = $hStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        jsonSuccess([
            'player' => $player,
            'cities' => $cities,
            'items'  => $items,
            'equips' => $equips,
            'state'  => $state,
            'heroes' => $heroes
        ]);
        break;

    case 'save_player':
        requirePermission('manage_players');
        $idPlayer = (int)($_POST['id_player'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $gold = (int)($_POST['gold'] ?? 0);
        $prestige = (int)($_POST['prestige'] ?? 0);
        $honor = (int)($_POST['honor'] ?? 0);
        $rank = (int)($_POST['rank'] ?? 0);
        $userGroup = (int)($_POST['user_group'] ?? 0);

        if (!$idPlayer || empty($name)) jsonError('بيانات التعديل غير مكتملة');

        $stmt = $db->prepare("UPDATE `player` SET `name` = ?, `gold` = ?, `prestige` = ?, `honor` = ?, `rank` = ?, `user_group` = ? WHERE `id_player` = ?");
        $stmt->execute([$name, $gold, $prestige, $honor, $rank, $userGroup, $idPlayer]);

        logAdminAction('edit_player', 'player', $idPlayer, $name, [
            'gold' => $gold, 'prestige' => $prestige, 'honor' => $honor, 'rank' => $rank, 'user_group' => $userGroup
        ]);

        jsonSuccess([], 'تم تحديث بيانات اللاعب بنجاح');
        break;

    // ==========================================
    // 4. Ban & Chat Mute Management
    // ==========================================
    case 'ban_player':
        requirePermission('ban_players');
        $idPlayer = (int)($_POST['id_player'] ?? 0);
        $duration = (int)($_POST['duration'] ?? 86400); // in seconds, or 9999999999 for perm
        $reason = trim($_POST['reason'] ?? 'مخالفة قوانين اللعبة');

        if (!$idPlayer) jsonError('معرف اللاعب غير صالح');

        $pannedUntil = ($duration >= 999999999) ? 2147483647 : (time() + $duration);

        $stmt = $db->prepare("UPDATE `player` SET `panned` = ?, `online` = 0 WHERE `id_player` = ?");
        $stmt->execute([$pannedUntil, $idPlayer]);

        // Get player name for logging
        $name = $db->query("SELECT name FROM player WHERE id_player = $idPlayer")->fetchColumn();

        logAdminAction('ban_account', 'player', $idPlayer, $name, [
            'duration' => $duration,
            'reason'   => $reason,
            'until'    => date('Y-m-d H:i:s', $pannedUntil)
        ]);

        jsonSuccess([], 'تم حظر حساب اللاعب بنجاح');
        break;

    case 'unban_player':
        requirePermission('ban_players');
        $idPlayer = (int)($_POST['id_player'] ?? 0);
        if (!$idPlayer) jsonError('معرف اللاعب غير صالح');

        $stmt = $db->prepare("UPDATE `player` SET `panned` = 0 WHERE `id_player` = ?");
        $stmt->execute([$idPlayer]);

        $name = $db->query("SELECT name FROM player WHERE id_player = $idPlayer")->fetchColumn();
        logAdminAction('unban_account', 'player', $idPlayer, $name, 'رفع الحظر عن الحساب');

        jsonSuccess([], 'تم رفع الحظر عن اللاعب بنجاح');
        break;

    case 'mute_player':
        requirePermission('ban_players');
        $idPlayer = (int)($_POST['id_player'] ?? 0);
        $duration = (int)($_POST['duration'] ?? 86400);
        $reason = trim($_POST['reason'] ?? 'مخالفة شروط المحادثة');

        if (!$idPlayer) jsonError('معرف اللاعب غير صالح');

        $muteUntil = ($duration >= 999999999) ? 2147483647 : (time() + $duration);

        $stmt = $db->prepare("UPDATE `player` SET `chat_panne` = ? WHERE `id_player` = ?");
        $stmt->execute([$muteUntil, $idPlayer]);

        $name = $db->query("SELECT name FROM player WHERE id_player = $idPlayer")->fetchColumn();
        logAdminAction('mute_chat', 'player', $idPlayer, $name, [
            'duration' => $duration,
            'reason'   => $reason,
            'until'    => date('Y-m-d H:i:s', $muteUntil)
        ]);

        jsonSuccess([], 'تم كتم وحظر اللاعب من الشات بنجاح');
        break;

    case 'unmute_player':
        requirePermission('ban_players');
        $idPlayer = (int)($_POST['id_player'] ?? 0);
        if (!$idPlayer) jsonError('معرف اللاعب غير صالح');

        $stmt = $db->prepare("UPDATE `player` SET `chat_panne` = 0 WHERE `id_player` = ?");
        $stmt->execute([$idPlayer]);

        $name = $db->query("SELECT name FROM player WHERE id_player = $idPlayer")->fetchColumn();
        logAdminAction('unmute_chat', 'player', $idPlayer, $name, 'رفع حظر وكتم الشات');

        jsonSuccess([], 'تم رفع كتم الشات عن اللاعب بنجاح');
        break;

    // ==========================================
    // 5. City & Resources Management
    // ==========================================
    case 'bulk_event_rewards':
        $rawRewards = $_POST['rewards'] ?? '';
        $rewards = is_string($rawRewards) ? json_decode($rawRewards, true) : null;
        if (!is_array($rewards)) {
            jsonError('بيانات المكافأة غير صالحة');
        }

        $gold = filter_var($rewards['gold'] ?? 0, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 2147483647],
        ]);
        if ($gold === false) {
            jsonError('كمية الذهب غير صالحة');
        }

        $resourceAmounts = [];
        foreach (['food', 'wood', 'stone', 'metal', 'coin'] as $resource) {
            $amount = filter_var($rewards['city_resources'][$resource] ?? 0, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0, 'max_range' => 2147483647],
            ]);
            if ($amount === false) {
                jsonError('بيانات موارد المدن غير صالحة');
            }
            $resourceAmounts[$resource] = $amount;
        }

        $items = $rewards['items'] ?? [];
        $equips = $rewards['equips'] ?? [];
        if (!is_array($items) || !is_array($equips) || count($items) > 20 || count($equips) > 20) {
            jsonError('عدد العناصر أو العتاد يتجاوز الحد المسموح');
        }

        $validatedItems = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['id'] ?? null)) {
                jsonError('بيانات أحد العناصر غير صالحة');
            }
            $itemId = trim($item['id']);
            $amount = filter_var($item['amount'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 1000000],
            ]);
            if ($itemId === '' || !isset($itemsCatalog[$itemId]) || $amount === false) {
                jsonError('العنصر أو كميته غير صالحة');
            }
            $validatedItems[] = [
                'id' => $itemId,
                'name' => $itemsCatalog[$itemId]['name'] ?? $itemId,
                'amount' => $amount,
            ];
        }

        $validatedEquips = [];
        foreach ($equips as $equip) {
            if (!is_array($equip) || !is_string($equip['id'] ?? null)) {
                jsonError('بيانات إحدى قطع العتاد غير صالحة');
            }
            $equipId = trim($equip['id']);
            if (!preg_match('/^(.+)_(sword|helmet|shield|armor|boot|belt|necklace|pendant|ring|steed)$/', $equipId, $matches)) {
                jsonError('نوع قطعة العتاد غير صالح');
            }

            $type = $matches[1];
            $part = $matches[2];
            $isPrimary = in_array($part, $primaryEquipParts, true);
            $level = filter_var($equip['level'] ?? 1, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 9],
            ]);
            $amount = filter_var($equip['amount'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 100],
            ]);
            if ($level === false || $amount === false) {
                jsonError('مستوى أو كمية العتاد غير صالحة');
            }
            if ($isPrimary) {
                $level = 1;
            }
            $catalogId = "{$type}_{$part}_{$level}";
            if (!isset($flatEquipsCatalog[$catalogId])) {
                jsonError('قطعة العتاد أو مستواها غير موجودة في الكتالوج');
            }

            $validatedEquips[] = [
                'type' => $type,
                'part' => $part,
                'level' => $level,
                'amount' => $amount,
                'name' => $flatEquipsCatalog[$catalogId]['name'] ?? $catalogId,
            ];
        }

        $hasCityResources = array_sum($resourceAmounts) > 0;
        if ($gold === 0 && !$hasCityResources && !$validatedItems && !$validatedEquips) {
            jsonError('اختر مكافأة واحدة على الأقل قبل الإرسال');
        }
        if ($gold > 0) {
            requirePermission('manage_players');
        }
        if ($validatedItems) {
            requirePermission('manage_items');
        }
        if ($validatedEquips) {
            requirePermission('manage_equip');
        }
        if ($hasCityResources) {
            requirePermission('edit_resources');
        }

        try {
            $db->beginTransaction();
            $playerCount = (int)$db->query('SELECT COUNT(*) FROM `player`')->fetchColumn();
            if ($playerCount === 0) {
                $db->rollBack();
                jsonError('لا يوجد لاعبون لإرسال المكافأة إليهم', 404);
            }

            if ($gold > 0) {
                $db->prepare('UPDATE `player` SET `gold` = LEAST(CAST(`gold` AS DECIMAL(20, 0)) + ?, 2147483647)')
                    ->execute([$gold]);
            }

            foreach ($validatedItems as $item) {
                $stmt = $db->prepare(
                    "INSERT INTO `player_item` (`id_player`, `id_item`, `amount`, `bound`)
                     SELECT `id_player`, ?, ?, 'T' FROM `player` WHERE 1
                     ON DUPLICATE KEY UPDATE `amount` = LEAST(CAST(`amount` AS DECIMAL(20, 0)) + VALUES(`amount`), 2147483647)"
                );
                $stmt->execute([$item['id'], $item['amount']]);
            }

            foreach ($validatedEquips as $equip) {
                $stmt = $db->prepare(
                    'INSERT INTO `player_equip` (`id_player`, `id_hero`, `type`, `part`, `lvl`, `on_hero`)
                     SELECT `id_player`, 0, ?, ?, ?, 0 FROM `player`'
                );
                for ($copy = 0; $copy < $equip['amount']; $copy++) {
                    $stmt->execute([$equip['type'], $equip['part'], $equip['level']]);
                }
            }

            $cityCount = 0;
            if ($hasCityResources) {
                $cityCount = (int)$db->query(
                    'SELECT COUNT(*) FROM `city` WHERE `id_player` IN (SELECT `id_player` FROM `player`)'
                )->fetchColumn();
                if ($cityCount > 0) {
                    $updates = [];
                    $params = [];
                    foreach ($resourceAmounts as $resource => $amount) {
                        if ($amount > 0) {
                            $updates[] = "`$resource` = LEAST(CAST(`$resource` AS DECIMAL(20, 0)) + ?, 2147483647)";
                            $params[] = $amount;
                        }
                    }
                    $updateStmt = $db->prepare(
                        'UPDATE `city` SET ' . implode(', ', $updates)
                        . ' WHERE `id_player` IN (SELECT `id_player` FROM `player`)'
                    );
                    $updateStmt->execute($params);
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Admin bulk event rewards] ' . $e->getMessage());
            jsonError('تعذر إرسال مكافأة الفعالية حالياً', 500);
        }

        logAdminAction('bulk_event_rewards', 'all_players', null, 'جميع اللاعبين', [
            'gold_per_player' => $gold,
            'items' => $validatedItems,
            'equips' => $validatedEquips,
            'city_resources_per_city' => $resourceAmounts,
            'players' => $playerCount,
            'cities' => $cityCount,
        ]);
        jsonSuccess([
            'players' => $playerCount,
            'cities' => $cityCount,
        ], 'تم إرسال مكافأة الفعالية إلى ' . $playerCount . ' لاعب' . ($hasCityResources ? '، وموارد المدن إلى ' . $cityCount . ' مدينة' : ''));
        break;

    case 'update_city_resources':
        requirePermission('edit_resources');
        $idCity = filter_var($_POST['id_city'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $resourceValues = [];
        foreach (['food', 'wood', 'stone', 'metal', 'coin'] as $resource) {
            $resourceValues[$resource] = filter_var(
                $_POST[$resource] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => 2147483647]]
            );
        }
        $loy = filter_var($_POST['loy'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($idCity === false || $loy === false || in_array(false, $resourceValues, true)) {
            jsonError('بيانات موارد المدينة غير صالحة');
        }
        [$food, $wood, $stone, $metal, $coin] = array_values($resourceValues);

        $stmt = $db->prepare("UPDATE `city` SET `food` = ?, `wood` = ?, `stone` = ?, `metal` = ?, `coin` = ?, `loy` = ?, `dis_loy` = 0 WHERE `id_city` = ?");
        $stmt->execute([$food, $wood, $stone, $metal, $coin, $loy, $idCity]);

        $cityName = $db->query("SELECT name FROM city WHERE id_city = $idCity")->fetchColumn();
        logAdminAction('edit_resources', 'city', $idCity, $cityName, [
            'food' => $food, 'wood' => $wood, 'stone' => $stone, 'metal' => $metal, 'coin' => $coin, 'loy' => $loy
        ]);

        jsonSuccess([], 'تم تحديث موارد المدينة بنجاح');
        break;

    case 'quick_boost_city':
        requirePermission('edit_resources');
        $idCity = (int)($_POST['id_city'] ?? 0);
        $type = $_POST['type'] ?? 'max_res';

        if (!$idCity) jsonError('معرف المدينة غير صالح');

        if ($type === 'max_res') {
            $amount = 100000000; // 100 million
            $db->prepare("UPDATE `city` SET `food` = ?, `wood` = ?, `stone` = ?, `metal` = ?, `coin` = ? WHERE `id_city` = ?")
               ->execute([$amount, $amount, $amount, $amount, $amount, $idCity]);
            $msg = 'تم تعبئة موارد المدينة بـ 100 مليون من كل نوع!';
        } elseif ($type === 'max_loy_pop') {
            $db->prepare("UPDATE `city` SET `loy` = 100, `dis_loy` = 0, `pop` = `pop_cap` WHERE `id_city` = ?")
               ->execute([$idCity]);
            $msg = 'تم رفع ولاء المدينة لـ 100% وملء السكان بالكامل!';
        } elseif ($type === 'max_army') {
            $add = 50000;
            $db->prepare("UPDATE `city` SET `army_a` = `army_a` + ?, `army_b` = `army_b` + ?, `army_c` = `army_c` + ?, `army_d` = `army_d` + ?, `army_e` = `army_e` + ?, `army_f` = `army_f` + ? WHERE `id_city` = ?")
               ->execute([$add, $add, $add, $add, $add, $add, $idCity]);
            $msg = 'تم إضافة 50,000 مقاتل من كل نوع لثكنات المدينة!';
        } else {
            jsonError('نوع التعزيز غير معروف');
        }

        $cityName = $db->query("SELECT name FROM city WHERE id_city = $idCity")->fetchColumn();
        logAdminAction('quick_boost_city', 'city', $idCity, $cityName, ['type' => $type, 'action' => $msg]);

        jsonSuccess([], $msg);
        break;

    // ==========================================
    // 6. Items & Luxuries Management
    // ==========================================
    case 'grant_item':
        requirePermission('manage_items');
        $idPlayer = (int)($_POST['id_player'] ?? 0);
        $idItem   = trim($_POST['id_item'] ?? '');
        $amount   = max(1, (int)($_POST['amount'] ?? 1));

        if (!$idPlayer || empty($idItem)) jsonError('بيانات المادة غير مكتملة');

        $stmt = $db->prepare("INSERT INTO `player_item` (`id_player`, `id_item`, `amount`, `bound`) VALUES (?, ?, ?, 'T') ON DUPLICATE KEY UPDATE `amount` = `amount` + VALUES(`amount`)");
        $stmt->execute([$idPlayer, $idItem, $amount]);

        $itemName = $itemsCatalog[$idItem]['name'] ?? $idItem;
        $playerName = $db->query("SELECT name FROM player WHERE id_player = $idPlayer")->fetchColumn();
        logAdminAction('grant_item', 'player', $idPlayer, $playerName, [
            'item_id' => $idItem, 'item_name' => $itemName, 'amount' => $amount
        ]);

        jsonSuccess([], "تم إضافة $amount × $itemName لحقيبة اللاعب بنجاح");
        break;

    case 'delete_item':
        requirePermission('manage_items');
        $idPlayer = (int)($_POST['id_player'] ?? 0);
        $idItem   = trim($_POST['id_item'] ?? '');

        if (!$idPlayer || empty($idItem)) jsonError('بيانات المادة غير مكتملة');

        $stmt = $db->prepare("DELETE FROM `player_item` WHERE `id_player` = ? AND `id_item` = ?");
        $stmt->execute([$idPlayer, $idItem]);

        $playerName = $db->query("SELECT name FROM player WHERE id_player = $idPlayer")->fetchColumn();
        logAdminAction('delete_item', 'player', $idPlayer, $playerName, ['item' => $idItem]);

        jsonSuccess([], 'تم حذف المادة من حقيبة اللاعب');
        break;

    case 'vip_boost_luxuries':
        requirePermission('manage_luxuries');
        $idPlayer = (int)($_POST['id_player'] ?? 0);
        $days     = max(1, min(365, (int)($_POST['days'] ?? 30)));

        if (!$idPlayer) jsonError('معرف اللاعب غير صالح');

        $futureTime = time() + ($days * 86400);

        $stmt = $db->prepare("INSERT INTO `player_state` (`id_player`, `peace`, `motiv`, `prot_pop`, `medical`, `wheat`, `wood`, `stone`, `metal`, `coin`, `attack_10`, `defence_10`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            `peace` = VALUES(`peace`), `motiv` = VALUES(`motiv`), `prot_pop` = VALUES(`prot_pop`), `medical` = VALUES(`medical`),
            `wheat` = VALUES(`wheat`), `wood` = VALUES(`wood`), `stone` = VALUES(`stone`), `metal` = VALUES(`metal`), `coin` = VALUES(`coin`),
            `attack_10` = VALUES(`attack_10`), `defence_10` = VALUES(`defence_10`)");

        $stmt->execute([
            $idPlayer, $futureTime, $futureTime, $futureTime, $futureTime,
            $futureTime, $futureTime, $futureTime, $futureTime, $futureTime,
            $futureTime, $futureTime
        ]);

        $playerName = $db->query("SELECT name FROM player WHERE id_player = $idPlayer")->fetchColumn();
        logAdminAction('vip_boost_luxuries', 'player', $idPlayer, $playerName, [
            'days' => $days, 'until' => date('Y-m-d H:i:s', $futureTime)
        ]);

        jsonSuccess([], "تم تفعيل حزمة الرفاهيات الملكية الشاملة لمدة $days يوم بنجاح!");
        break;

    // ==========================================
    // 7. Equipment Management
    // ==========================================
    case 'grant_equip':
        requirePermission('manage_equip');
        $idPlayer = (int)($_POST['id_player'] ?? 0);
        $rawType  = trim($_POST['type'] ?? '');
        $part     = trim($_POST['part'] ?? '');
        $lvl      = max(1, min(9, (int)($_POST['lvl'] ?? 1)));

        if (!$idPlayer || empty($rawType)) jsonError('بيانات العتاد غير مكتملة');

        $type = $rawType;
        if (strpos($rawType, '_') !== false && empty($part)) {
            [$type, $part] = explode('_', $rawType, 2);
        }

        if (empty($part)) {
            $part = 'sword';
            if (strpos($type, 'helmet') !== false) $part = 'helmet';
            elseif (strpos($type, 'shield') !== false) $part = 'shield';
            elseif (strpos($type, 'armor') !== false)  $part = 'armor';
            elseif (strpos($type, 'boot') !== false)   $part = 'boot';
            elseif (strpos($type, 'belt') !== false)   $part = 'belt';
            elseif (strpos($type, 'necklace') !== false) $part = 'necklace';
            elseif (strpos($type, 'pendant') !== false)  $part = 'pendant';
            elseif (strpos($type, 'ring') !== false)     $part = 'ring';
            elseif (strpos($type, 'steed') !== false)    $part = 'steed';
        }

        // For primary equipments, level is fixed at 1
        $primaryParts = ['sword', 'helmet', 'shield', 'armor', 'boot'];
        if (in_array($part, $primaryParts)) {
            $lvl = 1;
        }

        $stmt = $db->prepare("INSERT INTO `player_equip` (`id_player`, `id_hero`, `type`, `part`, `lvl`, `on_hero`) VALUES (?, 0, ?, ?, ?, 0)");
        $stmt->execute([$idPlayer, $type, $part, $lvl]);

        $meta = $equipsCatalog[$type][$part] ?? ($equipsCatalog[$type]['sec'][$part][0] ?? null);
        $equipName = $meta['name'] ?? "{$type} {$part}";
        $playerName = $db->query("SELECT name FROM player WHERE id_player = $idPlayer")->fetchColumn();
        logAdminAction('grant_equip', 'player', $idPlayer, $playerName, [
            'equip_type' => $type, 'part' => $part, 'name' => $equipName, 'level' => $lvl
        ]);

        jsonSuccess([], "تم إضافة $equipName (مستوى $lvl) بنجاح!");
        break;

    case 'delete_equip':
        requirePermission('manage_equip');
        $idEquip = (int)($_POST['id_equip'] ?? 0);
        if (!$idEquip) jsonError('معرف المعدة غير صالح');

        $eq = $db->query("SELECT * FROM player_equip WHERE id_equip = $idEquip")->fetch(PDO::FETCH_ASSOC);
        if ($eq) {
            $db->prepare("DELETE FROM `player_equip` WHERE `id_equip` = ?")->execute([$idEquip]);
            logAdminAction('delete_equip', 'equip', $idEquip, $eq['type'], "حذف قطعة العتاد");
        }

        jsonSuccess([], 'تم حذف قطعة العتاد بنجاح');
        break;

    case 'upgrade_equip':
        requirePermission('manage_equip');
        $idEquip = (int)($_POST['id_equip'] ?? 0);
        $lvl = max(1, min(15, (int)($_POST['lvl'] ?? 1)));
        if (!$idEquip) jsonError('معرف المعدة غير صالح');

        $db->prepare("UPDATE `player_equip` SET `lvl` = ? WHERE `id_equip` = ?")->execute([$lvl, $idEquip]);
        logAdminAction('upgrade_equip', 'equip', $idEquip, null, "تعديل مستوى القطعة إلى $lvl");

        jsonSuccess([], "تم تعديل مستوى العتاد إلى $lvl بنجاح");
        break;

    // ==========================================
    // 8. Admins & Roles Management
    // ==========================================
    case 'get_admins':
        requirePermission('manage_admins');
        $stmt = $db->query("SELECT `id_admin`, `username`, `name`, `role`, `permissions`, `status`, `last_login`, `created_at` FROM `admin_user` ORDER BY `id_admin` ASC");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($admins as &$a) {
            $a['permissions'] = json_decode($a['permissions'] ?: '{}', true);
        }
        jsonSuccess($admins);
        break;

    case 'save_admin':
        requirePermission('manage_admins');
        $idAdmin  = (int)($_POST['id_admin'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $role     = trim($_POST['role'] ?? 'moderator');
        $statusProvided = array_key_exists('status', $_POST);
        $permissionsProvided = array_key_exists('permissions', $_POST);
        $status = $statusProvided ? (int)$_POST['status'] : 1;
        $perms = $permissionsProvided ? $_POST['permissions'] : [];

        if ($idAdmin > 0 && (!$statusProvided || !$permissionsProvided)) {
            $stmt = $db->prepare('SELECT `status`, `permissions` FROM `admin_user` WHERE `id_admin` = ?');
            $stmt->execute([$idAdmin]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) jsonError('الحساب غير موجود');

            if (!$statusProvided) {
                $status = (int)$existing['status'];
            }
            if (!$permissionsProvided) {
                $storedPerms = json_decode($existing['permissions'] ?: '{}', true);
                $perms = is_array($storedPerms) ? $storedPerms : [];
            }
        }

        if (!preg_match('/\A[a-zA-Z0-9._-]{3,50}\z/', $username) || $name === '') {
            jsonError('اسم المستخدم أو الاسم الظاهر غير صالح');
        }
        if (($idAdmin === 0 && (strlen($password) < 12 || strlen($password) > 72))
            || ($password !== '' && (strlen($password) < 12 || strlen($password) > 72))) {
            jsonError('كلمة المرور يجب أن تكون من 12 إلى 72 خانة');
        }

        $allowedRoles = ['super_admin', 'admin', 'supervisor', 'moderator'];
        if (!in_array($role, $allowedRoles, true)) jsonError('الرتبة المحددة غير صالحة');

        $currentUser = getAdminUser();
        if ($currentUser['role'] !== 'super_admin') {
            if (in_array($role, ['super_admin', 'admin'], true)) {
                jsonError('فقط المدير التنفيذي يمكنه منح هذه الرتبة');
            }
            if ($idAdmin > 0) {
                $targetRoleStmt = $db->prepare('SELECT `role` FROM `admin_user` WHERE `id_admin` = ?');
                $targetRoleStmt->execute([$idAdmin]);
                if (in_array($targetRoleStmt->fetchColumn(), ['super_admin', 'admin'], true)) {
                    jsonError('لا يمكنك تعديل حساب مدير إداري أعلى منك');
                }
            }
            if (!is_array($perms)) {
                jsonError('قائمة الصلاحيات غير صالحة');
            }
            foreach ($perms as $permission => $enabled) {
                if ($permissionsProvided && $enabled && ($permission === 'all' || !hasPermission((string)$permission))) {
                    jsonError('لا يمكنك منح صلاحية لا تملكها');
                }
            }
        }

        $permsJson = json_encode($perms, JSON_UNESCAPED_UNICODE);

        if ($idAdmin > 0) {
            // Update
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("UPDATE `admin_user` SET `username` = ?, `password` = ?, `name` = ?, `role` = ?, `permissions` = ?, `status` = ? WHERE `id_admin` = ?");
                $stmt->execute([$username, $hash, $name, $role, $permsJson, $status, $idAdmin]);
            } else {
                $stmt = $db->prepare("UPDATE `admin_user` SET `username` = ?, `name` = ?, `role` = ?, `permissions` = ?, `status` = ? WHERE `id_admin` = ?");
                $stmt->execute([$username, $name, $role, $permsJson, $status, $idAdmin]);
            }
            logAdminAction('edit_admin', 'admin', $idAdmin, $username, "تحديث بيانات المشرف/المراقب");
            $msg = 'تم تحديث حساب المشرف بنجاح';
        } else {
            // Create
            if (empty($password)) jsonError('كلمة المرور مطلوبة للحسابات الجديدة');
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO `admin_user` (`username`, `password`, `name`, `role`, `permissions`, `status`) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $name, $role, $permsJson, $status]);
            $newId = $db->lastInsertId();
            logAdminAction('create_admin', 'admin', $newId, $username, "إنشاء حساب مشرف برتبة $role");
            $msg = 'تم إنشاء الحساب الإداري الجديد بنجاح';
        }

        jsonSuccess([], $msg);
        break;

    case 'delete_admin':
        requirePermission('manage_admins');
        $idAdmin = (int)($_POST['id_admin'] ?? 0);
        $currentUser = getAdminUser();

        if ($idAdmin === $currentUser['id_admin']) {
            jsonError('لا يمكنك حذف حسابك الشخصي المسجل به حالياً!');
        }

        $target = $db->query("SELECT * FROM `admin_user` WHERE `id_admin` = $idAdmin")->fetch(PDO::FETCH_ASSOC);
        if (!$target) jsonError('الحساب غير موجود');

        if ($target['role'] === 'super_admin' && $currentUser['role'] !== 'super_admin') {
            jsonError('لا تملك الصلاحية لحذف حساب مدير تنفيذي');
        }

        $db->prepare("DELETE FROM `admin_user` WHERE `id_admin` = ?")->execute([$idAdmin]);
        logAdminAction('delete_admin', 'admin', $idAdmin, $target['username'], "حذف الحساب الإداري");

        jsonSuccess([], 'تم حذف الحساب بنجاح');
        break;

    // ==========================================
    // 9. Audit Logs
    // ==========================================
    case 'get_logs':
        requirePermission('view_logs');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $total = (int)$db->query("SELECT COUNT(*) FROM `admin_log`")->fetchColumn();
        $stmt = $db->prepare("SELECT * FROM `admin_log` ORDER BY `id_log` DESC LIMIT $limit OFFSET $offset");
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        jsonSuccess([
            'logs'        => $logs,
            'total'       => $total,
            'page'        => $page,
            'total_pages' => ceil($total / $limit)
        ]);
        break;

    default:
        jsonError('الإجراء المطلوب غير صالح: ' . htmlspecialchars($action));
        break;
}

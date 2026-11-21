<?php
// Dynamic player.php for caesar-web
require_once __DIR__ . '/../../caesar-api/db.php';
require_once __DIR__ . '/../../caesar-api/PlayerAuth.php';

header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$db = getDB();

// 1. Return player's cities
if (isset($_POST['get_city']) || isset($_GET['get_city'])) {
    $token = $_POST['token'] ?? $_GET['token'] ?? '';
    if (!is_string($token) || $token === '') {
        http_response_code(401);
        echo json_encode(['state' => 'error_auth']);
        exit;
    }

    $playerStmt = $db->prepare("SELECT id_player FROM `player` WHERE `p_token` = ? LIMIT 1");
    $playerStmt->execute([$token]);
    $idPlayer = (int)$playerStmt->fetchColumn();
    if ($idPlayer <= 0) {
        http_response_code(401);
        echo json_encode(['state' => 'error_auth']);
        exit;
    }

    $requestedPlayerId = $_POST['id_player'] ?? $_GET['id_player'] ?? $idPlayer;
    $requestedPlayerId = filter_var($requestedPlayerId, FILTER_VALIDATE_INT);
    if ($requestedPlayerId === false || $requestedPlayerId !== $idPlayer) {
        http_response_code(403);
        echo json_encode(['state' => 'error_player_owner']);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM `city` WHERE `id_player` = ?");
    $stmt->execute([$idPlayer]);
    $cities = $stmt->fetchAll();
    foreach ($cities as &$c) {
        foreach ($c as $k => $v) {
            if (is_numeric($v)) $c[$k] = (int)$v;
        }
    }
    echo json_encode($cities, JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Player Details for Profile View
if (isset($_GET['GET_PLAYER_DETAIL']) || isset($_POST['GET_PLAYER_DETAIL'])) {
    $idTarget = (int)($_GET['id_player'] ?? $_POST['id_player'] ?? 0);
    $stmt = $db->prepare("SELECT
            p.id_player, p.name, p.avatar, p.prestige, p.honor, p.porm, p.id_guild,
            COALESCE(g.name, p.guild, '') as guild
        FROM player p
        LEFT JOIN guild g ON p.id_guild = g.id_guild
        WHERE p.id_player = ? LIMIT 1");
    $stmt->execute([$idTarget]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($player) {
        $player['id_player'] = (int)$player['id_player'];
        $player['avatar']    = (int)($player['avatar'] ?: 1);
        $player['prestige']  = (int)$player['prestige'];
        $player['honor']     = (int)$player['honor'];
        $player['porm']      = (int)($player['porm'] ?: 0);

        $rankStmt = $db->prepare("SELECT COUNT(*) + 1 FROM player WHERE prestige > ? OR (prestige = ? AND honor > ?)");
        $rankStmt->execute([$player['prestige'], $player['prestige'], $player['honor']]);
        $player['rank'] = (int)$rankStmt->fetchColumn();
    } else {
        $player = [
            'id_player' => $idTarget,
            'name'      => '-----',
            'avatar'    => 1,
            'prestige'  => 0,
            'honor'     => 0,
            'porm'      => 0,
            'guild'     => '----',
            'rank'      => 0
        ];
    }

    echo json_encode($player, JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Add Friend
if (isset($_POST['add_friend']) || isset($_GET['add_friend'])) {
    $sender = requireAuthenticatedPlayer($db);
    $idSender = (int)$sender['id_player'];
    $senderName = $sender['name'] ?: 'لاعب';

    $idFriend = (int)($_POST['id_friend'] ?? $_GET['id_friend'] ?? 0);

    if ($idSender <= 0) {
        http_response_code(401);
        echo json_encode(['state' => 'error', 'message' => 'جلسة غير صالحة'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($idFriend <= 0 || $idFriend === $idSender) {
        echo json_encode(['state' => 'error', 'message' => 'لا يمكنك إضافة نفسك كصديق'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $targetStmt = $db->prepare("SELECT id_player, name FROM player WHERE id_player = ? LIMIT 1");
    $targetStmt->execute([$idFriend]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        echo json_encode(['state' => 'error', 'message' => 'اللاعب غير موجود'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $targetName = $target['name'] ?: 'لاعب';

    // Ensure player_friend table exists
    $db->exec("CREATE TABLE IF NOT EXISTS `player_friend` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `id_player` INT NOT NULL,
        `id_friend` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_friend` (`id_player`, `id_friend`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $db->beginTransaction();

        $checkStmt = $db->prepare("SELECT COUNT(*) FROM player_friend WHERE (id_player = ? AND id_friend = ?) OR (id_player = ? AND id_friend = ?)");
        $checkStmt->execute([$idSender, $idFriend, $idFriend, $idSender]);
        if ((int)$checkStmt->fetchColumn() > 0) {
            $db->rollBack();
            echo json_encode(['state' => 'error', 'message' => 'هذا اللاعب موجود بالفعل في قائمة أصدقائك أو تم إرسال الطلب مسبقاً'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $ins = $db->prepare("INSERT INTO `player_friend` (`id_player`, `id_friend`) VALUES (?, ?)");
        try {
            $ins->execute([$idSender, $idFriend]);
        } catch (PDOException $error) {
            if ((string)$error->getCode() === '23000') {
                $db->rollBack();
                echo json_encode(['state' => 'error', 'message' => 'هذا اللاعب موجود بالفعل في قائمة أصدقائك أو تم إرسال الطلب مسبقاً'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            throw $error;
        }

        $subject = 'طلب صداقة';
        $body = 'يرغب اللاعب ' . $senderName . ' في إضافتك إلى قائمة الأصدقاء.';
        $msgStmt = $db->prepare("INSERT INTO `msg_income` (`id_player`, `from_`, `from_name`, `subject`, `body`, `seen`, `time_stamp`) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        $msgStmt->execute([$idFriend, $idSender, $senderName, $subject, $body]);

        $msgOut = $db->prepare("INSERT INTO `msg_out` (`id_player`, `to_player`, `to_name`, `subject`, `body`, `time_stamp`) VALUES (?, ?, ?, ?, ?, NOW())");
        $msgOut->execute([$idSender, $idFriend, $targetName, $subject, 'تم إرسال طلب صداقة إلى اللاعب ' . $targetName . '.']);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    echo json_encode(['state' => 'ok', 'message' => 'تم إرسال طلب الصداقة بنجاح'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Server Data
if (isset($_GET['SERVER_DATA'])) {
    $stmt   = $db->query("SELECT count(*) FROM `player`");
    $pCount = (int)$stmt->fetchColumn();
    $cStmt  = $db->query("SELECT count(*) FROM `city`");
    $cCount = (int)$cStmt->fetchColumn();
    $gStmt  = $db->query("SELECT count(*) FROM `guild`");
    $gCount = (int)$gStmt->fetchColumn();
    $hStmt  = $db->query("SELECT count(*) FROM `city_hero`");
    $hCount = (int)$hStmt->fetchColumn();

    echo json_encode([
        'id'          => 1,
        'player_num'  => max(1, $pCount),
        'guild_num'   => max(1, $gCount),
        'city_num'    => max(1, $cCount),
        'hero_num'    => max(1, $hCount),
        'open_status' => 1,
        'under_main'  => 0,
        'port'        => '8082'
    ]);
    exit;
}

// 3. Current server timestamp test
if (isset($_POST['test_time']) || isset($_GET['test_time'])) {
    echo time();
    exit;
}

// 4. Default: return server info
echo json_encode([
    'id'          => 1,
    'player_num'  => 1,
    'guild_num'   => 0,
    'city_num'    => 1,
    'hero_num'    => 1,
    'open_status' => 1,
    'under_main'  => 0,
    'port'        => '8082'
]);

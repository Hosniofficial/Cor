<?php
// web.elkaisar.com/api/guild.php - Complete Guild API Endpoints for Legacy Client Calls
require_once __DIR__ . '/../../app.elkaisar.com/db.php';
require_once __DIR__ . '/../../app.elkaisar.com/PlayerAuth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$db = getDB();
$player = requireAuthenticatedPlayer($db);

// 1. GET_UNJOINED_PLAYER_DATA
if (isset($_GET['GET_UNJOINED_PLAYER_DATA'])) {
    $idPlayer = (int)$player['id_player'];
    $reqs = [];
    $invs = [];

    if ($idPlayer > 0) {
        $stmt = $db->prepare("SELECT gr.id_guild, g.name FROM guild_req gr JOIN guild g ON gr.id_guild = g.id_guild WHERE gr.id_player = ? AND gr.type = 'req'");
        $stmt->execute([$idPlayer]);
        $reqs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $db->prepare("SELECT gr.id_guild, g.name FROM guild_req gr JOIN guild g ON gr.id_guild = g.id_guild WHERE gr.id_player = ? AND gr.type = 'inv'");
        $stmt->execute([$idPlayer]);
        $invs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    echo json_encode([
        'guild_req' => $reqs,
        'guild_inv' => $invs
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. get_guild_member
if (isset($_GET['get_guild_member'])) {
    $idGuild = (int)($_GET['id_guild'] ?? 0);
    $offset  = max(0, (int)($_GET['offset'] ?? 0));

    // Fallback if id_guild wasn't passed: lookup player's guild
    if ($idGuild <= 0 && !empty($_GET['id_player'])) {
        $pStmt = $db->prepare("SELECT id_guild FROM player WHERE id_player = ? LIMIT 1");
        $pStmt->execute([(int)$_GET['id_player']]);
        $idGuild = (int)$pStmt->fetchColumn();
    }

    if ($idGuild <= 0) {
        echo json_encode([]);
        exit;
    }

    $stmt = $db->prepare("
        SELECT p.id_player, p.name, gm.rank, gm.prize_share, p.porm, p.prestige, p.online, p.last_seen
        FROM guild_member gm
        JOIN player p ON gm.id_player = p.id_player
        WHERE gm.id_guild = ?
        ORDER BY gm.rank DESC, p.prestige DESC
        LIMIT 10 OFFSET $offset
    ");
    $stmt->execute([$idGuild]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($members as &$m) {
        $m['id_player']   = (int)$m['id_player'];
        $m['rank']        = (int)$m['rank'];
        $m['prize_share'] = (int)$m['prize_share'];
        $m['porm']        = (int)($m['porm'] ?? 0);
        $m['prestige']    = (int)($m['prestige'] ?? 0);
        $m['online']      = (int)($m['online'] ?? 1);
        $m['last_seen']   = (int)($m['last_seen'] ?? time());
    }

    echo json_encode($members, JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. get_guild_res
if (isset($_GET['get_guild_res'])) {
    $idGuild = (int)($_GET['id_guild'] ?? 0);
    if ($idGuild <= 0 && !empty($_GET['id_player'])) {
        $pStmt = $db->prepare("SELECT id_guild FROM player WHERE id_player = ? LIMIT 1");
        $pStmt->execute([(int)$_GET['id_player']]);
        $idGuild = (int)$pStmt->fetchColumn();
    }

    $stmt = $db->prepare("SELECT lvl, food, wood, stone, metal, coin, mem_num FROM guild WHERE id_guild = ?");
    $stmt->execute([$idGuild]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$res) {
        $res = ['lvl' => 1, 'food' => 0, 'wood' => 0, 'stone' => 0, 'metal' => 0, 'coin' => 0, 'mem_num' => 1];
    } else {
        foreach ($res as $k => $v) {
            $res[$k] = (int)$v;
        }
    }

    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. GET_GUILD_DETAIL
if (isset($_GET['GET_GUILD_DETAIL'])) {
    $idGuild = (int)($_GET['id_guild'] ?? 0);
    $stmt = $db->prepare("SELECT g.*, p.name AS p_name FROM guild g LEFT JOIN player p ON g.id_leader = p.id_player WHERE g.id_guild = ?");
    $stmt->execute([$idGuild]);
    $g = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($g) {
        // Calculate guild rank
        $rStmt = $db->prepare("SELECT COUNT(*) + 1 FROM guild WHERE prestige > ?");
        $rStmt->execute([(int)$g['prestige']]);
        $rankG = (int)$rStmt->fetchColumn();

        $detail = [
            'id_guild' => (int)$g['id_guild'],
            'name'     => $g['name'],
            'p_name'   => $g['p_name'] ?: ($g['leader_name'] ?? 'المدير'),
            'rank_g'   => $rankG,
            'mem_num'  => (int)$g['mem_num'],
            'lvl'      => (int)$g['lvl'],
            'prestige' => (int)$g['prestige'],
            'honor'    => (int)($g['honor'] ?? 0),
            'word'     => $g['word'] ?: 'مرحباً بكم في حلفنا',
            'slog_top' => preg_replace('/\D/', '', (string)$g['slog_top']) ?: '1',
            'slog_cnt' => preg_replace('/\D/', '', (string)$g['slog_cnt']) ?: '1',
            'slog_btm' => preg_replace('/\D/', '', (string)$g['slog_btm']) ?: '1',
        ];
        echo json_encode($detail, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['state' => 'error']);
    }
    exit;
}

// 5. GET_GUILD_AUTO_COMPLETE
if (isset($_GET['GET_GUILD_AUTO_COMPLETE'])) {
    $search = trim($_GET['search_value'] ?? '');
    $stmt = $db->prepare("SELECT id_guild, name, slog_top, slog_cnt, slog_btm FROM guild WHERE name LIKE ? LIMIT 10");
    $stmt->execute(['%' . $search . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$r) {
        $r['id_guild'] = (int)$r['id_guild'];
        $r['slog_top'] = preg_replace('/\D/', '', (string)$r['slog_top']) ?: '1';
        $r['slog_cnt'] = preg_replace('/\D/', '', (string)$r['slog_cnt']) ?: '1';
        $r['slog_btm'] = preg_replace('/\D/', '', (string)$r['slog_btm']) ?: '1';
    }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

// 6. get_rank_union_searsh
if (isset($_GET['get_rank_union_searsh'])) {
    $search = trim($_GET['search_value'] ?? '');
    $stmt = $db->prepare("SELECT g.id_guild, g.name, g.prestige, g.honor, g.mem_num, p.name AS leader FROM guild g LEFT JOIN player p ON g.id_leader = p.id_player WHERE g.name LIKE ? ORDER BY g.prestige DESC LIMIT 10");
    $stmt->execute(['%' . $search . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $rank = 1;
    foreach ($rows as &$r) {
        $r['rank']     = $rank++;
        $r['id_guild'] = (int)$r['id_guild'];
        $r['prestige'] = (int)$r['prestige'];
        $r['honor']    = (int)$r['honor'];
        $r['mem_num']  = (int)$r['mem_num'];
        $r['leader']   = $r['leader'] ?: 'المدير';
    }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['state' => 'ok']);

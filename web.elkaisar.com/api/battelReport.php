<?php
// web.elkaisar.com/api/battelReport.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../../app.elkaisar.com/db.php';
require_once __DIR__ . '/../../app.elkaisar.com/PlayerAuth.php';

$db = getDB();
$player = requireAuthenticatedPlayer($db);
$idPlayer = (int)$player['id_player'];

// 1. Battle Reports List
if (isset($_GET['get_report']) || isset($_POST['get_report'])) {
    $offset = max(0, (int)($_GET['offset'] ?? $_POST['offset'] ?? 0));
    $stmt = $db->prepare("SELECT `id_report`, `seen`, `x`, `y`, `time_stamp`, `type`, `t`, `lvl` 
                          FROM `report_player` 
                          WHERE `id_player` = ? 
                          ORDER BY `id_report` DESC 
                          LIMIT 10 OFFSET ?");
    $stmt->bindValue(1, $idPlayer, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    // Ensure numeric types
    foreach ($reports as &$r) {
        $r['id_report'] = (int)$r['id_report'];
        $r['seen']      = (int)$r['seen'];
        $r['x']         = (int)$r['x'];
        $r['y']         = (int)$r['y'];
        $r['type']      = (int)$r['type'];
        $r['t']         = (int)$r['t'];
        $r['lvl']       = (int)$r['lvl'];
    }
    
    echo json_encode($reports, JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Spy Reports List
if (isset($_GET['get_spy_report']) || isset($_POST['get_spy_report'])) {
    $offset = max(0, (int)($_GET['offset'] ?? $_POST['offset'] ?? 0));
    $stmt = $db->prepare("SELECT `id_report`, `id_player`, `seen`, `victim`, `x`, `y`, `time_stamp`, `type`, `t`, `l`, `spy_for` 
                          FROM `spy_report` 
                          WHERE `id_player` = ? 
                          ORDER BY `id_report` DESC 
                          LIMIT 10 OFFSET ?");
    $stmt->bindValue(1, $idPlayer, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    foreach ($reports as &$r) {
        $r['id_report']  = (int)$r['id_report'];
        $r['id_player']  = (int)$r['id_player'];
        $r['seen']       = (int)$r['seen'];
        $r['victim']     = (int)$r['victim'];
        $r['x']          = (int)$r['x'];
        $r['y']          = (int)$r['y'];
        $r['type']       = (int)$r['type'];
        $r['t']          = (int)$r['t'];
        $r['l']          = (int)$r['l'];
    }
    
    echo json_encode($reports, JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Battle Report Detail
if (isset($_GET['report_detail']) || isset($_POST['report_detail'])) {
    $idReport = (int)($_GET['id_report'] ?? $_POST['id_report'] ?? 0);
    
    // Mark as seen
    $db->prepare("UPDATE `report_player` SET `seen` = 1 WHERE `id_report` = ? AND `id_player` = ?")->execute([$idReport, $idPlayer]);
    
    $stmt = $db->prepare("SELECT `detail` FROM `report_player` WHERE `id_report` = ? AND `id_player` = ?");
    $stmt->execute([$idReport, $idPlayer]);
    $rawDetail = $stmt->fetchColumn();
    
    if ($rawDetail && ($decoded = json_decode($rawDetail, true))) {
        $decoded['encId'] = (string)$idReport;
        echo json_encode($decoded, JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([]);
    exit;
}

// 4. Spy Report Detail
if (isset($_GET['spy_report_detail']) || isset($_POST['spy_report_detail'])) {
    $idReport = (int)($_GET['id_report'] ?? $_POST['id_report'] ?? 0);
    
    $db->prepare("UPDATE `spy_report` SET `seen` = 1 WHERE `id_report` = ? AND `id_player` = ?")->execute([$idReport, $idPlayer]);
    
    $stmt = $db->prepare("SELECT `content` FROM `spy_report` WHERE `id_report` = ? AND `id_player` = ?");
    $stmt->execute([$idReport, $idPlayer]);
    $content = $stmt->fetchColumn();
    if ($content === false) {
        echo json_encode([]);
        exit;
    }

    echo json_encode([
        'side'    => 'attacker',
        'content' => $content
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Default empty array
echo json_encode([]);
exit;

<?php
// web.elkaisar.com/api/message.php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../../app.elkaisar.com/db.php';
require_once __DIR__ . '/../../app.elkaisar.com/PlayerAuth.php';

$db = getDB();
$player = requireAuthenticatedPlayer($db);
$idPlayer = (int)$player['id_player'];

// 1. Unread Counts
if (isset($_GET['MSG_NUMBERS']) || isset($_POST['MSG_NUMBERS'])) {
    $inCount   = (int)$db->query("SELECT COUNT(*) FROM `msg_income` WHERE `id_player` = $idPlayer AND `seen` = 0")->fetchColumn();
    $diffCount = (int)$db->query("SELECT COUNT(*) FROM `msg_diff` WHERE `id_player` = $idPlayer AND `seen` = 0")->fetchColumn();
    $repCount  = (int)$db->query("SELECT COUNT(*) FROM `report_player` WHERE `id_player` = $idPlayer AND `seen` = 0")->fetchColumn();
    $spyCount  = (int)$db->query("SELECT COUNT(*) FROM `spy_report` WHERE `id_player` = $idPlayer AND `seen` = 0")->fetchColumn();
    
    echo json_encode([
        'msg_out'       => 0,
        'msg_income'    => $inCount,
        'msg_diff'      => $diffCount,
        'report_player' => $repCount,
        'spy_report'    => $spyCount
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Income Messages
if (isset($_GET['get_msg_income']) || isset($_POST['get_msg_income'])) {
    $offset = max(0, (int)($_GET['offset'] ?? $_POST['offset'] ?? 0));
    $stmt = $db->prepare("SELECT `id_msg`, `from_`, `from_name` as `name`, `subject` as `head`, `seen`, `time_stamp` 
                          FROM `msg_income` 
                          WHERE `id_player` = ? 
                          ORDER BY `id_msg` DESC 
                          LIMIT 10 OFFSET ?");
    $stmt->bindValue(1, $idPlayer, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    echo json_encode($msgs, JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Income Message Detail
if (isset($_GET['get_income_msg_in_detail']) || isset($_POST['get_income_msg_in_detail'])) {
    $idMsg = (int)($_GET['id_message'] ?? $_POST['id_message'] ?? 0);
    $db->prepare("UPDATE `msg_income` SET `seen` = 1 WHERE `id_msg` = ? AND `id_player` = ?")->execute([$idMsg, $idPlayer]);
    
    $stmt = $db->prepare("SELECT `id_msg`, `from_`, `from_name` as `name`, `subject` as `head`, `body`, `seen`, `time_stamp`, `from_` as `id_from` 
                          FROM `msg_income` 
                          WHERE `id_msg` = ? AND `id_player` = ?");
    $stmt->execute([$idMsg, $idPlayer]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($msg ? [$msg] : [], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Misc Messages (msg_diff)
if (isset($_GET['get_msg_diff']) || isset($_POST['get_msg_diff'])) {
    $offset = max(0, (int)($_GET['offset'] ?? $_POST['offset'] ?? 0));
    $stmt = $db->prepare("SELECT `id_msg`, `subject` as `head`, `seen`, `time_stamp` 
                          FROM `msg_diff` 
                          WHERE `id_player` = ? 
                          ORDER BY `id_msg` DESC 
                          LIMIT 10 OFFSET ?");
    $stmt->bindValue(1, $idPlayer, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    echo json_encode($msgs, JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['get_diff_msg_in_detail']) || isset($_POST['get_diff_msg_in_detail'])) {
    $idMsg = (int)($_GET['id_message'] ?? $_POST['id_message'] ?? 0);
    $db->prepare("UPDATE `msg_diff` SET `seen` = 1 WHERE `id_msg` = ? AND `id_player` = ?")->execute([$idMsg, $idPlayer]);
    
    $stmt = $db->prepare("SELECT `id_msg`, `subject` as `head`, `body`, `seen`, `time_stamp` 
                          FROM `msg_diff` 
                          WHERE `id_msg` = ? AND `id_player` = ?");
    $stmt->execute([$idMsg, $idPlayer]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($msg ? [$msg] : [], JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. Outbox Messages (msg_out) - supports both get_msg_outcome and get_msg_out
if (isset($_GET['get_msg_outcome']) || isset($_POST['get_msg_outcome']) || isset($_GET['get_msg_out']) || isset($_POST['get_msg_out'])) {
    $offset = max(0, (int)($_GET['offset'] ?? $_POST['offset'] ?? 0));
    $stmt = $db->prepare("SELECT `id_msg`, `to_name` as `name`, `subject` as `head`, 1 as `seen`, `time_stamp` 
                          FROM `msg_out` 
                          WHERE `id_player` = ? 
                          ORDER BY `id_msg` DESC 
                          LIMIT 10 OFFSET ?");
    $stmt->bindValue(1, $idPlayer, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    echo json_encode($msgs, JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['get_out_msg_in_detail']) || isset($_POST['get_out_msg_in_detail'])) {
    $idMsg = (int)($_GET['id_message'] ?? $_POST['id_message'] ?? 0);
    $stmt = $db->prepare("SELECT `id_msg`, `to_name` as `name`, `subject` as `head`, `body`, `time_stamp` 
                          FROM `msg_out` 
                          WHERE `id_msg` = ? AND `id_player` = ?");
    $stmt->execute([$idMsg, $idPlayer]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($msg ? [$msg] : [], JSON_UNESCAPED_UNICODE);
    exit;
}

// 6. Send Mail to Player
if (isset($_POST['send_mail_to']) || isset($_GET['send_mail_to'])) {
    $idTo    = (int)($_POST['id_to'] ?? 0);
    $body    = trim($_POST['body'] ?? '');
    $subject = trim($_POST['subject'] ?? 'بدون عنوان');
    
    $senderName = $db->query("SELECT name FROM player WHERE id_player = $idPlayer")->fetchColumn() ?: 'لاعب';
    $receiverName = $db->query("SELECT name FROM player WHERE id_player = $idTo")->fetchColumn() ?: 'لاعب';
    
    // Add to recipient inbox
    $stmt = $db->prepare("INSERT INTO `msg_income` (`id_player`, `from_`, `from_name`, `subject`, `body`, `seen`, `time_stamp`) VALUES (?, ?, ?, ?, ?, 0, NOW())");
    $stmt->execute([$idTo, $idPlayer, $senderName, $subject, $body]);
    
    // Add to sender outbox
    $stmtOut = $db->prepare("INSERT INTO `msg_out` (`id_player`, `to_player`, `to_name`, `subject`, `body`, `time_stamp`) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmtOut->execute([$idPlayer, $idTo, $receiverName, $subject, $body]);
    
    echo "done";
    exit;
}

// 7. Send Mail to Guild Members
if (isset($_POST['SEND_GUILD_MAIL']) || isset($_GET['SEND_GUILD_MAIL'])) {
    $subject = trim($_POST['subject'] ?? 'رسالة التحالف');
    $body    = trim($_POST['body'] ?? '');
    
    $stmtG = $db->prepare("SELECT id_guild, name FROM player WHERE id_player = ?");
    $stmtG->execute([$idPlayer]);
    $pData = $stmtG->fetch(PDO::FETCH_ASSOC);
    $idGuild = (int)($pData['id_guild'] ?? 0);
    $senderName = $pData['name'] ?? 'القائد';
    
    if ($idGuild > 0 && !empty($body)) {
        $mStmt = $db->prepare("SELECT id_player FROM guild_member WHERE id_guild = ?");
        $mStmt->execute([$idGuild]);
        $members = $mStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if (empty($members)) {
            $mStmt2 = $db->prepare("SELECT id_player FROM player WHERE id_guild = ?");
            $mStmt2->execute([$idGuild]);
            $members = $mStmt2->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
        
        $insInc = $db->prepare("INSERT INTO `msg_income` (`id_player`, `from_`, `from_name`, `subject`, `body`, `seen`, `time_stamp`) VALUES (?, 2, ?, ?, ?, 0, NOW())");
        foreach ($members as $mId) {
            $insInc->execute([$mId, $senderName, "[التحالف] " . $subject, $body]);
        }
        
        $db->prepare("INSERT INTO `msg_out` (`id_player`, `to_player`, `to_name`, `subject`, `body`, `time_stamp`) VALUES (?, 0, 'أعضاء التحالف', ?, ?, NOW())")
           ->execute([$idPlayer, "[التحالف] " . $subject, $body]);
    }
    
    echo "done";
    exit;
}

// 8. Delete Selected Messages
if (isset($_POST['delete_msg']) || isset($_GET['delete_msg'])) {
    $msgsJson = $_POST['msgs'] ?? $_GET['msgs'] ?? '{}';
    $reqData = json_decode($msgsJson, true);
    $deletedCount = 0;
    
    if (isset($reqData['table']) && isset($reqData['id_msgs']) && is_array($reqData['id_msgs'])) {
        $table = $reqData['table'];
        $idList = array_map('intval', $reqData['id_msgs']);
        if (!empty($idList)) {
            $inClause = implode(',', $idList);
            if ($table === 'report_player') {
                $deletedCount = (int)$db->exec("DELETE FROM `report_player` WHERE `id_player` = $idPlayer AND `id_report` IN ($inClause)");
            } elseif ($table === 'spy_report') {
                $deletedCount = (int)$db->exec("DELETE FROM `spy_report` WHERE `id_player` = $idPlayer AND `id_report` IN ($inClause)");
            } elseif ($table === 'msg_income') {
                $deletedCount = (int)$db->exec("DELETE FROM `msg_income` WHERE `id_player` = $idPlayer AND `id_msg` IN ($inClause)");
            } elseif ($table === 'msg_diff') {
                $deletedCount = (int)$db->exec("DELETE FROM `msg_diff` WHERE `id_player` = $idPlayer AND `id_msg` IN ($inClause)");
            } elseif ($table === 'msg_out') {
                $deletedCount = (int)$db->exec("DELETE FROM `msg_out` WHERE `id_player` = $idPlayer AND `id_msg` IN ($inClause)");
            }
        }
    } elseif (is_array($reqData)) {
        foreach ($reqData as $item) {
            $table = $item['table'] ?? '';
            $idMsg = (int)($item['id_msg'] ?? 0);
            if ($table === 'report_player') {
                $deletedCount += (int)$db->exec("DELETE FROM `report_player` WHERE `id_report` = $idMsg AND `id_player` = $idPlayer");
            } elseif ($table === 'spy_report') {
                $deletedCount += (int)$db->exec("DELETE FROM `spy_report` WHERE `id_report` = $idMsg AND `id_player` = $idPlayer");
            } elseif ($table === 'msg_income') {
                $deletedCount += (int)$db->exec("DELETE FROM `msg_income` WHERE `id_msg` = $idMsg AND `id_player` = $idPlayer");
            } elseif ($table === 'msg_diff') {
                $deletedCount += (int)$db->exec("DELETE FROM `msg_diff` WHERE `id_msg` = $idMsg AND `id_player` = $idPlayer");
            } elseif ($table === 'msg_out') {
                $deletedCount += (int)$db->exec("DELETE FROM `msg_out` WHERE `id_msg` = $idMsg AND `id_player` = $idPlayer");
            }
        }
    }
    
    echo max(1, $deletedCount);
    exit;
}

// 9. Delete All Messages / Unread in a tab
if (isset($_POST['DELETE_ALL_UNREAD']) || isset($_GET['DELETE_ALL_UNREAD'])) {
    $msgsJson = $_POST['msgs'] ?? $_GET['msgs'] ?? '{}';
    $reqData = json_decode($msgsJson, true);
    $table = $reqData['table'] ?? ($_POST['table'] ?? '');
    
    if ($table === 'report_player') {
        $db->prepare("DELETE FROM `report_player` WHERE `id_player` = ?")->execute([$idPlayer]);
    } elseif ($table === 'spy_report') {
        $db->prepare("DELETE FROM `spy_report` WHERE `id_player` = ?")->execute([$idPlayer]);
    } elseif ($table === 'msg_income') {
        $db->prepare("DELETE FROM `msg_income` WHERE `id_player` = ?")->execute([$idPlayer]);
    } elseif ($table === 'msg_diff') {
        $db->prepare("DELETE FROM `msg_diff` WHERE `id_player` = ?")->execute([$idPlayer]);
    } elseif ($table === 'msg_out') {
        $db->prepare("DELETE FROM `msg_out` WHERE `id_player` = ?")->execute([$idPlayer]);
    }
    echo "1";
    exit;
}

echo "1";
exit;

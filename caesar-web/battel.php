<?php
// caesar-web/battel.php - Authentic Cocos2d-html5 Battle Replay Engine
require_once __DIR__ . '/../caesar-api/db.php';

$db = getDB();

$reportIdRaw = $_GET['report'] ?? $_GET['id_report'] ?? '';
$server = (int)($_GET['server'] ?? 1);

// Extract ID from URL if accessed via /BattleReplay/{id}.html
if (empty($reportIdRaw) && preg_match('#/BattleReplay/(\d+)#i', $_SERVER['REQUEST_URI'] ?? '', $m)) {
    $reportIdRaw = $m[1];
}

$idReport = 0;
if (is_numeric($reportIdRaw) && (int)$reportIdRaw > 0) {
    $idReport = (int)$reportIdRaw;
}

if ($idReport <= 0) {
    $stmt = $db->query("SELECT `id_report` FROM `report_player` ORDER BY `id_report` DESC LIMIT 1");
    $idReport = (int)$stmt->fetchColumn();
}

if ($idReport <= 0) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><title>التقرير غير موجود</title><body>لا يوجد تقرير معركة للعرض.</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8" />
  <title>استعراض المعارك - قيصر الشرق</title>
  <link rel="icon" type="image/png" href="/Res/favicon.png" sizes="128x128" />
  <meta name="viewport"
    content="width=device-width, initial-scale=0.5, maximum-scale=0.5, minimum-scale=0.5, width=device-width,height=device-height, user-scalable=yes" />
  <link rel="stylesheet" href="/css/battle.all.bundle.cff0be5e-ab5c-4b3e-bc9c-a69fc5b52263.css" />
  <style>
    html, body {
      width: 100%;
      height: 100%;
      margin: 0;
      padding: 0;
      overflow: hidden;
      background-color: #000;
      user-select: none;
      -webkit-user-select: none;
    }

    #GameWindow {
      display: block;
      margin: 0 auto;
    }

    /* Hide scrollbar for Chrome, Safari, and Opera */
    #battel-detail::-webkit-scrollbar {
      display: none;
    }

    #battel-detail {
      /* Enforce scrolling capability */
      overflow-y: auto;
      /* Hide scrollbar for Modern Firefox */
      scrollbar-width: none;
      /* Hide scrollbar for IE 10+ and Legacy Edge */
      -ms-overflow-style: none;
    }

    #battel-detail ul .name {
      margin: 5px 10px;
      font-size: 13px;
      font-weight: bold;
      white-space: nowrap;
      overflow: hidden;
    }

    #battle-report-container {
      position: fixed;
      z-index: 500;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    #battle-report-container > * {
      pointer-events: auto;
    }
  </style>
</head>

<body style="background-color: #000000; margin: 0px; padding: 0px; overflow: hidden">
  <canvas id="GameWindow"></canvas>
  <div id="battle-report-container" style="position: fixed; z-index: 500; top: 0; left: 0;"></div>

  <script>
    const BattelID = "<?php echo (int)$idReport; ?>";
    if (window.history && window.history.replaceState) {
      try {
        window.history.replaceState(null, "", "/BattleReplay/" + BattelID + ".html");
      } catch (e) {}
    }
  </script>
  <script src="/Res/loading.js"></script>
  <script src="/Src/jquery-3.2.1.min.js"></script>
  <script src="/Src/jquery.nicescroll.min.js"></script>
  <script src="/Src/cocos.polyfill.js"></script>
  <script src="/BRAsset/battle-replay-core.bundle.js"></script>
  <script src="/cocos2d-html5/CCBoot.js"></script>
  <script cocos>
    let gameConfig = {
      project_type: "javascript",
      debugMode: 0,
      showFPS: false,
      frameRate: 30,
      noCache: false,
      id: "GameWindow",
      renderMode: 0,
      engineDir: "/cocos2d-html5",
      modules: ["cocos2d", "extensions"],
      jsList: [
        "/BRAsset/battle-replay.bundle.js",
      ],
    };

    cc.game.run(gameConfig, function () {
      cc.game.onStart();
    });
  </script>
</body>
</html>

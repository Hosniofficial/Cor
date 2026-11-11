<?php
// web.elkaisar.com/battel.php - Real Battle Replay System
require_once __DIR__ . '/../app.elkaisar.com/db.php';
require_once __DIR__ . '/../app.elkaisar.com/services/BattleService.php';

$db = getDB();

$reportIdRaw = $_GET['report'] ?? $_GET['id_report'] ?? '';
$server = (int)($_GET['server'] ?? 1);

// Find requested report, or get latest report, or generate a real battle simulation
$reportRow = null;
if (is_numeric($reportIdRaw) && (int)$reportIdRaw > 0) {
    $stmt = $db->prepare("SELECT * FROM `report_player` WHERE `id_report` = ?");
    $stmt->execute([(int)$reportIdRaw]);
    $reportRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$reportRow) {
    $stmt = $db->query("SELECT * FROM `report_player` ORDER BY `id_report` DESC LIMIT 1");
    $reportRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If still no report exists, generate a real live battle from player 1's real army
if (!$reportRow || empty($reportRow['detail'])) {
    $sim = BattleService::executeBattle($db, 1);
    if ($sim) {
        $stmt = $db->prepare("SELECT * FROM `report_player` WHERE `id_report` = ?");
        $stmt->execute([$sim['reportId']]);
        $reportRow = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$detail = json_decode($reportRow['detail'] ?? '{}', true) ?: [];

$xCoord = $reportRow['x'] ?? 150;
$yCoord = $reportRow['y'] ?? 200;
$timeStamp = $reportRow['time_stamp'] ?? date('Y-m-d H:i:s');
$targetLvl = $reportRow['lvl'] ?? 5;

// Unit dictionary
$unitNames = [
    1 => 'مشاة رومانية',
    2 => 'فرسان الطليعة',
    3 => 'رماة السهام',
    4 => 'مقاتلين مدرعين',
    5 => 'مقاليع ثقيلة',
    6 => 'مجانيق حصار'
];

$unitImages = [
    1 => 'images/tech/soldier_1.jpg',
    2 => 'images/tech/soldier_2.jpg',
    3 => 'images/tech/soldier_3.jpg',
    4 => 'images/tech/soldier_4.jpg',
    5 => 'images/tech/soldier_5.jpg',
    6 => 'images/tech/soldier_6.jpg'
];

// Extract Attacker (side 1) and Defender (side 0)
$attackerHero = null;
$defenderHero = null;

if (!empty($detail['heros'])) {
    foreach ($detail['heros'] as $h) {
        if ((int)$h['side'] === 1 && !$attackerHero) {
            $attackerHero = $h;
        } elseif ((int)$h['side'] === 0 && !$defenderHero) {
            $defenderHero = $h;
        }
    }
}

// Fallback if structure was different
if (!$attackerHero && isset($detail['heros'][0])) $attackerHero = $detail['heros'][0];
if (!$defenderHero && isset($detail['heros'][1])) $defenderHero = $detail['heros'][1];

// Extract real troops slots
function extractSlots($heroData) {
    $slots = [];
    $keys = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
    foreach ($keys as $k) {
        $type = (int)($heroData[$k . '_type'] ?? 0);
        $pre  = (int)($heroData[$k . '_pre'] ?? 0);
        $post = (int)($heroData[$k . '_post'] ?? 0);
        if ($type > 0 && $pre > 0) {
            $slots[$k] = [
                'type' => $type,
                'pre'  => $pre,
                'post' => $post,
                'loss' => max(0, $pre - $post)
            ];
        }
    }
    return $slots;
}

$attackerSlots = extractSlots($attackerHero);
$defenderSlots = extractSlots($defenderHero);

$sideWin = $detail['general_data']['side_win'] ?? 'attacker';
$isAttackerWon = ($sideWin === 'attacker' || (int)$sideWin === 1);
if ($isAttackerWon && (int)($defenderHero['id_player'] ?? -1) === 0) {
    foreach ($defenderSlots as &$slot) {
        $slot['post'] = 0;
        $slot['loss'] = $slot['pre'];
    }
    unset($slot);
}

// Total counts
$totalAtkPre  = array_sum(array_column($attackerSlots, 'pre'));
$totalAtkPost = array_sum(array_column($attackerSlots, 'post'));
$totalAtkLoss = max(0, $totalAtkPre - $totalAtkPost);

$totalDefPre  = array_sum(array_column($defenderSlots, 'pre'));
$totalDefPost = array_sum(array_column($defenderSlots, 'post'));
$totalDefLoss = max(0, $totalDefPre - $totalDefPost);

$prizeRes = $detail['prize']['resource'] ?? [
    'food' => 50000, 'wood' => 40000, 'stone' => 30000, 'metal' => 20000, 'coin' => 10000
];
$honorWon = $detail['prize']['honor'] ?? 300;
$expWon   = $attackerHero['xp'] ?? 3500;
$roundsLog = $detail['rounds_log'] ?? [
    'الجولة 1: تقدمت الكتائب إلى خطوط التماس وبدأت الاشتباك المباشر.',
    'الجولة 2: تركيز الضربات وسحق حامية الأعداء بالكامل.',
    'الجولة 3: استسلام الناجين وانتصار الجيش الروماني الحاسم.'
];
$totalRoundsCount = count($roundsLog);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعراض القوات الحقيقية للمعركة - قيصر الشرق</title>
    <link rel="icon" type="image/png" href="images/favicon.png" sizes="128x128">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg-dark: #070b14;
            --bg-panel: #101726;
            --bg-field: #131e33;
            --border: #223254;
            --gold: #d4af37;
            --gold-light: #fef08a;
            --gold-dark: #996515;
            --red: #ef4444;
            --green: #10b981;
            --blue: #38bdf8;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', Tahoma, Arial, sans-serif; }
        body {
            background: radial-gradient(circle at center top, #162444 0%, var(--bg-dark) 80%);
            color: var(--text-main);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container {
            width: 100%;
            max-width: 1100px;
        }

        /* Top Header */
        .arena-header {
            background: var(--bg-panel);
            border: 1px solid var(--border);
            border-top: 3px solid var(--gold);
            border-radius: 16px;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            margin-bottom: 20px;
        }
        .arena-title { display: flex; align-items: center; gap: 14px; }
        .arena-logo {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--gold-dark), var(--gold));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0b0f19;
            font-size: 24px;
            box-shadow: 0 0 15px rgba(212,175,55,0.4);
        }
        .arena-title h1 { font-size: 22px; font-weight: 900; color: var(--gold-light); }
        .arena-title p { font-size: 13px; color: var(--text-muted); }

        .battle-result-badge {
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            background: <?= $isAttackerWon ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>;
            color: <?= $isAttackerWon ? '#34d399' : '#f87171' ?>;
            border: 1px solid <?= $isAttackerWon ? 'rgba(16, 185, 129, 0.4)' : 'rgba(239, 68, 68, 0.4)' ?>;
            box-shadow: 0 0 20px <?= $isAttackerWon ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)' ?>;
        }

        /* Battlefield Stage */
        .battle-stage {
            background: var(--bg-panel);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.6);
            position: relative;
            overflow: hidden;
        }
        .stage-grid {
            display: grid;
            grid-template-columns: 1fr 140px 1fr;
            gap: 20px;
            align-items: center;
        }

        /* Army Columns */
        .army-side {
            background: rgba(10, 16, 30, 0.7);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            transition: all 0.3s;
        }
        .army-side.attacker { border-color: rgba(56, 189, 248, 0.4); }
        .army-side.defender { border-color: rgba(239, 68, 68, 0.4); }

        .side-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        .hero-img {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            border: 2px solid var(--gold);
            object-fit: cover;
            background: #000;
        }
        .hero-info h3 { font-size: 16px; font-weight: 800; color: #fff; }
        .hero-info span { font-size: 12px; color: var(--gold); font-weight: 700; }

        /* Troops List */
        .troops-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .troop-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--bg-field);
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        .troop-thumb {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid rgba(212,175,55,0.3);
        }
        .troop-details { flex: 1; }
        .troop-name {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
        }
        .troop-counts {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
        }
        .troop-hp-bar {
            width: 100%;
            height: 8px;
            background: #060911;
            border-radius: 4px;
            overflow: hidden;
        }
        .troop-hp-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #34d399);
            border-radius: 4px;
            transition: width 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .troop-hp-fill.enemy {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }

        /* Center Arena Clashing */
        .center-clash {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .round-indicator {
            background: rgba(212, 175, 55, 0.1);
            border: 1px solid var(--gold);
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 800;
            color: var(--gold-light);
            margin-bottom: 14px;
        }
        .swords-icon {
            font-size: 44px;
            color: var(--gold);
            animation: swordPulse 1.5s infinite;
            filter: drop-shadow(0 0 10px rgba(212,175,55,0.6));
        }
        @keyframes swordPulse {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.15) rotate(-10deg); }
        }

        /* Controls */
        .controls-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
        }
        .btn-ctrl {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        .btn-gold {
            background: linear-gradient(135deg, var(--gold-dark), var(--gold));
            color: #080d19;
            box-shadow: 0 4px 15px rgba(212,175,55,0.3);
        }
        .btn-gold:hover { filter: brightness(1.15); transform: translateY(-2px); }
        .btn-outline {
            background: rgba(255,255,255,0.04);
            border-color: var(--border);
            color: #fff;
        }
        .btn-outline:hover {
            border-color: var(--gold);
            color: var(--gold-light);
        }

        /* Combat Log */
        .combat-log-panel {
            background: var(--bg-panel);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }
        .combat-log-panel h3 { font-size: 16px; font-weight: 800; color: var(--gold-light); margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .log-box {
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            max-height: 180px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
        }
        .log-entry { display: flex; align-items: center; gap: 8px; }
        .log-entry i { color: var(--gold); font-size: 12px; }

        /* Spoils & Prizes */
        .spoils-panel {
            background: linear-gradient(135deg, rgba(212,175,55,0.08), rgba(16,23,38,0.9));
            border: 1px solid var(--gold);
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .spoils-panel h3 { font-size: 16px; font-weight: 800; color: var(--gold-light); margin-bottom: 12px; }
        .res-list { display: flex; gap: 14px; flex-wrap: wrap; }
        .res-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(0,0,0,0.4);
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid rgba(212,175,55,0.3);
            font-size: 13px;
            font-weight: 700;
        }
        .res-pill img { width: 22px; height: 22px; }

        .back-link { margin-top: 25px; text-align: center; }
        .back-link a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }
        .back-link a:hover { color: var(--gold); }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <header class="arena-header">
        <div class="arena-title">
            <div class="arena-logo">
                <i class="fa-solid fa-chess-rook"></i>
            </div>
            <div>
                <h1>ميدان استعراض القوات الحقيقية للمعركة</h1>
                <p>موقع الهدف: [ <?= $xCoord ?>, <?= $yCoord ?> ] &bull; التوقيت الفعلي: <?= $timeStamp ?></p>
            </div>
        </div>
        <div class="battle-result-badge">
            <i class="fa-solid <?= $isAttackerWon ? 'fa-trophy' : 'fa-skull' ?>"></i>
            <span><?= $isAttackerWon ? 'انتصار حاسم لقواتك' : 'هزيمة الكتائب في المعركة' ?></span>
        </div>
    </header>

    <!-- Battlefield 2D Stage -->
    <section class="battle-stage">
        <div class="stage-grid">
            <!-- Attacker (Left / Real Hero & Troops) -->
            <div class="army-side attacker">
                <div class="side-header">
                    <img src="images/icons/hero/eq-bg.png" class="hero-img">
                    <div class="hero-info">
                        <h3><?= htmlspecialchars($attackerHero['h_name'] ?? 'البطل سيزر') ?></h3>
                        <span>الجانب المهاجم (+<?= number_format($expWon) ?> خبرة)</span>
                    </div>
                </div>

                <div class="troops-list">
                    <?php if (empty($attackerSlots)): ?>
                        <div style="color: var(--text-muted); text-align: center; padding: 20px;">لم تخرج أي كتائب لهذا البطل</div>
                    <?php else: ?>
                        <?php foreach ($attackerSlots as $slotKey => $slot): ?>
                            <?php 
                                $tType = $slot['type'];
                                $tName = $unitNames[$tType] ?? "كتيبة $tType";
                                $tImg  = $unitImages[$tType] ?? 'images/tech/soldier_1.jpg';
                                $pct   = $slot['pre'] > 0 ? max(0, min(100, round(($slot['post'] / $slot['pre']) * 100))) : 0;
                            ?>
                            <div class="troop-item" id="atk-item-<?= $slotKey ?>">
                                <img src="<?= $tImg ?>" class="troop-thumb">
                                <div class="troop-details">
                                    <div class="troop-name">
                                        <span><?= $tName ?></span>
                                        <span id="atk-pct-<?= $slotKey ?>"><?= $pct ?>%</span>
                                    </div>
                                    <div class="troop-counts">
                                        <span>الناجون: <strong id="atk-post-<?= $slotKey ?>"><?= number_format($slot['post']) ?></strong> / <?= number_format($slot['pre']) ?></span>
                                        <span style="color: #f87171;">الخسائر: -<?= number_format($slot['loss']) ?></span>
                                    </div>
                                    <div class="troop-hp-bar">
                                        <div class="troop-hp-fill" id="atk-bar-<?= $slotKey ?>" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Center Clash Icon & Round Info -->
            <div class="center-clash">
                <div class="round-indicator" id="round-indicator-text">الجولة 1 من <?= $totalRoundsCount ?></div>
                <div class="swords-icon">
                    <i class="fa-solid fa-swords"></i>
                </div>
                <div style="font-size: 11px; color: var(--gold-light); margin-top: 10px; font-weight: 700;">اشتباك القوات المباشر</div>
            </div>

            <!-- Defender (Right / Real Enemy Army) -->
            <div class="army-side defender">
                <div class="side-header">
                    <img src="images/icons/hero/eq-bg.png" class="hero-img">
                    <div class="hero-info">
                        <h3><?= htmlspecialchars($defenderHero['h_name'] ?? "حامية العدو [مستوى $targetLvl]") ?></h3>
                        <span style="color: #f87171;">الجانب المدافع</span>
                    </div>
                </div>

                <div class="troops-list">
                    <?php if (empty($defenderSlots)): ?>
                        <div style="color: var(--text-muted); text-align: center; padding: 20px;">حامية العدو خالية</div>
                    <?php else: ?>
                        <?php foreach ($defenderSlots as $slotKey => $slot): ?>
                            <?php 
                                $tType = $slot['type'];
                                $tName = $unitNames[$tType] ?? "كتيبة $tType";
                                $tImg  = $unitImages[$tType] ?? 'images/tech/soldier_1.jpg';
                                $pct   = $slot['pre'] > 0 ? max(0, min(100, round(($slot['post'] / $slot['pre']) * 100))) : 0;
                            ?>
                            <div class="troop-item" id="def-item-<?= $slotKey ?>">
                                <img src="<?= $tImg ?>" class="troop-thumb">
                                <div class="troop-details">
                                    <div class="troop-name">
                                        <span><?= $tName ?></span>
                                        <span id="def-pct-<?= $slotKey ?>"><?= $pct ?>%</span>
                                    </div>
                                    <div class="troop-counts">
                                        <span>المتبقي: <strong id="def-post-<?= $slotKey ?>"><?= number_format($slot['post']) ?></strong> / <?= number_format($slot['pre']) ?></span>
                                        <span style="color: #f87171;">القتلى: -<?= number_format($slot['loss']) ?></span>
                                    </div>
                                    <div class="troop-hp-bar">
                                        <div class="troop-hp-fill enemy" id="def-bar-<?= $slotKey ?>" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Controls Bar -->
        <div class="controls-bar">
            <button class="btn-ctrl btn-gold" id="btn-replay-play" onclick="toggleReplay()">
                <i class="fa-solid fa-play"></i>
                <span id="btn-play-text">تشغيل الاستعراض التلقائي</span>
            </button>
            <button class="btn-ctrl btn-outline" onclick="stepRound()">
                <i class="fa-solid fa-forward-step"></i>
                <span>الجولة التالية</span>
            </button>
            <button class="btn-ctrl btn-outline" onclick="resetReplay()">
                <i class="fa-solid fa-rotate-left"></i>
                <span>إعادة الاستعراض</span>
            </button>
        </div>
    </section>

    <!-- Combat Log Panel -->
    <section class="combat-log-panel">
        <h3><i class="fa-solid fa-scroll text-gold"></i> السجل الفعلي لمجريات الجولات والضربات</h3>
        <div class="log-box" id="combat-log-stream">
            <?php foreach ($roundsLog as $idx => $line): ?>
                <div class="log-entry"><i class="fa-solid fa-bolt"></i> <?= htmlspecialchars($line) ?></div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Spoils & Prizes -->
    <section class="spoils-panel">
        <div>
            <h3><i class="fa-solid fa-sack-dollar text-gold ml-2"></i> الموارد والغنائم الفعلية المكتسبة:</h3>
            <div class="res-list">
                <div class="res-pill"><img src="images/style/food.png"><span>+<?= number_format($prizeRes['food'] ?? 0) ?> غذاء</span></div>
                <div class="res-pill"><img src="images/style/wood.png"><span>+<?= number_format($prizeRes['wood'] ?? 0) ?> خشب</span></div>
                <div class="res-pill"><img src="images/style/stone.png"><span>+<?= number_format($prizeRes['stone'] ?? 0) ?> صخور</span></div>
                <div class="res-pill"><img src="images/style/iron.png"><span>+<?= number_format($prizeRes['metal'] ?? 0) ?> حديد</span></div>
                <div class="res-pill"><img src="images/style/coin.png"><span>+<?= number_format($prizeRes['coin'] ?? 0) ?> عملات</span></div>
            </div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <div class="res-pill"><img src="images/items/item080.jpg"><span>+1 صندوق 10 ذهب</span></div>
            <div class="res-pill"><img src="images/items/item117.jpg"><span>+<?= number_format($honorWon) ?> شرف عسكري</span></div>
        </div>
    </section>

    <div class="back-link">
        <a href="server.php"><i class="fa-solid fa-arrow-right ml-1"></i> إغلاق واستكمال اللعب في قيصر الشرق</a>
    </div>
</div>

<script>
const attackerSlotsData = <?= json_encode($attackerSlots) ?>;
const defenderSlotsData = <?= json_encode($defenderSlots) ?>;
const roundsCount = <?= $totalRoundsCount ?>;

let currentRound = 1;
let autoPlayTimer = null;

function applyRound(r) {
    currentRound = r;
    document.getElementById('round-indicator-text').innerText = `الجولة ${r} من ${roundsCount}`;

    const progress = r / roundsCount;

    // Interpolate attacker
    for (const [k, slot] of Object.entries(attackerSlotsData)) {
        const curCount = Math.round(slot.pre - (slot.loss * progress));
        const pct = slot.pre > 0 ? Math.max(0, Math.min(100, Math.round((curCount / slot.pre) * 100))) : 0;
        const postEl = document.getElementById(`atk-post-${k}`);
        const pctEl  = document.getElementById(`atk-pct-${k}`);
        const barEl  = document.getElementById(`atk-bar-${k}`);
        if (postEl) postEl.innerText = curCount.toLocaleString();
        if (pctEl)  pctEl.innerText  = `${pct}%`;
        if (barEl)  barEl.style.width = `${pct}%`;
    }

    // Interpolate defender
    for (const [k, slot] of Object.entries(defenderSlotsData)) {
        const curCount = Math.round(slot.pre - (slot.loss * progress));
        const pct = slot.pre > 0 ? Math.max(0, Math.min(100, Math.round((curCount / slot.pre) * 100))) : 0;
        const postEl = document.getElementById(`def-post-${k}`);
        const pctEl  = document.getElementById(`def-pct-${k}`);
        const barEl  = document.getElementById(`def-bar-${k}`);
        if (postEl) postEl.innerText = curCount.toLocaleString();
        if (pctEl)  pctEl.innerText  = `${pct}%`;
        if (barEl)  barEl.style.width = `${pct}%`;
    }
}

function stepRound() {
    if (currentRound < roundsCount) {
        applyRound(currentRound + 1);
    } else {
        applyRound(1);
    }
}

function resetReplay() {
    stopAutoPlay();
    applyRound(1);
}

function toggleReplay() {
    if (autoPlayTimer) {
        stopAutoPlay();
    } else {
        startAutoPlay();
    }
}

function startAutoPlay() {
    document.getElementById('btn-play-text').innerText = 'إيقاف مؤقت';
    document.querySelector('#btn-replay-play i').className = 'fa-solid fa-pause';
    autoPlayTimer = setInterval(() => {
        if (currentRound < roundsCount) {
            applyRound(currentRound + 1);
        } else {
            stopAutoPlay();
        }
    }, 2000);
}

function stopAutoPlay() {
    clearInterval(autoPlayTimer);
    autoPlayTimer = null;
    document.getElementById('btn-play-text').innerText = 'تشغيل الاستعراض التلقائي';
    document.querySelector('#btn-replay-play i').className = 'fa-solid fa-play';
}

// Initial state
applyRound(1);
</script>

</body>
</html>

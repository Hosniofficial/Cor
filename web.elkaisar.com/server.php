<?php
// web.elkaisar.com/server.php - Live Authenticated Game Session
require_once __DIR__ . '/session.php';
$servers = require __DIR__ . '/game-servers.php';
$selectedServerId = (int)($_SESSION['selected_server_id'] ?? 0);
if ($selectedServerId <= 0 || empty($servers[$selectedServerId]['available'])) {
    unset($_SESSION['selected_server_id']);
    header('Location: profile.php');
    exit;
}
$selectedServer = $servers[$selectedServerId];
require_once __DIR__ . '/../app.elkaisar.com/db.php';
$db = getDB();

$playerId = (int)($_SESSION['player_id'] ?? 0);
if ($playerId <= 0) {
    header('Location: login.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM `player` WHERE `id_player` = ? LIMIT 1");
$stmt->execute([$playerId]);
$livePlayer = $stmt->fetch();

if (!$livePlayer) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

// Fetch player's active/primary city
$cStmt = $db->prepare("SELECT * FROM `city` WHERE `id_player` = ? ORDER BY `id_city` ASC LIMIT 1");
$cStmt->execute([$playerId]);
$liveCity = $cStmt->fetch();

if (!$liveCity) {
    $randX = rand(50, 450);
    $randY = rand(50, 450);
    $cityName = mb_substr($livePlayer['name'], 0, 38, 'UTF-8') . ' - العاصمة';
    $cityCreatedAt = time();
    $db->prepare("INSERT INTO `city` (`id_player`, `x`, `y`, `name`, `lvl`, `food`, `wood`, `stone`, `metal`, `coin`, `pop`, `pop_max`, `pop_cap`, `loy`, `loy_max`, `LS`, `pop_LSS`) VALUES (?, ?, ?, ?, 1, 10000, 10000, 10000, 10000, 20000, 100, 100, 100, 100, 100, ?, ?)")->execute([$playerId, $randX, $randY, $cityName, $cityCreatedAt, $cityCreatedAt]);
    $liveCity = ['id_city' => (int)$db->lastInsertId(), 'name' => $cityName, 'x' => $randX, 'y' => $randY];
}

// Fetch player guild info
$gStmt = $db->prepare("SELECT g.id_guild, g.name AS guild_name, g.name, gm.rank, gm.prize_share FROM guild g JOIN guild_member gm ON g.id_guild = gm.id_guild WHERE gm.id_player = ?");
$gStmt->execute([$playerId]);
$liveGuild = $gStmt->fetch(PDO::FETCH_ASSOC);
$livePlayer['guild_data'] = $liveGuild ?: false;

$liveFullGuild = null;
if (!empty($liveGuild)) {
    $fgStmt = $db->prepare("SELECT * FROM guild WHERE id_guild = ?");
    $fgStmt->execute([(int)$liveGuild['id_guild']]);
    $fg = $fgStmt->fetch(PDO::FETCH_ASSOC);
    if ($fg) {
        $lStmt = $db->prepare("SELECT name FROM player WHERE id_player = ? LIMIT 1");
        $lStmt->execute([(int)$fg['id_leader']]);
        $ldrName = $lStmt->fetchColumn() ?: ($fg['leader_name'] ?? 'المدير');

        $rStmt = $db->prepare("SELECT COUNT(*) + 1 FROM guild WHERE prestige > ?");
        $rStmt->execute([(int)$fg['prestige']]);
        $rnkG = (int)$rStmt->fetchColumn();

        $liveFullGuild = [
            'id_guild'          => (int)$fg['id_guild'],
            'name'              => $fg['name'],
            'id_leader'         => (int)$fg['id_leader'],
            'leader_name'       => $ldrName,
            'p_name'            => $ldrName,
            'leaderName'        => $ldrName,
            'rank_g'            => $rnkG,
            'lvl'               => (int)$fg['lvl'],
            'prestige'          => (int)$fg['prestige'],
            'honor'             => (int)($fg['honor'] ?? 0),
            'mem_num'           => (int)$fg['mem_num'],
            'mem_max'           => (int)($fg['mem_max'] ?? ($fg['lvl'] * 10)),
            'slog_top'          => preg_replace('/\D/', '', (string)$fg['slog_top']) ?: '1',
            'slog_cnt'          => preg_replace('/\D/', '', (string)$fg['slog_cnt']) ?: '1',
            'slog_btm'          => preg_replace('/\D/', '', (string)$fg['slog_btm']) ?: '1',
            'word'              => $fg['word'] ?: 'مرحباً بكم في حلفنا',
            'food'              => (int)($fg['food'] ?? 0),
            'wood'              => (int)($fg['wood'] ?? 0),
            'stone'             => (int)($fg['stone'] ?? 0),
            'metal'             => (int)($fg['metal'] ?? 0),
            'coin'              => (int)($fg['coin'] ?? 0),
            'total_prize_share' => (int)($fg['total_prize_share'] ?? 10),
        ];
    }
}

$idPlayer = (int)$livePlayer['id_player'];
$idCity   = (int)$liveCity['id_city'];
$token    = $livePlayer['p_token'];
$apiUrl = getenv('ELKAISAR_API_URL')
    ?: 'http://' . ($_SERVER['SERVER_NAME'] ?? '127.0.0.1') . ':8092';
$apiUrlJs = json_encode($apiUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// Check admin privileges
$isAdmin = false;
if ((int)($livePlayer['user_group'] ?? 0) > 0 || !empty($_SESSION['admin_user'])) {
    $isAdmin = true;
} else {
    $aStmt = $db->prepare("SELECT id_admin FROM `admin_user` WHERE `username` = ? OR `name` = ? LIMIT 1");
    $aStmt->execute([$livePlayer['name'], $livePlayer['name']]);
    if ($aStmt->fetchColumn()) {
        $isAdmin = true;
    }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Caesar of Arabs – قيصر العرب</title>
        <link rel="icon" type="image/png" href="images/favicon.png" sizes="128×128">
        <meta id="" name="viewport" content="width=device-width, initial-scale=0.5, maximum-scale=0.5, minimum-scale=0.5, user-scalable=no">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="css-3.4.113/reset-min.css"/>
        <link rel="stylesheet" href="css-3.4.113/fonts-min.css"/>
        <link rel="stylesheet" href="css-3.4.113/base.css"/>
        <link rel="stylesheet" href="css-3.4.113/city.css"/>
        <link rel="stylesheet" href="css-3.4.113/world.css"/>
        <link rel="stylesheet" href="css-3.4.113/lastStyle.css"/>
        <link rel="stylesheet" href="css-3.4.113/lastOfLast.css"/>
        <link rel="stylesheet" href="css-3.4.113/finishing.css"/>
        <link rel="stylesheet" href="css-3.4.113/tooltips.css"/>
        <link rel="stylesheet" href="css-3.4.113/animation.css"/>
        <link rel="stylesheet" href="css-3.4.113/hero.css"/>
        <link rel="stylesheet" href="css-3.4.113/godPoints.css"/>
        <link rel="stylesheet" href="css-3.4.113/ui.css"/>
        <link rel="stylesheet" href="css-3.4.113/team.css"/>
        <!-- <link rel="stylesheet" href="css/loader.css"/>-->
        <!--<script src="js/loader.js"></script>-->

    </head>
    <body>

        <div id="loader-layer">
            <div  class="logo">
                <img  src="images/icons/Logo-wow.png"/>
            </div>
            <div id="loader-grab">

            </div>
            <div id="load-bar">
                <div>

                </div>
            </div>
            <div id="load-percent">
                0%
            </div>
        </div>
                <div id="menu-bar">
            <div class="content-wrapper flex">
                <div class="left flex">
                    <div class="left-half flex">
                        <div class="wrapper-icon">
                            <div class="icon" id="ToggelFullSrc" style="background-image: url(images/btns/withBg/buttonFullScreenOn.png)"></div>
                        </div>
                        <div class="wrapper-icon">
                           <div class="icon" id="ToggelSound" style="background-image: url(images/btns/withBg/buttonSoundOptions.png)"></div>
                        </div>
                        <div class="wrapper-icon">
                            <div class="icon" id="OpenSettingsBox" style="background-image: url(images/btns/withBg/buttonOptions.png)"></div>
                        </div>
                        <?php if ($isAdmin): ?>
                        <div class="wrapper-icon" title="لوحة تحكم الإدارة">
                            <a href="admin/index.php" target="_blank" style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;text-decoration:none;font-size:16px;" title="👑 لوحة الإدارة">👑</a>
                        </div>
                        <?php endif; ?>
                        <div class="wrapper-icon" title="الملف الشخصي">
                            <a href="profile.php" style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;text-decoration:none;font-size:15px;" title="الملف الشخصي">👤</a>
                        </div>
                        <div class="wrapper-icon" title="تسجيل الخروج">
                            <a href="logout.php" style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;text-decoration:none;font-size:15px;color:#ff6b6b;" onclick="return confirm('هل تريد بالتأكيد تسجيل الخروج؟');" title="🚪 تسجيل الخروج">🚪</a>
                        </div>
                    </div>
                    <div class="right-half">
                        <div id="server-bing">
                            Ping:  <span class="stroke"></span>
                        </div>
                    </div>
                </div>
                <div class="mid" style="position: absolute;right: 50%;margin-right: -150px;">
                    <div id="server-name">
                        <div class="name"><?php echo htmlspecialchars($selectedServer['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="time"></div>
                    </div>
                </div>
                <div id="global-menu-list" class="right">
                    
                </div>
            </div>
        </div>
        <div id="global-announce" data-statge="0" data-now="6000">
            <div class="wrapper">
                <div class="text"><span class="warning">مبروك!</span>  لقد  اصبح محمد  المسيطر على  عاصمة المشاة</div>
            </div>
        </div>
        <div id="city-data">
            <div class="name"><?php echo htmlspecialchars($liveCity['name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="coords">[ <?php echo (int)$liveCity['x']; ?> , <?php echo (int)$liveCity['y']; ?> ]</div>
        </div>

        <div id="luck-wheel-btn">
            <div class="track"></div>
        </div>
        
        
        <div id="godGateBtnWrapper">
            <div class="fire"></div>
            <button title="بوبات الالهة"></button>
            <div class="fire-tail"></div>
        </div>
        <div id="helpGateBtnWrapper">
            <button title="بوبات الالهة"></button>
            <div class="fire-tail"></div>
        </div>
	    <div id="ArenaChallangeBtnWrapper">
            <button></button>
            <div class="fire-tail"></div>
        </div>
        <div id="PlayerTeamWrapper">
            <button class="NoTeamIcon"></button>
        </div>
        <div class="profile" id="player-profile">
            <div id="p-provile-slider">
                <img src="images/btns/show_hide.png">
            </div>
            <div class="left">
                <div id="matrial-box">
                    <button></button>
                </div>
                <div id="smallMap-icon">
                    <img src="images/icons/world_map.png">
                </div>
                <div class="avatar">
                    <div class="avatar-img">
                        <img ondragstart="return false;" src="images/hero/faceA5.jpg">
                    </div>
                </div>
                <div class="avatar-name">
                    <h1><?php echo htmlspecialchars($livePlayer['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                </div>
            </div>
            <div class="list_of_data">
                <ul>
                    <li id="player_prestige">
                        <img ondragstart="return false;" src="images/icons/prestige.png"> 
                        <span>53</span>
                    </li>
                    <li id="player_title">
                        <img ondragstart="return false;" src="images/icons/rank-player.png"> 
                        <span></span>
                    </li>
                    <li id="player_rank">
                        <img ondragstart="return false;" src="images/icons/stat_streak.png"> 
                        <span>1354</span>
                    </li>
                    <li  id="player_guild" id_guild = "">
                        <img ondragstart="return false;" src="images/icons/guild.png"> 
                        <span></span>
                    </li>
                    <li id="player_honor">
                        <img  ondragstart="return false;" src="images/icons/honor.png"> 
                        <span>0</span>
                    </li>
                    <li id="player_gold">
                        <img  ondragstart="return false;" src="images/icons/gold.png"> 
                        <span>0</span>
                    </li>
                </ul>
            </div>

        </div>
        <div id="city_col">
            <div id="player-city-list">
                <ul>

                </ul>
            </div>
            <button id="WorldCity" disabled="disabled" data-view="city">
                العالم
            </button>
        </div>

        <div id="city-profile">
            <div id="city-profile-tooltipe">

            </div>
            <div id="city-profile-slider">
                <img src="images/btns/show_hide.png"/>
            </div>

            <div class="upper_nave_bar">
                <ul>
                    <li show='default'>
                        <img ondragstart="return false;" src="images/btns/menu_res.png"/>
                    </li>
                    <li show='hero'>
                        <img ondragstart="return false;" src="images/btns/menu_hero.png"/>
                    </li>
                    <li show='army'>
                        <img ondragstart="return false;" src="images/btns/menu_mill.png"/>
                    </li>
                    <li show='wild'>
                        <img  ondragstart="return false;" src="images/btns/menu_bar.png"/>
                    </li>
                </ul>
            </div>
            <div class="page_content" data-view="resource">

            </div>

        </div>

        <div id="player_stat_bar">
            <ul></ul>
        </div>

        <div id="current-tasks">
            <div id="build-tsk">
             


            </div>
            <div id="study-tsk">
               
                
            </div>
            <div id="jop-tsk">
            </div>
        </div>
        
        <div id="chat-box" class="bg-general">
            <div id="navigator-bar" class="bg-btn-blu">
                <div id="UPDOWN-chat">
                    <img src="images/btns/show_hide.png"/>
                </div>
                <div id="hide-show" style="display: none">
                    <div id="nav-btn" class="flex">
                        <input type="checkbox" id="FastNav" style="margin-right: 10px;" title="انتقال سريع">
                        <button class="full-btn full-btn-3x" style="">اذهب الى </button>
                        
                    </div>

                    <label class="pull-R">Y</label>
                    <div id="y_coord-input" class="coords-input">
                        <input type="text" class="only_num input" min="0"  max="499"/>
                    </div>
                    <label class="pull-R">X</label>
                    <div id="x_coord-input" class="coords-input ">
                        <input type="text" class="only_num input" min="0" max="499"/>
                    </div>

                </div>
                <div id="active-chat-rooms">
                    <ul>

                    </ul>
                </div>
            </div>
            <div id="drop-down-list-wrapper">

            </div>
            <div style="height: 20px;"></div>
            <div id="chat-area" data-width="x">
                <div id="chat-icons">
                    <ul>
                        <li data-show="anounce">
                            <img src="images/icons/chat/annonces.png"/>
                        </li>
                        <li class="active" data-show="world">
                            <img src="images/icons/chat/world.png"/>
                        </li>
                        <li data-show="guild">
                            <img src="images/icons/chat/guild.png"/>
                        </li>
                        <li data-show="private">
                            <img src="images/icons/chat/private.png"/>
                        </li>
                    </ul>
                </div>
                <div id="msg-area">
                </div>
            </div>
            <div id="input-area" class="flex">
                <div class="quote-wrapper"></div>
                <div id="expand-chat">
                    <button class=" full-btn full-btn-3x send pull-R">
                        <img src="images/btns/buttonGoHome.png">
                    </button>
                    <button class="full-btn full-btn-3x pull-L expand">
                        <i></i>
                        <i></i>
                        <i></i>
                    </button>
                </div>
                <div id="input-chat">
                    <div class="input-area flex">
                        <div class="input-warpper">
                            <input data-pastable="true"  class="input chat-input"/>
                        </div>
                        <div class="emjoi-icon">
                            <button class="full-btn-3x flex">
                                <img src="images/icons/emjoi.png">
                            </button>
                        </div>
                    </div>
                </div>
                <div id="msg-for">
                    <button id="chat-to" class="full-btn full-btn-3x" data-chat-to="world">
                        <img src="images/icons/chat/world.png">
                        <label>العالم</label>
                    </button>
                </div>
            </div>
        </div>
        <div id="GameWindow"></div>
        <script>
            var player             = <?= json_encode($livePlayer, JSON_UNESCAPED_UNICODE) ?>;
            const  BASE_ASSET_BATH = "";
            const  ID_PLAYER       = <?= $idPlayer ?>;
            var  TOKEN             = <?= json_encode($token) ?>;
            const  SERVER_ID       = <?= $selectedServerId ?>;
            const  BASE_URL        = window.location.origin;
            const  API_URL         = <?= $apiUrlJs ?>;
            const  WS_PORT         = 8081;
            const  WS_HOST         = window.location.hostname;
            const  ID_FIRST_CITY   = <?= $idCity ?>;
            const  JS_VERSION      = "-3.4.113";
            var PLAYER_NOTIF     = {"msg_in":0,"msg_diff":0,"msg_report":0,"spy_report":0,"battel_number":0,"hero_in_battel":0,"hero_back":0,"spy_task":0};
            var isMobile         = false; //initiate as false
			var EXAMINE_PLAYER_MUTE = false;
            // device detection
            if (/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|ipad|iris|kindle|Android|Silk|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(navigator.userAgent)
                    || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(navigator.userAgent.substr(0, 4))) {
                isMobile = true;
            };
            
            
        </script>
        <!--
        <script type="text/javascript" src="js-3.4.113/jquery-3.2.1.min.js"></script>
        <script type="text/javascript" src="js-3.4.113/lib/jquery.nicescroll.min.js"></script>
        <script type="text/javascript" src="js-3.4.113/lib/jquery-ui.min.js"></script>
        <script type="text/javascript" src="js-3.4.113/lib/phaser.min.js"></script>
        <script type="text/javascript" src="js-3.4.113/base/translate.js"></script>
        <script type="text/javascript" src="js-3.4.113/base/userLan.js"></script>
        <script type="text/javascript" src="js-3.4.113/base/navBar.js"></script>
        <script type="text/javascript" src="js-3.4.113/base/LPreLoad.js"></script>
        <script type="text/javascript" src="js-3.4.113/base/LBaseData.js"></script>
        <script type="text/javascript" src="js-3.4.113/base/base.js"></script>
        <script type="text/javascript" src="js-3.4.113/base/boxWrapper.js"></script>
        <script type="text/javascript" src="js-3.4.113/base/rank.js"></script>
        <script type="text/javascript" src="js-3.4.113/player.js"></script>
        <script type="text/javascript" src="js-3.4.113/world/province.js"></script>
        <script type="text/javascript" src="js-3.4.113/world/city.js"></script>
        <script type="text/javascript" src="js-3.4.113/fullscr.js"></script>
        <script type="text/javascript" src="js-3.4.113/alert.js"></script>
        <script type="text/javascript" src="js-3.4.113/city/city_profile.js"></script>
        <script type="text/javascript" src="js-3.4.113/city/army_building.js"></script>
        <script type="text/javascript" src="js-3.4.113/interatction.js"></script>
        <script type="text/javascript" src="js-3.4.113/base/schadular.js"></script>
        <script type="text/javascript" src="js-3.4.113/building.js"></script>
        <script type="text/javascript" src="js-3.4.113/city/cityBuilding.js"></script>
        <script type="text/javascript" src="js-3.4.113/city/PlayerHero.js"></script>
        <script type="text/javascript" src="js-3.4.113/army.js"></script>
        <script type="text/javascript" src="js-3.4.113/hero.js"></script>
        <script type="text/javascript" src="js-3.4.113/heroArmy.js"></script>
        <script type="text/javascript" src="js-3.4.113/matrial.js"></script>
        <script type="text/javascript" src="js-3.4.113/LItemUse.js"></script>
        <script type="text/javascript" src="js-3.4.113/education.js"></script>
        <script type="text/javascript" src="js-3.4.113/menu-list/menuList.js"></script>
        <script type="text/javascript" src="js-3.4.113/menu-list/exchange.js"></script>
        <script type="text/javascript" src="js-3.4.113/menu-list/battelReport.js"></script>
        <script type="text/javascript" src="js-3.4.113/menu-list/dominant.js"></script>
        <script type="text/javascript" src="js-3.4.113/menu-list/LItem.js"></script>
        <script type="text/javascript" src="js-3.4.113/ws/server.js"></script>
        <script type="text/javascript" src="js-3.4.113/ws/wsBattel.js"></script>
        <script type="text/javascript" src="js-3.4.113/ws/lib/chat.js"></script>
        <script type="text/javascript" src="js-3.4.113/ws/lib/battel.js"></script>
        <script type="text/javascript" src="js-3.4.113/ws/lib/guild.js"></script>
        <script type="text/javascript" src="js-3.4.113/ws/lib/mail.js"></script>
        <script type="text/javascript" src="js-3.4.113/ws/lib/hero.js"></script>
        <script type="text/javascript" src="js-3.4.113/ws/lib/player.js"></script>
        <script type="text/javascript" src="js-3.4.113/ws/lib/serverAnnounce.js"></script>
        <script type="text/javascript" src="js-3.4.113/ws/lib/city.js"></script>
        <script type="text/javascript" src="js-3.4.113/ws/lib/Base.js"></script>
        <script type="text/javascript" src="js-3.4.113/world/worldUnit.js"></script>
        <script type="text/javascript" src="js-3.4.113/world/worldCampBox.js"></script>
        <script type="text/javascript" src="js-3.4.113/world/WorldUnitIcon.js"></script>
        <script type="text/javascript" src="js-3.4.113/world/WorldUnitArmy.js"></script>
        <script type="text/javascript" src="js-3.4.113/world/worldUtil.js"></script>
        <script type="text/javascript" src="js-3.4.113/world/worldUnitPrize.js"></script>
        <script type="text/javascript" src="js-3.4.113/world/worldMap.js"></script>
        <script type="text/javascript" src="js-3.4.113/world/WorldMapBattel.js"></script>
        <script type="text/javascript" src="js-3.4.113/world/world.js"></script>
        <script type="text/javascript" src="js-3.4.113/battel.js"></script>
        <script type="text/javascript" src="js-3.4.113/guild.js"></script>
        <script type="text/javascript" src="js-3.4.113/message.js"></script>
        <script type="text/javascript" src="js-3.4.113/quest.js"></script>
        <script type="text/javascript" src="js-3.4.113/lastUtil.js"></script>
        <script type="text/javascript" src="js-3.4.113/tools/luck_wheel.js"></script>
        <script type="text/javascript" src="js-3.4.113/tools/market.js"></script>
        <script type="text/javascript" src="js-3.4.113/tools/palace.js"></script>
        <script type="text/javascript" src="js-3.4.113/tools/spy.js"></script>
        <script type="text/javascript" src="js-3.4.113/navigate.js"></script>
        <script type="text/javascript" src="js-3.4.113/animation.js"></script>
        <script type="text/javascript" src="js-3.4.113/equipment.js"></script>
        <script type="text/javascript" src="js-3.4.113/tools/tradeCenter.js"></script>
        <script type="text/javascript" src="js-3.4.113/tools/jop.js"></script>
        <script type="text/javascript" src="js-3.4.113/battel/joinAttack.js"></script>
        <script type="text/javascript" src="js-3.4.113/battel/battelField.js"></script>
        <script type="text/javascript" src="js-3.4.113/feature/godGate.js"></script>
        <script type="text/javascript" src="js-3.4.113/feature/setting.js"></script>
        <script type="text/javascript" src="js-3.4.113/feature/emjoi.js"></script>
        <script type="text/javascript" src="js-3.4.113/feature/contribute.js"></script>
        <script type="text/javascript" src="js-3.4.113/feature/arenaChallange.js"></script>
        <script type="text/javascript" src="js-3.4.113/ui/ui.js"></script>

-->  <script type="text/javascript" src="js-3.4.113/lib/jquery-3.2.1.min.js"></script>
            <script type="text/javascript" src="js-3.4.113/lib/jquery.nicescroll.min.js"></script>
            <script type="text/javascript" src="js-3.4.113/lib/jquery-ui.min.js"></script>
            <script type="text/javascript" src="js-3.4.113/lib/phaser.min.js"></script>

        <!-- Compatibility & Bulletproofing Layer -->
        <script>
        (function() {
            var liveCityData = <?php echo json_encode($liveCity, JSON_UNESCAPED_UNICODE); ?>;
            var livePlayerData = <?php echo json_encode($livePlayer, JSON_UNESCAPED_UNICODE); ?>;
            window.LIVE_GUILD_DATA = <?php echo json_encode($liveFullGuild, JSON_UNESCAPED_UNICODE); ?>;
            window.LIVE_PLAYER_GUILD = <?php echo json_encode($liveGuild ?: null, JSON_UNESCAPED_UNICODE); ?>;

            window.PLAYER_ALL_CITIES = [liveCityData];
            if (window.LIVE_PLAYER_GUILD && window.player) {
                window.player.guild_data = window.LIVE_PLAYER_GUILD;
            }

            // 0. Canvas 2D willReadFrequently optimization for Phaser
            var _origGetContext = HTMLCanvasElement.prototype.getContext;
            HTMLCanvasElement.prototype.getContext = function(type, attributes) {
                if (type === '2d') {
                    attributes = Object.assign({ willReadFrequently: true }, attributes);
                }
                return _origGetContext.call(this, type, attributes);
            };

            // Global Missing Image Warning Listener (captures all 404/broken images with their exact path)
            window.addEventListener('error', function(event) {
                var target = event.target;
                if (target && (target.tagName === 'IMG' || target instanceof HTMLImageElement)) {
                    var missingSrc = target.src || target.getAttribute('src');
                    console.warn('%c⚠️ [صورة غير موجودة - Missing Image]: ' + missingSrc, 'background: #b71c1c; color: #ffffff; padding: 3px 8px; font-weight: bold; font-size: 11px; border-radius: 3px;');
                }
            }, true);

            // 1. Bulletproof JSON.parse
            var _origParse = JSON.parse;
            JSON.parse = function(val, reviver) {
                if (typeof val === 'object' && val !== null) return val;
                return _origParse(val, reviver);
            };

            // 2. Global isJson validator
            window.isJson = function(str) {
                if (typeof str === 'object' && str !== null) return true;
                try {
                    _origParse(str);
                    return true;
                } catch(e) {
                    return false;
                }
            };

            // Use plain HTTP transports when the game page itself is served over HTTP.
            if (window.location.protocol !== 'https:' && window.jQuery) {
                window.jQuery.ajaxPrefilter(function(options) {
                    if (typeof options.url === 'string') {
                        options.url = options.url.replace(
                            /^https:\/\/([^/?#]+:8081)(?=\/|[?#]|$)/i,
                            'http://$1'
                        );
                    }
                });
            }

            var _origWebSocket = window.WebSocket;
            window.WebSocket = function(url, protocols) {
                if (typeof url === 'string' && window.location.protocol !== 'https:') {
                    url = url.replace(
                        /^wss:\/\/([^/?#]+:8081)(?=[/?#]|$)/i,
                        'ws://$1'
                    );
                }
                return protocols ? new _origWebSocket(url, protocols) : new _origWebSocket(url);
            };
            window.WebSocket.prototype = _origWebSocket.prototype;

            // 4. Clean alert handler (suppress [object Object] and raw HTML pages)
            var _origAlert = window.alert;
            window.alert = function(msg) {
                if (typeof msg === 'object' && msg !== null) {
                    console.warn('[DEV alert suppressed]:', msg);
                    return;
                }
                if (typeof msg === 'string' && (msg.indexOf('<!DOCTYPE') !== -1 || msg.indexOf('<html') !== -1 || msg.indexOf('<?php') !== -1)) {
                    console.warn('[HTML alert suppressed]:', msg);
                    return;
                }
                console.log('[DEV alert]:', msg);
                _origAlert(msg);
            };

            // 5. Safe Proxy for PLAYER_QUEST_DONE
            var _playerQuestDone = {};
            Object.defineProperty(window, 'PLAYER_QUEST_DONE', {
                get: function() { return _playerQuestDone; },
                set: function(val) {
                    _playerQuestDone = new Proxy(val || {}, {
                        get: function(target, prop) {
                            if (prop in target) return target[prop];
                            return 0;
                        }
                    });
                },
                configurable: true,
                enumerable: true
            });
            window.PLAYER_QUEST_DONE = {};

            $(document).on("PlayerReady", function() {
                if (window.Elkaisar) {
                    if (!Elkaisar.CurrentCity) Elkaisar.CurrentCity = {};
                    Elkaisar.CurrentCity.City = liveCityData;
                    window.PLAYER_ALL_CITIES = [liveCityData];

                    if (window.LIVE_PLAYER_GUILD) {
                        Elkaisar.DPlayer.GuildData = window.LIVE_PLAYER_GUILD;
                        if (window.player) window.player.guild_data = window.LIVE_PLAYER_GUILD;
                    }
                    if (window.LIVE_GUILD_DATA) {
                        Elkaisar.Guild.GuildData = Object.assign({}, window.LIVE_GUILD_DATA, Elkaisar.Guild.GuildData);
                        Elkaisar.Guild.LeaderName = window.LIVE_GUILD_DATA.leaderName || window.LIVE_GUILD_DATA.p_name;
                        Elkaisar.Guild.Allay = Elkaisar.Guild.Allay || [];
                        Elkaisar.Guild.prizeShare = window.LIVE_PLAYER_GUILD ? window.LIVE_PLAYER_GUILD.prize_share : 10;
                    }
                }
            });
        })();
        </script>

            <script type="text/javascript" src="js-3.4.113/obfuscated.js"></script>

        <!-- Post-Engine Bulletproofing & Guard Rails -->
        <script>
        (function() {
            // Guard Building.calResource
            if (window.Building && Building.calResource) {
                ['food', 'wood', 'stone', 'metal'].forEach(function(r) {
                    Building.calResource[r] = function(type, lvl) {
                        if (!window.BUILDING_JSON_DATA || !BUILDING_JSON_DATA[type] || !BUILDING_JSON_DATA[type]["lvl_req"] || !BUILDING_JSON_DATA[type]["lvl_req"][lvl]) {
                            return 0;
                        }
                        return lvl < 30 ? (BUILDING_JSON_DATA[type]["lvl_req"][lvl][r] || 0) : "--";
                    };
                });
            }
            // Guard getArabicNumbers
            var _origGetArabicNumbers = window.getArabicNumbers;
            window.getArabicNumbers = function(str) {
                if (str === undefined || str === null || (typeof str === 'number' && isNaN(str)) || str === 'NaN') {
                    return '0';
                }
                return _origGetArabicNumbers ? _origGetArabicNumbers(str) : str;
            };

            // Guard and Enhance Guild Data
            if (window.Elkaisar && window.Elkaisar.Guild) {
                if (window.LIVE_GUILD_DATA) {
                    Elkaisar.Guild.GuildData = Object.assign({}, window.LIVE_GUILD_DATA, Elkaisar.Guild.GuildData);
                    Elkaisar.Guild.LeaderName = window.LIVE_GUILD_DATA.leaderName || window.LIVE_GUILD_DATA.p_name;
                    Elkaisar.Guild.Allay = Elkaisar.Guild.Allay || [];
                    Elkaisar.Guild.prizeShare = window.LIVE_PLAYER_GUILD ? window.LIVE_PLAYER_GUILD.prize_share : 10;
                }
                if (window.LIVE_PLAYER_GUILD) {
                    Elkaisar.DPlayer.GuildData = window.LIVE_PLAYER_GUILD;
                    if (window.player) window.player.guild_data = window.LIVE_PLAYER_GUILD;
                }

                // Auto-refresh open guild views when new guild data arrives
                if (window.Guild && Guild.getGuildData) {
                    var _origGetGuildData = Guild.getGuildData;
                    Guild.getGuildData = function() {
                        var p = _origGetGuildData.apply(this, arguments);
                        if (p && p.done) {
                            p.done(function() {
                                if ($("#guild-guild_data").length > 0) {
                                    var updatedHtml = Guild.content_forGuild_data();
                                    $("#guild-guild_data").replaceWith(updatedHtml);
                                }
                            });
                        }
                        return p;
                    };
                }
            }
        })();
        </script>
        
          </body>
</html>
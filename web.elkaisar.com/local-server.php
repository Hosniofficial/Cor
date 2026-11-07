<?php
// local-server.php - Live MySQL-backed Elkaisar Game
require_once __DIR__ . '/../app.elkaisar.com/db.php';
$db = getDB();
$stmt = $db->query("SELECT * FROM `player` ORDER BY `id_player` ASC LIMIT 1");
$livePlayer = $stmt->fetch();
if (!$livePlayer) {
    $created = createFreshPlayer('البطل سيزر');
    $stmt = $db->prepare("SELECT * FROM `player` WHERE `id_player` = ?");
    $stmt->execute([$created['playerId']]);
    $livePlayer = $stmt->fetch();
}
$cStmt = $db->prepare("SELECT * FROM `city` WHERE `id_player` = ? ORDER BY `id_city` ASC LIMIT 1");
$cStmt->execute([$livePlayer['id_player']]);
$liveCity = $cStmt->fetch();

$idPlayer = (int)$livePlayer['id_player'];
$idCity   = (int)$liveCity['id_city'];
$token    = $livePlayer['p_token'];
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>قيصر الشرق - Local Dev</title>
        <link rel="icon" type="image/png" href="images/favicon.png" sizes="128×128">
        <meta id="" name="viewport" content="width=device-width, initial-scale=0.5, maximum-scale=0.5, minimum-scale=0.5, user-scalable=no">
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
                    </div>
                    <div class="right-half">
                        <div id="server-bing">
                            Ping:  <span class="stroke"></span>
                        </div>
                    </div>
                </div>
                <div class="mid" style="position: absolute;right: 50%;margin-right: -150px;">
                    <div id="server-name">
                        <div class="name">العقرب</div>
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
            <div class="name"><?php echo htmlspecialchars($liveCity['name']); ?></div>
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
                    <h1> 
                        <?php echo htmlspecialchars($livePlayer['name']); ?>
                    </h1>
                </div>
            </div>
            <div class="list_of_data">
                <ul>
                    <li id="player_prestige">
                        <img ondragstart="return false;" src="images/icons/prestige.png"> 
                        <span><?php echo (int)$livePlayer['prestige']; ?></span>
                    </li>
                    <li id="player_title">
                        <img ondragstart="return false;" src="images/icons/rank-player.png"> 
                        <span></span>
                    </li>
                    <li id="player_rank">
                        <img ondragstart="return false;" src="images/icons/stat_streak.png"> 
                        <span><?php echo (int)$livePlayer['rank']; ?></span>
                    </li>
                    <li  id="player_guild" id_guild = "">
                        <img ondragstart="return false;" src="images/icons/guild.png"> 
                        <span></span>
                    </li>
                    <li id="player_honor">
                        <img  ondragstart="return false;" src="images/icons/honor.png"> 
                        <span><?php echo (int)$livePlayer['honor']; ?></span>
                    </li>
                    <li id="player_gold">
                        <img  ondragstart="return false;" src="images/icons/gold.png"> 
                        <span><?php echo (int)$livePlayer['gold']; ?></span>
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
                        <input type="checkbox" id="FastNav" checked="true" style="margin-right: 10px;">
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
            var player             = <?php echo json_encode($livePlayer, JSON_UNESCAPED_UNICODE); ?>;
            const  BASE_ASSET_BATH = "";
            const  ID_PLAYER       = <?php echo $idPlayer; ?>;
            var  TOKEN             = <?php echo json_encode($token); ?>;
            const  SERVER_ID       = 1;
            // ── LOCAL DEV: Override production URLs ────────────────────────
            const  BASE_URL      = "http://localhost:8080";
            const  API_URL       = "http://localhost:8090";
            const  WS_PORT       = 8081;
            const  WS_HOST       = "localhost";
            // ──────────────────────────────────────────────────────────────
            const  ID_FIRST_CITY = <?php echo $idCity; ?>;
            const  JS_VERSION    = "-3.4.113";
            var PLAYER_NOTIF     = {"msg_in":0,"msg_diff":0,"msg_report":0,"spy_report":0,"battel_number":0,"hero_in_battel":0,"hero_back":0,"spy_task":0};
            var isMobile         = false; //initiate as false
			var EXAMINE_PLAYER_MUTE = false;
            // device detection
            if (/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|ipad|iris|kindle|Android|Silk|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(navigator.userAgent)
                    || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(navigator.userAgent.substr(0, 4))) {
                isMobile = true;
            };
        </script>

        <script type="text/javascript" src="js-3.4.113/lib/jquery-3.2.1.min.js"></script>
        <script type="text/javascript" src="js-3.4.113/lib/jquery.nicescroll.min.js"></script>
        <script type="text/javascript" src="js-3.4.113/lib/jquery-ui.min.js"></script>
        <script type="text/javascript" src="js-3.4.113/lib/phaser.min.js"></script>

        <!-- Local Dev Compatibility Layer -->
        <script>
        (function() {
            var liveCityData = <?php echo json_encode($liveCity, JSON_UNESCAPED_UNICODE); ?>;
            var livePlayerData = <?php echo json_encode($livePlayer, JSON_UNESCAPED_UNICODE); ?>;

            // Ensure window.PLAYER_ALL_CITIES always returns a valid array
            window.PLAYER_ALL_CITIES = [liveCityData];

            // 1. Bulletproof JSON.parse: If val is already an object, return it without throwing
            var _origParse = JSON.parse;
            JSON.parse = function(val, reviver) {
                if (typeof val === 'object' && val !== null) {
                    return val;
                }
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

            // 3. Convert wss:// to ws:// for localhost so no SSL cert is required
            var _origWebSocket = window.WebSocket;
            window.WebSocket = function(url, protocols) {
                if (typeof url === 'string') {
                    url = url.replace(/^wss:\/\/(localhost|127\.0\.0\.1)/i, 'ws://$1');
                }
                return protocols ? new _origWebSocket(url, protocols) : new _origWebSocket(url);
            };
            window.WebSocket.prototype = _origWebSocket.prototype;

            // 4. Convert https:// to http:// for localhost:8081 AJAX requests & force json dataType for .json
            if (window.$ && $.ajaxPrefilter) {
                $.ajaxPrefilter(function(options) {
                    if (options.url) {
                        options.url = options.url.replace(/^https:\/\/(localhost|127\.0\.0\.1):8081/i, 'http://$1:8081');
                        if (options.url.indexOf('.json') !== -1) {
                            options.dataType = 'json';
                        }
                    }
                });
            }

            // 5. Clean alert handler (don't pop up [object Object])
            var _origAlert = window.alert;
            window.alert = function(msg) {
                if (typeof msg === 'object' && msg !== null) {
                    console.warn('[DEV alert suppressed]:', msg);
                    return;
                }
                console.log('[DEV alert]:', msg);
                _origAlert(msg);
            };

            // 6. Safe Proxy for PLAYER_QUEST_DONE (ensures uncompleted quests return 0 instead of undefined)
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

            // 6. Ensure Elkaisar.CurrentCity.City is populated as soon as Elkaisar is defined
            $(document).on("PlayerReady", function() {
                if (window.Elkaisar) {
                    if (!Elkaisar.CurrentCity) Elkaisar.CurrentCity = {};
                    Elkaisar.CurrentCity.City = liveCityData;
                    window.PLAYER_ALL_CITIES = [liveCityData];
                    console.log('[DEV] CurrentCity.City initialized successfully:', Elkaisar.CurrentCity.City);
                }
            });

            // 7. Debug click on construct_building
            $(document).on("click", ".construct_building", function() {
                console.log('[DEV CLICK construct_building]:', {
                    buildingToBuild: $(this).attr('data-building'),
                    place: $(".for_building_box").attr("data-building-place"),
                    type: $(".for_building_box").attr("data-building-type"),
                    lvl: $(".for_building_box").attr("data-building-lvl"),
                    currentCityId: window.Elkaisar && Elkaisar.CurrentCity && Elkaisar.CurrentCity.City ? Elkaisar.CurrentCity.City.id_city : 'NOT_FOUND'
                });
            });
        })();
        </script>

        <script type="text/javascript" src="js-3.4.113/obfuscated.js"></script>
        
        <!-- Post-Engine Bulletproofing & Guard Rails -->
        <script>
        (function() {
            // 1. Guard Elkaisar.Building.verfiyCondetion
            if (window.Elkaisar && Elkaisar.Building) {
                var _origVerfiy = Elkaisar.Building.verfiyCondetion;
                Elkaisar.Building.verfiyCondetion = function(BuildingType, BuildingLvl) {
                    try {
                        if (!Elkaisar.Building.BuildingData) {
                            return [];
                        }
                        if (typeof Elkaisar.Building.BuildingData === 'string') {
                            try { Elkaisar.Building.BuildingData = JSON.parse(Elkaisar.Building.BuildingData); } catch(e){}
                        }
                        if (!Elkaisar.Building.BuildingData[BuildingType]) {
                            return [];
                        }
                        var reqs = Elkaisar.Building.BuildingData[BuildingType].lvlRequirment;
                        if (!reqs || !reqs[Math.min(BuildingLvl || 0, 29)] || !reqs[Math.min(BuildingLvl || 0, 29)].condetion) {
                            return [];
                        }
                        return _origVerfiy.apply(this, arguments);
                    } catch (e) {
                        console.warn('[verfiyCondetion guard caught]:', e);
                        return [];
                    }
                };
            }

            // 2. Safe Proxy for Elkaisar.BaseData.Items
            var defaultItem = {
                name: "مادة",
                image: "images/items/item01.jpg",
                req: [],
                use: "none",
                gold: 0,
                desc: "",
                table: "matrial_box",
                db_tab: "matrial_box",
                long_desc: "",
                playerAmount: 0
            };

            function wrapItems(raw) {
                if (typeof raw === 'string') {
                    try { raw = JSON.parse(raw); } catch(e) { raw = {}; }
                }
                if (!raw || typeof raw !== 'object') raw = {};
                return new Proxy(raw, {
                    get: function(target, prop) {
                        if (prop in target) {
                            var it = target[prop];
                            if (it && typeof it === 'object' && it.playerAmount === undefined) {
                                it.playerAmount = 0;
                            }
                            return it;
                        }
                        if (typeof prop === 'string' && prop !== 'then' && prop !== 'toJSON' && isNaN(prop)) {
                            return defaultItem;
                        }
                        return target[prop];
                    }
                });
            }

            if (window.Elkaisar && Elkaisar.BaseData) {
                var _itemsTarget = wrapItems(Elkaisar.BaseData.Items);
                try {
                    Object.defineProperty(Elkaisar.BaseData, 'Items', {
                        get: function() { return _itemsTarget; },
                        set: function(val) { _itemsTarget = wrapItems(val); },
                        configurable: true
                    });
                } catch(e) {
                    console.warn('[Items proxy warning]:', e);
                }
            }

            // 3. Safe Proxy for Elkaisar.Equip.EquipList
            var defaultEquip = {
                name: "معدة",
                image: "images/equip/equip01.jpg",
                desc: "",
                Texture2D: ""
            };

            function wrapEquip(raw) {
                if (typeof raw === 'string') {
                    try { raw = JSON.parse(raw); } catch(e) { raw = {}; }
                }
                if (!raw || typeof raw !== 'object') raw = {};
                return new Proxy(raw, {
                    get: function(target, prop) {
                        if (prop in target) return target[prop];
                        if (typeof prop === 'string' && prop !== 'then' && prop !== 'toJSON') {
                            target[prop] = Object.assign({}, defaultEquip);
                            return target[prop];
                        }
                        return target[prop];
                    }
                });
            }

            if (window.Elkaisar && Elkaisar.Equip) {
                var _equipTarget = wrapEquip(Elkaisar.Equip.EquipList);
                try {
                    Object.defineProperty(Elkaisar.Equip, 'EquipList', {
                        get: function() { return _equipTarget; },
                        set: function(val) { _equipTarget = wrapEquip(val); },
                        configurable: true
                    });
                } catch(e) {
                    console.warn('[EquipList proxy warning]:', e);
                }
            }

            // 4. Guard Elkaisar.ArenaChallange.HeroList
            if (window.Elkaisar && Elkaisar.ArenaChallange) {
                var _origArenaHeroList = Elkaisar.ArenaChallange.HeroList;
                Elkaisar.ArenaChallange.HeroList = function(idCity) {
                    try {
                        if (!idCity && Elkaisar.DPlayer && Elkaisar.DPlayer.City) {
                            idCity = Object.keys(Elkaisar.DPlayer.City)[0];
                        }
                        if (Elkaisar.DPlayer && Array.isArray(Elkaisar.DPlayer.Heros)) {
                            Elkaisar.DPlayer.Heros = Elkaisar.DPlayer.Heros.filter(function(h) {
                                return h && h.Hero && typeof h.Hero === 'object' && h.Hero.id_city !== undefined;
                            });
                        }
                        return _origArenaHeroList.apply(this, arguments);
                    } catch(e) {
                        console.warn('[ArenaChallange.HeroList guard caught]:', e);
                        return '';
                    }
                };
            }

            // 5. Guard Matrial.getPlayerAmount (prevents NaN across all item boxes)
            if (window.Matrial) {
                Matrial.getPlayerAmount = function(matrial) {
                    if (window.Elkaisar && Elkaisar.BaseData && Elkaisar.BaseData.Items && Elkaisar.BaseData.Items[matrial]) {
                        var val = Elkaisar.BaseData.Items[matrial].playerAmount;
                        return (val !== undefined && val !== null && !isNaN(val)) ? Number(val) : 0;
                    }
                    return 0;
                };
            }

            // 6. Guard getArabicNumbers (prevents "NaN" in Arabic numbers)
            var _origGetArabicNumbers = window.getArabicNumbers;
            window.getArabicNumbers = function(str) {
                if (str === undefined || str === null || (typeof str === 'number' && isNaN(str)) || str === 'NaN') {
                    return '0';
                }
                return _origGetArabicNumbers ? _origGetArabicNumbers(str) : str;
            };

            // 7. Guard calPageNumber & Trading.calPageNum (never return 0 or NaN for page counters)
            if (typeof window.calPageNumber === 'function') {
                var _origCalPageNumber = window.calPageNumber;
                window.calPageNumber = function(type, isMall) {
                    var res = _origCalPageNumber(type, isMall);
                    return (isNaN(res) || res <= 0) ? 1 : res;
                };
            }
            if (window.Trading && Trading.calPageNum) {
                var _origTradingCalPageNum = Trading.calPageNum;
                Trading.calPageNum = function(cat) {
                    var res = _origTradingCalPageNum(cat);
                    return (isNaN(res) || res <= 0) ? 1 : res;
                };
            }

            // 8. Guard CITY_JOP_REQ and Quest.prizeArrayToString (fixes jop_for vs jopFor)
            if (window.CITY_JOP_REQ) {
                window.CITY_JOP_REQ.FOOD = window.CITY_JOP_REQ.FARM;
                window.CITY_JOP_REQ.METAL = window.CITY_JOP_REQ.MINE;
            }
            if (window.Quest && Quest.prizeArrayToString) {
                var _origPrizeArrayToString = Quest.prizeArrayToString;
                Quest.prizeArrayToString = function(questPrize) {
                    if (Array.isArray(questPrize)) {
                        questPrize.forEach(function(item) {
                            if (item && item.type === 'jop') {
                                if (!item.jop_for) item.jop_for = item.jopFor || 'farm';
                                var k = String(item.jop_for).toUpperCase();
                                if (!window.CITY_JOP_REQ[k]) {
                                    if (k === 'FOOD') window.CITY_JOP_REQ.FOOD = window.CITY_JOP_REQ.FARM;
                                    else if (k === 'METAL') window.CITY_JOP_REQ.METAL = window.CITY_JOP_REQ.MINE;
                                    else window.CITY_JOP_REQ[k] = { ar_title: 'وظائف' };
                                }
                            }
                        });
                    }
                    try {
                        return _origPrizeArrayToString.apply(this, arguments);
                    } catch(e) {
                        console.warn('[prizeArrayToString caught]:', e);
                        return 'مكافأة المهمة';
                    }
                };
            }

            // 9. Guard Building.calResource and Building.isUpgradable (prevents lvl_req crash)
            if (window.Building) {
                if (Building.calResource) {
                    ['food', 'wood', 'stone', 'metal'].forEach(function(r) {
                        Building.calResource[r] = function(type, lvl) {
                            if (!window.BUILDING_JSON_DATA || !BUILDING_JSON_DATA[type] || !BUILDING_JSON_DATA[type]["lvl_req"] || !BUILDING_JSON_DATA[type]["lvl_req"][lvl]) {
                                return 0;
                            }
                            return lvl < 30 ? (BUILDING_JSON_DATA[type]["lvl_req"][lvl][r] || 0) : "--";
                        };
                    });
                }
                if (Building.isUpgradable) {
                    var _origIsUpgradable = Building.isUpgradable;
                    Building.isUpgradable = function(type, lvl) {
                        type = parseInt(type);
                        if (isNaN(type) || type === 0 || type === 19 || type === 20) return false;
                        if (!window.BUILDING_JSON_DATA || !BUILDING_JSON_DATA[type] || !BUILDING_JSON_DATA[type]["lvl_req"]) {
                            return false;
                        }
                        try {
                            return _origIsUpgradable.apply(this, arguments);
                        } catch(e) {
                            console.warn('[isUpgradable caught]:', e);
                            return false;
                        }
                    };
                }
            }
        })();
        </script>
        
      </body>
</html>

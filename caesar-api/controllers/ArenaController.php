<?php
// ArenaController.php - Arena profile and ranking endpoints

class ArenaController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/AArenaChallange/getArenaData':
                self::getArenaData($db);
                return true;
            case '/api/AArenaChallange/getFightList':
                self::getFightList($db);
                return true;
            case '/api/AArenaChallange/getRankList':
                self::getRankList($db);
                return true;
            case '/api/AArenaChallange/fightSomeOne':
            case '/api/AArena/fightSomeOne':
            case '/api/AArena/startDuel':
                self::fightSomeOne($db);
                return true;
            case '/api/AArenaChallange/buyBattelAttempt':
            case '/api/AArenaChallange/addAttByBox':
                self::addAttempt($db);
                return true;
            case '/api/AArenaChallange/addExpByBox':
                self::addExp($db);
                return true;
            case '/api/AArenaChallange/saveHeroList':
            case '/api/AArenaChallange/speedUpAtte':
                self::getPlayer($db);
                self::notImplemented();
                return true;
            case '/api/AArenaChallange/fightSomeGuild':
            case '/api/AArenaChallange/fightSomeTeam':
                self::getPlayer($db);
                self::notImplemented();
                return true;
        }
        return false;
    }

    private static function getPlayer($db) {
        return requireAuthenticatedPlayer($db);
    }

    public static function getArenaData($db) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            sendJson(['state' => 'error_method_not_allowed']);
        }
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];

        // Get or init arena record
        $aStmt = $db->prepare("SELECT * FROM arena_player WHERE id_player = ?");
        $aStmt->execute([$playerId]);
        $arenaData = $aStmt->fetch();
        if (!$arenaData) {
            $db->prepare("INSERT INTO arena_player (id_player, rank, points, attempts, wins, losses) VALUES (?, 1, 1000, 10, 0, 0)")
               ->execute([$playerId]);
            $arenaData = ['rank' => 1, 'attempts' => 10, 'wins' => 0, 'losses' => 0, 'points' => 1000];
        }

        $hStmt = $db->prepare(
            "SELECT h.id_hero, h.name AS HeroName, p.name AS PlayerName,
                    h.lvl, h.avatar, h.ultra_p, h.power
             FROM city_hero h
             JOIN player p ON p.id_player = h.id_player
             WHERE h.id_player = ?
             ORDER BY h.ord ASC, h.id_hero ASC
             LIMIT 5"
        );
        $hStmt->execute([$playerId]);
        $heroes = $hStmt->fetchAll() ?: [];
        foreach ($heroes as &$h) {
            foreach ($h as $k => $v) { if (is_numeric($v)) $h[$k] = (int)$v; }
        }

        $res = [
            'King' => [
                'Arena' => [
                    'id_player'      => $playerId,
                    'lvl'            => 1,
                    'exp'            => (int)($arenaData['points'] ?? 0),
                    'win'            => (int)($arenaData['wins'] ?? 0),
                    'lose'           => (int)($arenaData['losses'] ?? 0),
                    'champion'       => 0,
                    'attempt'        => (int)($arenaData['attempts'] ?? 10),
                    'lastAttackTime' => time(),
                    'rank'           => (int)($arenaData['rank'] ?? 1),
                    'ArenaName'      => $player['name']
                ],
                'HeroList' => $heroes
            ],
            'Team'  => ['Arena' => [], 'HeroList' => []],
            'Guild' => ['Arena' => [], 'HeroList' => []]
        ];
        sendJson($res);
    }

    public static function getFightList($db) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            sendJson(['state' => 'error_method_not_allowed']);
        }
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $stmt = $db->prepare("
            SELECT
                p.id_player AS idPlayer,
                p.name AS PlayerName,
                p.id_guild AS idGuild,
                g.name AS GuildName,
                g.slog_top,
                g.slog_cnt,
                g.slog_btm,
                p.porm,
                ap.rank,
                1 AS arenaLvl
            FROM arena_player ap
            JOIN player p ON p.id_player = ap.id_player
            LEFT JOIN guild g ON g.id_guild = p.id_guild
            WHERE ap.rank > 0
              AND ap.id_player <> ?
              AND ap.rank <= (
                  SELECT own.rank
                  FROM arena_player own
                  WHERE own.id_player = ?
                  LIMIT 1
              )
            ORDER BY ap.rank DESC, ap.points DESC, p.id_player ASC
            LIMIT 10
        ");
        $stmt->execute([$playerId, $playerId]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($players as &$row) {
            foreach ($row as $key => $value) {
                if ($value !== null && is_numeric($value)) {
                    $row[$key] = (int)$value;
                }
            }
        }
        unset($row);

        sendJson([
            'King' => $players,
            'Team' => [],
            'Guild' => []
        ]);
    }

    public static function getRankList($db) {
        $stmt = $db->query("SELECT id_player, name, prestige, avatar FROM player ORDER BY prestige DESC LIMIT 20");
        $rows = $stmt->fetchAll() ?: [];
        $rank = 1;
        foreach ($rows as &$r) {
            $r['rank'] = $rank++;
            $r['id_player'] = (int)$r['id_player'];
            $r['prestige']  = (int)$r['prestige'];
            $r['avatar']    = (int)$r['avatar'];
            $r['lvl']       = 1;
            $r['win']       = 10;
        }
        sendJson($rows);
    }

    public static function fightSomeOne($db) {
        self::getPlayer($db);
        self::notImplemented();
    }

    public static function addAttempt($db) {
        self::getPlayer($db);
        self::notImplemented();
    }

    public static function addExp($db) {
        self::getPlayer($db);
        self::notImplemented();
    }

    public static function saveHeroList($db) {
        self::getPlayer($db);
        self::notImplemented();
    }

    private static function notImplemented() {
        http_response_code(501);
        sendJson(['state' => 'error_not_implemented']);
    }
}

<?php
// RankingController.php - Handles leaderboards and rankings for players, guilds, cities, and heroes

class RankingController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/ARankingPlayer/generalRank':
                self::playerRank($db);
                return true;
            case '/api/ARankingGuild/generalRank':
                sendJson([]);
                return true;
            case '/api/ARankingCity/generalRank':
                self::cityRank($db);
                return true;
            case '/api/ARankingHero/generalRank':
                self::heroRank($db);
                return true;
        }
        return false;
    }

    public static function playerRank($db) {
        $stmt = $db->query("SELECT id_player, name as p_name, guild, prestige, honor FROM player ORDER BY prestige DESC LIMIT 10");
        $ranks = $stmt->fetchAll() ?: [];
        foreach ($ranks as &$r) {
            $r['id_player'] = (int)$r['id_player'];
            $r['prestige']  = (int)$r['prestige'];
            $r['honor']     = (int)$r['honor'];
            $r['guild']     = $r['guild'] ?: 'بدون تحالف';
        }
        sendJson($ranks);
    }

    public static function cityRank($db) {
        $stmt = $db->query("SELECT c.id_city, c.name as CityName, c.lvl, p.name as PlayerName FROM city c JOIN player p ON c.id_player = p.id_player ORDER BY c.lvl DESC LIMIT 10");
        $ranks = $stmt->fetchAll() ?: [];
        foreach ($ranks as &$r) {
            $r['id_city'] = (int)$r['id_city'];
            $r['lvl']     = (int)$r['lvl'];
        }
        sendJson($ranks);
    }

    public static function heroRank($db) {
        $stmt = $db->query("SELECT h.id_hero, h.name as HeroName, h.power, p.name as PlayerName FROM city_hero h JOIN player p ON h.id_player = p.id_player ORDER BY h.power DESC LIMIT 10");
        $ranks = $stmt->fetchAll() ?: [];
        foreach ($ranks as &$r) {
            $r['id_hero'] = (int)$r['id_hero'];
            $r['power']   = (int)$r['power'];
        }
        sendJson($ranks);
    }
}

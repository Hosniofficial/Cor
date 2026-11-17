<?php
// RankingController.php - Handles leaderboards and rankings for players, guilds, cities, and heroes

class RankingController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/ARankingPlayer/generalRank':
                self::playerRank($db);
                return true;
            case '/api/ARankingGuild/generalRank':
                self::guildRank($db);
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
        $offset = max(0, (int)($_REQUEST['offset'] ?? 0));
        $stmt = $db->prepare("SELECT 
                p.id_player, p.name, p.avatar, p.prestige, p.honor, p.porm, p.id_guild, p.guild,
                COALESCE(g.slog_top, '1') as slog_top,
                COALESCE(g.slog_cnt, '1') as slog_cnt,
                COALESCE(g.slog_btm, '1') as slog_btm
            FROM player p
            LEFT JOIN guild g ON p.id_guild = g.id_guild
            ORDER BY p.prestige DESC, p.honor DESC
            LIMIT 10 OFFSET ?");
        $stmt->execute([$offset]);
        $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($ranks as &$r) {
            $r['id_player'] = (int)$r['id_player'];
            $r['avatar']    = (int)($r['avatar'] ?: 1);
            $r['prestige']  = (int)$r['prestige'];
            $r['honor']     = (int)$r['honor'];
            $r['porm']      = (int)($r['porm'] ?: 0);
            $r['id_guild']  = (int)($r['id_guild'] ?: 0);
            $r['guild']     = $r['guild'] ?: ($r['id_guild'] ? 'تحالف' : '-----');
            $r['slog_top']  = (string)($r['slog_top'] ?: '1');
            $r['slog_cnt']  = (string)($r['slog_cnt'] ?: '1');
            $r['slog_btm']  = (string)($r['slog_btm'] ?: '1');
        }
        sendJson($ranks);
    }

    public static function guildRank($db) {
        $offset = max(0, (int)($_REQUEST['offset'] ?? 0));
        $stmt = $db->prepare("SELECT 
                g.id_guild, g.name as GuildName, g.prestige, g.mem_num, g.lvl,
                COALESCE(g.slog_top, '1') as slog_top,
                COALESCE(g.slog_cnt, '1') as slog_cnt,
                COALESCE(g.slog_btm, '1') as slog_btm,
                COALESCE(p.name, g.leader_name, 'القائد') as lord_name,
                COALESCE(p.avatar, 1) as avatar,
                COALESCE((SELECT SUM(m.honor) FROM player m WHERE m.id_guild = g.id_guild), 0) as honor
            FROM guild g
            LEFT JOIN player p ON g.id_leader = p.id_player
            ORDER BY g.prestige DESC, g.lvl DESC
            LIMIT 10 OFFSET ?");
        $stmt->execute([$offset]);
        $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($ranks as &$r) {
            $r['id_guild'] = (int)$r['id_guild'];
            $r['avatar']   = (int)($r['avatar'] ?: 1);
            $r['prestige'] = (int)$r['prestige'];
            $r['honor']    = (int)$r['honor'];
            $r['mem_num']  = (int)$r['mem_num'];
            $r['slog_top'] = (string)($r['slog_top'] ?: '1');
            $r['slog_cnt'] = (string)($r['slog_cnt'] ?: '1');
            $r['slog_btm'] = (string)($r['slog_btm'] ?: '1');
        }
        sendJson($ranks);
    }

    public static function heroRank($db) {
        $offset = max(0, (int)($_REQUEST['offset'] ?? 0));
        $stmt = $db->prepare("SELECT 
                h.id_hero, h.name, h.avatar as heroAvatar, h.lvl, 
                (h.point_a + h.point_a_plus) as point_a,
                (h.point_b + h.point_b_plus) as point_b,
                (h.point_c + h.point_c_plus) as point_c,
                p.name as lord_name,
                COALESCE(p.avatar, 1) as avatar,
                COALESCE(p.id_guild, 0) as id_guild
            FROM city_hero h 
            JOIN player p ON h.id_player = p.id_player 
            ORDER BY h.lvl DESC, (h.point_a + h.point_b + h.point_c + h.point_a_plus + h.point_b_plus + h.point_c_plus) DESC 
            LIMIT 10 OFFSET ?");
        $stmt->execute([$offset]);
        $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($ranks as &$r) {
            $r['id_hero']    = (int)$r['id_hero'];
            $r['heroAvatar'] = (int)($r['heroAvatar'] ?: 1);
            $r['avatar']     = (int)($r['avatar'] ?: 1);
            $r['lvl']        = (int)$r['lvl'];
            $r['point_a']    = (int)$r['point_a'];
            $r['point_b']    = (int)$r['point_b'];
            $r['point_c']    = (int)$r['point_c'];
            $r['id_guild']   = (int)$r['id_guild'];
        }
        sendJson($ranks);
    }

    public static function cityRank($db) {
        $offset = max(0, (int)($_REQUEST['offset'] ?? 0));
        $stmt = $db->prepare("SELECT 
                c.id_city, c.name, c.lvl, c.pop,
                p.name as lord_name,
                COALESCE(p.avatar, 1) as avatar,
                COALESCE(p.id_guild, 0) as id_guild,
                COALESCE(g.name, p.guild, '-----') as GuildName,
                COALESCE(g.slog_top, '1') as slog_top,
                COALESCE(g.slog_cnt, '1') as slog_cnt,
                COALESCE(g.slog_btm, '1') as slog_btm
            FROM city c 
            JOIN player p ON c.id_player = p.id_player
            LEFT JOIN guild g ON p.id_guild = g.id_guild
            ORDER BY c.lvl DESC, c.pop DESC 
            LIMIT 10 OFFSET ?");
        $stmt->execute([$offset]);
        $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($ranks as &$r) {
            $r['id_city']  = (int)$r['id_city'];
            $r['lvl']      = (int)$r['lvl'];
            $r['pop']      = (int)$r['pop'];
            $r['avatar']   = (int)($r['avatar'] ?: 1);
            $r['id_guild'] = (int)$r['id_guild'];
            $r['slog_top'] = (string)($r['slog_top'] ?: '1');
            $r['slog_cnt'] = (string)($r['slog_cnt'] ?: '1');
            $r['slog_btm'] = (string)($r['slog_btm'] ?: '1');
        }
        sendJson($ranks);
    }
}

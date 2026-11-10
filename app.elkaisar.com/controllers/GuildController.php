<?php
// GuildController.php - Guild management, membership, and invitation endpoints.

class GuildController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/AGuild/getGuildData':
                self::getGuildData($db);
                return true;
            case '/api/APlayer/getPlayerGuildData':
                self::getPlayerGuildData($db);
                return true;
            case '/api/AGuild/create':
                self::createGuild($db);
                return true;
            case '/api/AGuild/quitFromGuild':
                self::quitFromGuild($db);
                return true;
            case '/api/AGuild/disbandGuild':
                self::disbandGuild($db);
                return true;
            case '/api/AGuild/donateRes':
                self::donateRes($db);
                return true;
            case '/api/AGuild/upgradeUsingRes':
            case '/api/AGuild/upgradeUsingItem':
                self::upgradeGuild($db);
                return true;
            case '/api/AGuild/modifyGuildWord':
                self::modifyGuildWord($db);
                return true;
            case '/api/AGuild/changeGuildName':
                self::changeGuildName($db);
                return true;
            case '/api/AGuild/getGuildInvReq':
                self::getGuildInvReq($db);
                return true;
            case '/api/AGuildInvReq/sendGuildRequest':
                self::sendGuildRequest($db);
                return true;
            case '/api/AGuildInvReq/acceptGuildReq':
                self::acceptGuildReq($db);
                return true;
            case '/api/AGuildInvReq/rejectGuildJoinReq':
            case '/api/AGuildInvReq/cancelGuildRequest':
            case '/api/AGuildInvReq/rejectGuildInv':
            case '/api/AGuildInvReq/cancelGuildInv':
                self::rejectGuildReq($db, $uri);
                return true;
            case '/api/AGuildInvReq/sendGuildJoinInv':
                self::sendGuildJoinInv($db);
                return true;
            case '/api/AGuildInvReq/acceptGuildInv':
                self::acceptGuildInv($db);
                return true;
            case '/api/AGuildMember/fireMember':
                self::fireMember($db);
                return true;
            case '/api/AGuildMember/modifyPrizeShare':
                self::modifyPrizeShare($db);
                return true;
            case '/api/AGuildMember/promotMember':
                self::promotMember($db);
                return true;
            case '/api/AGuildMember/removeFromPosition':
                self::removeFromPosition($db);
                return true;
            case '/api/AGuildMember/tradePosition':
                self::tradePosition($db);
                return true;
            case '/api/AGuild/resignFromPosition':
                self::resignFromPosition($db);
                return true;
            case '/api/AGuild/changeGuildRelation':
                self::changeGuildRelation($db);
                return true;
        }
        return false;
    }

    private static function getPlayer($db) {
        return requireAuthenticatedPlayer($db);
    }

    private static function requireGuildMembership($db, array $player): int {
        $idGuild = (int)($player['id_guild'] ?? 0);
        $stmt = $db->prepare(
            'SELECT 1 FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ? LIMIT 1'
        );
        $stmt->execute([$idGuild, (int)$player['id_player']]);
        if ($idGuild <= 0 || !$stmt->fetchColumn()) {
            http_response_code(403);
            sendJson(['state' => 'error_auth']);
        }

        return $idGuild;
    }

    private static function requireGuildRank($db, array $player, int $minimumRank): int {
        $idGuild = self::requireGuildMembership($db, $player);
        $stmt = $db->prepare(
            'SELECT `rank` FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ? LIMIT 1'
        );
        $stmt->execute([$idGuild, (int)$player['id_player']]);
        if ((int)$stmt->fetchColumn() < $minimumRank) {
            http_response_code(403);
            sendJson(['state' => 'error_auth']);
        }

        return $idGuild;
    }

    private static function requireGuildLeader($db, array $player): int {
        return self::requireGuildRank($db, $player, 6);
    }

    private static function requireGuildTargetMember($db, int $idGuild, int $idPlayer): void {
        $stmt = $db->prepare(
            'SELECT 1 FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ? LIMIT 1'
        );
        $stmt->execute([$idGuild, $idPlayer]);
        if ($idPlayer <= 0 || !$stmt->fetchColumn()) {
            http_response_code(403);
            sendJson(['state' => 'error_auth']);
        }
    }

    private static function lockGuildMemberRank(PDO $db, int $idGuild, int $idPlayer): ?int {
        $stmt = $db->prepare(
            'SELECT `rank` FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ? FOR UPDATE'
        );
        $stmt->execute([$idGuild, $idPlayer]);
        $rank = $stmt->fetchColumn();

        return $rank === false ? null : (int)$rank;
    }

    private static function joinGuildFromPending($db, int $idGuild, int $idPlayer, string $requestType, ?int $actorId = null, int $minimumActorRank = 6): void {
        $db->beginTransaction();
        try {
            $guildStmt = $db->prepare('SELECT `name`, `mem_max` FROM `guild` WHERE `id_guild` = ? FOR UPDATE');
            $guildStmt->execute([$idGuild]);
            $guild = $guildStmt->fetch(PDO::FETCH_ASSOC);
            if (!$guild) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            if ($actorId !== null) {
                $rankStmt = $db->prepare('SELECT `rank` FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ? FOR UPDATE');
                $rankStmt->execute([$idGuild, $actorId]);
                if ((int)$rankStmt->fetchColumn() < $minimumActorRank) {
                    $db->rollBack();
                    http_response_code(403);
                    sendJson(['state' => 'error_auth']);
                }
            }

            $playerStmt = $db->prepare('SELECT `id_guild` FROM `player` WHERE `id_player` = ? FOR UPDATE');
            $playerStmt->execute([$idPlayer]);
            $target = $playerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }
            if (!empty($target['id_guild'])) {
                $db->rollBack();
                sendJson(['state' => 'error_0']);
            }

            $memberStmt = $db->prepare('SELECT 1 FROM `guild_member` WHERE `id_player` = ? LIMIT 1');
            $memberStmt->execute([$idPlayer]);
            if ($memberStmt->fetchColumn()) {
                $db->rollBack();
                sendJson(['state' => 'error_0']);
            }

            $pendingStmt = $db->prepare('SELECT 1 FROM `guild_req` WHERE `id_guild` = ? AND `id_player` = ? AND `type` = ? FOR UPDATE');
            $pendingStmt->execute([$idGuild, $idPlayer, $requestType]);
            if (!$pendingStmt->fetchColumn()) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            $countStmt = $db->prepare('SELECT COUNT(*) FROM `guild_member` WHERE `id_guild` = ?');
            $countStmt->execute([$idGuild]);
            if ((int)$countStmt->fetchColumn() >= (int)$guild['mem_max']) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }

            $delete = $db->prepare('DELETE FROM `guild_req` WHERE `id_guild` = ? AND `id_player` = ? AND `type` = ?');
            $delete->execute([$idGuild, $idPlayer, $requestType]);
            if ($delete->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            $db->prepare('INSERT INTO `guild_member` (`id_guild`, `id_player`, `rank`, `prize_share`) VALUES (?, ?, 0, 0)')
               ->execute([$idGuild, $idPlayer]);
            $updatePlayer = $db->prepare('UPDATE `player` SET `guild` = ?, `id_guild` = ? WHERE `id_player` = ? AND (`id_guild` IS NULL OR `id_guild` = 0)');
            $updatePlayer->execute([$guild['name'], $idGuild, $idPlayer]);
            if ($updatePlayer->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_0']);
            }
            $db->prepare('DELETE FROM `guild_req` WHERE `id_player` = ?')
               ->execute([$idPlayer]);
            $db->prepare('UPDATE `guild` SET `mem_num` = `mem_num` + 1 WHERE `id_guild` = ?')
               ->execute([$idGuild]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        sendJson(['state' => 'ok']);
    }

    private static function formatGuildData($guild, $leaderName, $rankG = 1) {
        return [
            'id_guild'          => (int)$guild['id_guild'],
            'name'              => $guild['name'],
            'id_leader'         => (int)$guild['id_leader'],
            'leader_name'       => $leaderName,
            'p_name'            => $leaderName,
            'leaderName'        => $leaderName,
            'rank_g'            => (int)$rankG,
            'lvl'               => (int)$guild['lvl'],
            'prestige'          => (int)$guild['prestige'],
            'honor'             => (int)($guild['honor'] ?? 0),
            'mem_num'           => (int)$guild['mem_num'],
            'mem_max'           => (int)($guild['mem_max'] ?? ($guild['lvl'] * 10)),
            'slog_top'          => preg_replace('/\D/', '', (string)$guild['slog_top']) ?: '1',
            'slog_cnt'          => preg_replace('/\D/', '', (string)$guild['slog_cnt']) ?: '1',
            'slog_btm'          => preg_replace('/\D/', '', (string)$guild['slog_btm']) ?: '1',
            'word'              => $guild['word'] ?: 'مرحباً بكم في حلفنا',
            'food'              => (int)($guild['food'] ?? 0),
            'wood'              => (int)($guild['wood'] ?? 0),
            'stone'             => (int)($guild['stone'] ?? 0),
            'metal'             => (int)($guild['metal'] ?? 0),
            'coin'              => (int)($guild['coin'] ?? 0),
            'total_prize_share' => (int)($guild['total_prize_share'] ?? 10),
        ];
    }

    public static function getPlayerGuildData($db) {
        $player = self::getPlayer($db);
        if (!$player || empty($player['id_guild'])) {
            sendJson(['GuildData' => null]);
            return;
        }
        $idGuild = (int)$player['id_guild'];
        $stmt = $db->prepare("SELECT g.id_guild, g.name AS guild_name, g.name, gm.rank, gm.prize_share FROM guild g JOIN guild_member gm ON g.id_guild = gm.id_guild WHERE gm.id_player = ?");
        $stmt->execute([(int)$player['id_player']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['id_guild']    = (int)$row['id_guild'];
            $row['rank']        = (int)$row['rank'];
            $row['prize_share'] = (int)$row['prize_share'];
            $row['name']        = $row['guild_name'];
            sendJson(['GuildData' => $row]);
            return;
        }
        sendJson(['GuildData' => null]);
    }

    public static function getGuildData($db) {
        $player = self::getPlayer($db);
        if (!$player || empty($player['id_guild'])) {
            sendJson(['state' => 'noGuild']);
            return;
        }
        $idGuild = (int)$player['id_guild'];
        $gStmt = $db->prepare("SELECT * FROM guild WHERE id_guild = ?");
        $gStmt->execute([$idGuild]);
        $guild = $gStmt->fetch(PDO::FETCH_ASSOC);
        if (!$guild) {
            sendJson(['state' => 'noGuild']);
            return;
        }

        // Leader name
        $lStmt = $db->prepare("SELECT name FROM player WHERE id_player = ? LIMIT 1");
        $lStmt->execute([(int)$guild['id_leader']]);
        $leaderName = $lStmt->fetchColumn() ?: ($guild['leader_name'] ?? 'المدير');

        // Guild rank
        $rStmt = $db->prepare("SELECT COUNT(*) + 1 FROM guild WHERE prestige > ?");
        $rStmt->execute([(int)$guild['prestige']]);
        $rankG = (int)$rStmt->fetchColumn();

        $formattedGuild = self::formatGuildData($guild, $leaderName, $rankG);

        // Player prize share in guild
        $psStmt = $db->prepare("SELECT prize_share FROM guild_member WHERE id_guild = ? AND id_player = ?");
        $psStmt->execute([$idGuild, (int)$player['id_player']]);
        $prizeShare = (int)($psStmt->fetchColumn() ?: 10);

        // Allays
        $allayStmt = $db->prepare("
            SELECT ga.state, g.id_guild, g.name 
            FROM guild_allay ga 
            JOIN guild g ON (ga.id_guild_1 = ? AND ga.id_guild_2 = g.id_guild) OR (ga.id_guild_2 = ? AND ga.id_guild_1 = g.id_guild)
        ");
        $allayStmt->execute([$idGuild, $idGuild]);
        $allays = $allayStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($allays as &$a) {
            $a['id_guild'] = (int)$a['id_guild'];
            $a['state']    = (int)$a['state'];
        }

        // Members
        $mStmt = $db->prepare("
            SELECT p.id_player, p.name, p.prestige, p.avatar, p.porm, p.online, p.last_seen, gm.rank, gm.prize_share 
            FROM guild_member gm 
            JOIN player p ON gm.id_player = p.id_player 
            WHERE gm.id_guild = ? 
            ORDER BY gm.rank DESC, p.prestige DESC
        ");
        $mStmt->execute([$idGuild]);
        $members = $mStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($members as &$m) {
            foreach ($m as $k => $v) {
                if (is_numeric($v)) $m[$k] = (int)$v;
            }
        }

        $guildPayload = [
            'GuildData'   => $formattedGuild,
            'leaderName'  => $leaderName,
            'Allay'       => $allays,
            'prizeShare'  => $prizeShare,
            'GuildMember' => $members
        ];

        sendJson([
            'state'     => 'ok',
            'Guild'     => $guildPayload,
            'GuildData' => $guildPayload
        ]);
    }

    public static function createGuild($db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];

        $guildName = isset($_REQUEST['guildName']) ? trim($_REQUEST['guildName']) : '';
        $slogBottom = preg_replace('/\D/', '', (string)($_REQUEST['slogBottom'] ?? '1')) ?: '1';
        $slogTop    = preg_replace('/\D/', '', (string)($_REQUEST['slogTop'] ?? '1')) ?: '1';
        $slogMiddle = preg_replace('/\D/', '', (string)($_REQUEST['slogMiddle'] ?? '1')) ?: '1';

        if (!$guildName) {
            sendJson(['state' => 'error_0']);
            return;
        }

        $db->beginTransaction();
        try {
            $playerStmt = $db->prepare('SELECT `name`, `prestige`, `id_guild` FROM `player` WHERE `id_player` = ? FOR UPDATE');
            $playerStmt->execute([$playerId]);
            $currentPlayer = $playerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$currentPlayer || !empty($currentPlayer['id_guild'])) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
            }

            $memberStmt = $db->prepare('SELECT 1 FROM `guild_member` WHERE `id_player` = ? LIMIT 1');
            $memberStmt->execute([$playerId]);
            if ($memberStmt->fetchColumn()) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
            }

            $stmt = $db->prepare('SELECT COUNT(*) FROM `guild` WHERE `name` = ?');
            $stmt->execute([$guildName]);
            if ((int)$stmt->fetchColumn() > 0) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }

            $ins = $db->prepare("INSERT INTO `guild` (`name`, `id_leader`, `leader_name`, `lvl`, `prestige`, `honor`, `mem_num`, `mem_max`, `slog_top`, `slog_cnt`, `slog_btm`, `word`, `total_prize_share`) VALUES (?, ?, ?, 1, ?, 0, 1, 50, ?, ?, ?, 'مرحباً بكم في حلفنا', 10)");
            $ins->execute([$guildName, $playerId, $currentPlayer['name'], (int)$currentPlayer['prestige'], $slogTop, $slogMiddle, $slogBottom]);
            $idGuild = (int)$db->lastInsertId();

            $db->prepare('INSERT INTO `guild_member` (`id_guild`, `id_player`, `rank`, `prize_share`) VALUES (?, ?, 6, 10)')
               ->execute([$idGuild, $playerId]);
            $updatePlayer = $db->prepare('UPDATE `player` SET `guild` = ?, `id_guild` = ? WHERE `id_player` = ? AND (`id_guild` IS NULL OR `id_guild` = 0)');
            $updatePlayer->execute([$guildName, $idGuild, $playerId]);
            if ($updatePlayer->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        $guildStmt = $db->prepare('SELECT * FROM `guild` WHERE `id_guild` = ?');
        $guildStmt->execute([$idGuild]);
        $guild = $guildStmt->fetch(PDO::FETCH_ASSOC);
        $formatted = self::formatGuildData($guild, $player['name'], 1);

        $payload = [
            'GuildData'  => $formatted,
            'leaderName' => $player['name'],
            'Allay'      => [],
            'prizeShare' => 10
        ];

        sendJson([
            'state'     => 'ok',
            'Guild'     => $payload,
            'GuildData' => $payload
        ]);
    }

    public static function quitFromGuild($db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $idGuild = self::requireGuildMembership($db, $player);

        $db->beginTransaction();
        try {
            $guildStmt = $db->prepare('SELECT `id_guild` FROM `guild` WHERE `id_guild` = ? FOR UPDATE');
            $guildStmt->execute([$idGuild]);
            if (!$guildStmt->fetchColumn()) {
                $db->rollBack();
                sendJson(['state' => 'error_auth']);
            }

            $rankStmt = $db->prepare('SELECT `rank`, `prize_share` FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ? FOR UPDATE');
            $rankStmt->execute([$idGuild, $playerId]);
            $member = $rankStmt->fetch(PDO::FETCH_ASSOC);
            if (!$member || (int)$member['rank'] !== 0) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }

            $playerStmt = $db->prepare('SELECT `id_guild` FROM `player` WHERE `id_player` = ? FOR UPDATE');
            $playerStmt->execute([$playerId]);
            if ((int)$playerStmt->fetchColumn() !== $idGuild) {
                $db->rollBack();
                sendJson(['state' => 'error_auth']);
            }

            $delete = $db->prepare('DELETE FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ?');
            $delete->execute([$idGuild, $playerId]);
            if ($delete->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_auth']);
            }
            $updatePlayer = $db->prepare('UPDATE `player` SET `guild` = NULL, `id_guild` = NULL WHERE `id_player` = ? AND `id_guild` = ?');
            $updatePlayer->execute([$playerId, $idGuild]);
            if ($updatePlayer->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_auth']);
            }
            $db->prepare('UPDATE `guild` SET `mem_num` = GREATEST(0, `mem_num` - 1) WHERE `id_guild` = ?')
               ->execute([$idGuild]);
            $shareStmt = $db->prepare('SELECT COALESCE(SUM(`prize_share`), 0) FROM `guild_member` WHERE `id_guild` = ?');
            $shareStmt->execute([$idGuild]);
            $db->prepare('UPDATE `guild` SET `total_prize_share` = ? WHERE `id_guild` = ?')
               ->execute([(int)$shareStmt->fetchColumn(), $idGuild]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
        sendJson(['state' => 'ok']);
    }

    public static function disbandGuild($db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $idGuild = self::requireGuildLeader($db, $player);

        $db->beginTransaction();
        try {
            $guildStmt = $db->prepare('SELECT `id_leader` FROM `guild` WHERE `id_guild` = ? FOR UPDATE');
            $guildStmt->execute([$idGuild]);
            if ((int)$guildStmt->fetchColumn() !== $playerId) {
                $db->rollBack();
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }

            $db->prepare('UPDATE `player` SET `guild` = NULL, `id_guild` = NULL WHERE `id_guild` = ?')
               ->execute([$idGuild]);
            $db->prepare('DELETE FROM `guild_req` WHERE `id_guild` = ?')->execute([$idGuild]);
            $db->prepare('DELETE FROM `guild_member` WHERE `id_guild` = ?')->execute([$idGuild]);
            $db->prepare('DELETE FROM `guild_allay` WHERE `id_guild_1` = ? OR `id_guild_2` = ?')->execute([$idGuild, $idGuild]);
            $db->prepare('DELETE FROM `guild` WHERE `id_guild` = ?')->execute([$idGuild]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
        sendJson(['state' => 'ok']);
    }

    public static function donateRes($db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $idGuild = self::requireGuildMembership($db, $player);
        $idCity  = (int)($_REQUEST['idCity'] ?? 0);

        $food  = max(0, (int)($_REQUEST['food'] ?? 0));
        $wood  = max(0, (int)($_REQUEST['wood'] ?? 0));
        $stone = max(0, (int)($_REQUEST['stone'] ?? 0));
        $metal = max(0, (int)($_REQUEST['metal'] ?? 0));
        $coin  = max(0, (int)($_REQUEST['coin'] ?? 0));

        $city = requirePlayerOwnedCity($db, $player, $idCity);
        if ($food + $wood + $stone + $metal + $coin > 0) {
            $db->beginTransaction();
            $deduct = $db->prepare(
                'UPDATE `city` SET `food` = `food` - ?, `wood` = `wood` - ?, `stone` = `stone` - ?, `metal` = `metal` - ?, `coin` = `coin` - ?'
                . ' WHERE `id_city` = ? AND `id_player` = ? AND `food` >= ? AND `wood` >= ? AND `stone` >= ? AND `metal` >= ? AND `coin` >= ?'
            );
            $deduct->execute([
                $food, $wood, $stone, $metal, $coin, $idCity, $playerId,
                $food, $wood, $stone, $metal, $coin
            ]);
            if ($deduct->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_not_enough_resources']);
            }

            $db->prepare('UPDATE `guild` SET `food` = `food` + ?, `wood` = `wood` + ?, `stone` = `stone` + ?, `metal` = `metal` + ?, `coin` = `coin` + ? WHERE `id_guild` = ?')
               ->execute([$food, $wood, $stone, $metal, $coin, $idGuild]);
            $db->commit();
            $cStmt = $db->prepare('SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ?');
            $cStmt->execute([$idCity, $playerId]);
            $city = $cStmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($city) {
            foreach ($city as $k => $v) {
                if (is_numeric($v)) $city[$k] = (int)$v;
            }
        }

        $guild = $db->query("SELECT * FROM guild WHERE id_guild = $idGuild")->fetch(PDO::FETCH_ASSOC);
        $formatted = self::formatGuildData($guild, $player['name'], 1);

        $payload = [
            'GuildData'  => $formatted,
            'leaderName' => $player['name'],
            'Allay'      => [],
            'prizeShare' => 10
        ];

        sendJson([
            'state'     => 'ok',
            'CityRes'   => $city,
            'Guild'     => $payload,
            'GuildData' => $payload
        ]);
    }

    public static function upgradeGuild($db) {
        $player = self::getPlayer($db);
        self::requireGuildLeader($db, $player);
        http_response_code(501);
        sendJson(['state' => 'error_not_implemented']);
    }

    public static function modifyGuildWord($db) {
        $player = self::getPlayer($db);
        $idGuild = self::requireGuildLeader($db, $player);
        $word = isset($_REQUEST['newWord']) ? trim($_REQUEST['newWord']) : (isset($_REQUEST['word']) ? trim($_REQUEST['word']) : '');
        if ($idGuild > 0 && $word) {
            $db->prepare("UPDATE guild SET word = ? WHERE id_guild = ?")->execute([$word, $idGuild]);
        }
        $guild = $db->query("SELECT * FROM guild WHERE id_guild = $idGuild")->fetch(PDO::FETCH_ASSOC);
        $formatted = self::formatGuildData($guild, $player['name'], 1);

        $payload = [
            'GuildData'  => $formatted,
            'leaderName' => $player['name'],
            'Allay'      => [],
            'prizeShare' => 10
        ];

        sendJson([
            'state'     => 'ok',
            'Guild'     => $payload,
            'GuildData' => $payload
        ]);
    }

    public static function changeGuildName($db) {
        $player = self::getPlayer($db);
        $idGuild = self::requireGuildLeader($db, $player);
        $newName = isset($_REQUEST['newGuildName']) ? trim($_REQUEST['newGuildName']) : (isset($_REQUEST['name']) ? trim($_REQUEST['name']) : '');
        $slogTop = isset($_REQUEST['slog_top']) ? preg_replace('/\D/', '', (string)$_REQUEST['slog_top']) : '';
        $slogCnt = isset($_REQUEST['slog_cnt']) ? preg_replace('/\D/', '', (string)$_REQUEST['slog_cnt']) : '';
        $slogBtm = isset($_REQUEST['slog_btm']) ? preg_replace('/\D/', '', (string)$_REQUEST['slog_btm']) : '';

        if ($idGuild > 0) {
            if ($newName) {
                $db->prepare("UPDATE guild SET name = ? WHERE id_guild = ?")->execute([$newName, $idGuild]);
                $db->prepare("UPDATE player SET guild = ? WHERE id_guild = ?")->execute([$newName, $idGuild]);
            }
            if ($slogTop) $db->prepare("UPDATE guild SET slog_top = ? WHERE id_guild = ?")->execute([$slogTop, $idGuild]);
            if ($slogCnt) $db->prepare("UPDATE guild SET slog_cnt = ? WHERE id_guild = ?")->execute([$slogCnt, $idGuild]);
            if ($slogBtm) $db->prepare("UPDATE guild SET slog_btm = ? WHERE id_guild = ?")->execute([$slogBtm, $idGuild]);
        }
        sendJson(['state' => 'ok']);
    }

    public static function getGuildInvReq($db) {
        $player = self::getPlayer($db);
        $idGuild = (int)($player['id_guild'] ?? 0);

        $reqs = [];
        $invs = [];

        if ($idGuild > 0) {
            self::requireGuildMembership($db, $player);
            $stmt = $db->prepare("SELECT p.id_player, p.name, p.avatar FROM guild_req gr JOIN player p ON gr.id_player = p.id_player WHERE gr.id_guild = ? AND gr.type = 'req'");
            $stmt->execute([$idGuild]);
            $reqs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stmt = $db->prepare("SELECT p.id_player, p.name, p.avatar FROM guild_req gr JOIN player p ON gr.id_player = p.id_player WHERE gr.id_guild = ? AND gr.type = 'inv'");
            $stmt->execute([$idGuild]);
            $invs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        foreach ($reqs as &$r) {
            $r['id_player'] = (int)$r['id_player'];
            $r['avatar']    = (int)$r['avatar'];
        }
        foreach ($invs as &$i) {
            $i['id_player'] = (int)$i['id_player'];
            $i['avatar']    = (int)$i['avatar'];
        }

        sendJson([
            'state'    => 'ok',
            'GuildReq' => $reqs,
            'GuildInv' => $invs
        ]);
    }

    public static function sendGuildRequest($db) {
        $player = self::getPlayer($db);
        $idPlayer = (int)$player['id_player'];
        $idGuild  = (int)($_REQUEST['idGuild'] ?? 0);
        if ($idGuild <= 0 || !empty($player['id_guild'])) {
            sendJson(['state' => !empty($player['id_guild']) ? 'error_0' : 'error_3']);
        }

        $db->beginTransaction();
        try {
            $guildStmt = $db->prepare('SELECT `mem_max` FROM `guild` WHERE `id_guild` = ? FOR UPDATE');
            $guildStmt->execute([$idGuild]);
            $memMax = $guildStmt->fetchColumn();
            if ($memMax === false) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            $playerStmt = $db->prepare('SELECT `id_guild` FROM `player` WHERE `id_player` = ? FOR UPDATE');
            $playerStmt->execute([$idPlayer]);
            $freshPlayer = $playerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$freshPlayer || !empty($freshPlayer['id_guild'])) {
                $db->rollBack();
                sendJson(['state' => 'error_0']);
            }
            $memberStmt = $db->prepare('SELECT 1 FROM `guild_member` WHERE `id_player` = ? LIMIT 1');
            $memberStmt->execute([$idPlayer]);
            if ($memberStmt->fetchColumn()) {
                $db->rollBack();
                sendJson(['state' => 'error_0']);
            }

            $countStmt = $db->prepare('SELECT COUNT(*) FROM `guild_member` WHERE `id_guild` = ?');
            $countStmt->execute([$idGuild]);
            if ((int)$countStmt->fetchColumn() >= (int)$memMax) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }

            $pending = $db->prepare("SELECT `type` FROM `guild_req` WHERE `id_guild` = ? AND `id_player` = ? FOR UPDATE");
            $pending->execute([$idGuild, $idPlayer]);
            $pendingType = $pending->fetchColumn();
            if ($pendingType === false) {
                $db->prepare("INSERT INTO `guild_req` (`id_guild`, `id_player`, `type`, `time`) VALUES (?, ?, 'req', ?)")
                   ->execute([$idGuild, $idPlayer, time()]);
            } elseif ($pendingType !== 'req') {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        sendJson(['state' => 'ok']);
    }

    public static function acceptGuildReq($db) {
        $player = self::getPlayer($db);
        $playerGuild = self::requireGuildRank($db, $player, 4);
        $idGuild  = (int)($_REQUEST['idGuild'] ?? $playerGuild);
        $idPlayer = (int)($_REQUEST['idPlayerToAccept'] ?? $_REQUEST['idPlayer'] ?? 0);
        if ($idGuild !== $playerGuild || $idPlayer <= 0) {
            http_response_code(403);
            sendJson(['state' => 'error_auth']);
        }

        self::joinGuildFromPending($db, $idGuild, $idPlayer, 'req', (int)$player['id_player'], 4);
    }

    public static function rejectGuildReq($db, $uri) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];

        switch ($uri) {
            case '/api/AGuildInvReq/cancelGuildRequest':
                $requestType = 'req';
                $targetPlayerId = $playerId;
                break;
            case '/api/AGuildInvReq/rejectGuildInv':
                $requestType = 'inv';
                $targetPlayerId = $playerId;
                break;
            case '/api/AGuildInvReq/rejectGuildJoinReq':
                $requestType = 'req';
                $targetPlayerId = (int)($_REQUEST['idPlayerToAccept'] ?? $_REQUEST['idPlayer'] ?? 0);
                break;
            case '/api/AGuildInvReq/cancelGuildInv':
                $requestType = 'inv';
                $targetPlayerId = (int)($_REQUEST['idPlayerToInvite'] ?? $_REQUEST['idPlayer'] ?? 0);
                break;
            default:
                http_response_code(404);
                sendJson(['state' => 'error_not_found']);
        }

        if ($targetPlayerId <= 0) {
            sendJson(['state' => 'error_3']);
        }

        $leaderAction = $targetPlayerId !== $playerId;
        $minimumActorRank = $uri === '/api/AGuildInvReq/rejectGuildJoinReq' ? 5 : 4;
        if ($leaderAction) {
            $actorGuildId = self::requireGuildRank($db, $player, $minimumActorRank);
            $idGuild = (int)($_REQUEST['idGuild'] ?? $actorGuildId);
            if ($idGuild !== $actorGuildId) {
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }
        } else {
            if ($uri === '/api/AGuildInvReq/rejectGuildJoinReq' || $uri === '/api/AGuildInvReq/cancelGuildInv') {
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }
            $idGuild = (int)($_REQUEST['idGuild'] ?? 0);
            if ($idGuild <= 0) {
                sendJson(['state' => 'error_3']);
            }
        }

        $db->beginTransaction();
        try {
            if ($leaderAction) {
                $guildStmt = $db->prepare('SELECT `id_guild` FROM `guild` WHERE `id_guild` = ? FOR UPDATE');
                $guildStmt->execute([$idGuild]);
                if (!$guildStmt->fetchColumn()) {
                    $db->rollBack();
                    sendJson(['state' => 'error_1']);
                }
                $rankStmt = $db->prepare('SELECT `rank` FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ? FOR UPDATE');
                $rankStmt->execute([$idGuild, $playerId]);
                if ((int)$rankStmt->fetchColumn() < $minimumActorRank) {
                    $db->rollBack();
                    http_response_code(403);
                    sendJson(['state' => 'error_auth']);
                }
            }

            $delete = $db->prepare('DELETE FROM `guild_req` WHERE `id_guild` = ? AND `id_player` = ? AND `type` = ?');
            $delete->execute([$idGuild, $targetPlayerId, $requestType]);
            if ($delete->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
        sendJson(['state' => 'ok']);
    }

    public static function sendGuildJoinInv($db) {
        $player = self::getPlayer($db);
        $idGuild = self::requireGuildRank($db, $player, 3);
        $targetId = (int)($_REQUEST['idPlayerToInvite'] ?? 0);
        if ($targetId <= 0 || $targetId === (int)$player['id_player']) {
            sendJson(['state' => 'error_3']);
        }

        $db->beginTransaction();
        try {
            $guildStmt = $db->prepare('SELECT `mem_max` FROM `guild` WHERE `id_guild` = ? FOR UPDATE');
            $guildStmt->execute([$idGuild]);
            $memMax = $guildStmt->fetchColumn();
            if ($memMax === false) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }

            $rankStmt = $db->prepare('SELECT `rank` FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ? FOR UPDATE');
            $rankStmt->execute([$idGuild, (int)$player['id_player']]);
            if ((int)$rankStmt->fetchColumn() < 3) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            $targetStmt = $db->prepare('SELECT `id_guild` FROM `player` WHERE `id_player` = ? FOR UPDATE');
            $targetStmt->execute([$targetId]);
            $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
            if (!$target || !empty($target['id_guild'])) {
                $db->rollBack();
                sendJson(['state' => 'error_0']);
            }
            $memberStmt = $db->prepare('SELECT 1 FROM `guild_member` WHERE `id_player` = ? LIMIT 1');
            $memberStmt->execute([$targetId]);
            if ($memberStmt->fetchColumn()) {
                $db->rollBack();
                sendJson(['state' => 'error_0']);
            }

            $countStmt = $db->prepare('SELECT COUNT(*) FROM `guild_member` WHERE `id_guild` = ?');
            $countStmt->execute([$idGuild]);
            if ((int)$countStmt->fetchColumn() >= (int)$memMax) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            $pending = $db->prepare("SELECT `type` FROM `guild_req` WHERE `id_guild` = ? AND `id_player` = ? FOR UPDATE");
            $pending->execute([$idGuild, $targetId]);
            $pendingType = $pending->fetchColumn();
            if ($pendingType === false) {
                $db->prepare("INSERT INTO `guild_req` (`id_guild`, `id_player`, `type`, `time`) VALUES (?, ?, 'inv', ?)")
                   ->execute([$idGuild, $targetId, time()]);
            } elseif ($pendingType !== 'inv') {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        sendJson(['state' => 'ok']);
    }

    public static function acceptGuildInv($db) {
        $player = self::getPlayer($db);
        $idGuild = (int)($_REQUEST['idGuild'] ?? 0);
        if ($idGuild <= 0) {
            sendJson(['state' => 'error_3']);
        }

        self::joinGuildFromPending($db, $idGuild, (int)$player['id_player'], 'inv');
    }

    public static function removeFromPosition($db) {
        $player = self::getPlayer($db);
        $idGuild = self::requireGuildMembership($db, $player);
        $idMember = (int)($_REQUEST['idMember'] ?? 0);
        if ($idMember <= 0 || $idMember === (int)$player['id_player']) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_input']);
        }

        $db->beginTransaction();
        try {
            $db->prepare('SELECT `id_guild` FROM `guild` WHERE `id_guild` = ? FOR UPDATE')
               ->execute([$idGuild]);
            $actorRank = self::lockGuildMemberRank($db, $idGuild, (int)$player['id_player']);
            $targetRank = self::lockGuildMemberRank($db, $idGuild, $idMember);
            if ($actorRank === null || $targetRank === null || $actorRank <= $targetRank) {
                $db->rollBack();
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }
            $db->prepare('UPDATE `guild_member` SET `rank` = 0 WHERE `id_guild` = ? AND `id_player` = ?')
               ->execute([$idGuild, $idMember]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
        sendJson(['state' => 'ok']);
    }

    public static function promotMember($db) {
        $player = self::getPlayer($db);
        $idGuild = self::requireGuildMembership($db, $player);
        $idMember = (int)($_REQUEST['idMember'] ?? 0);
        $rawRank = $_REQUEST['newRank'] ?? null;
        if ($rawRank === null || !is_scalar($rawRank) || !preg_match('/^[1-5]$/', (string)$rawRank) ||
            $idMember <= 0 || $idMember === (int)$player['id_player']) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_input']);
        }
        $newRank = (int)$rawRank;

        $db->beginTransaction();
        try {
            $db->prepare('SELECT `id_guild` FROM `guild` WHERE `id_guild` = ? FOR UPDATE')
               ->execute([$idGuild]);
            $actorRank = self::lockGuildMemberRank($db, $idGuild, (int)$player['id_player']);
            $targetRank = self::lockGuildMemberRank($db, $idGuild, $idMember);
            $rankAllowed = $actorRank === 6
                ? $newRank < 6
                : ($actorRank === 5 && $newRank >= 1 && $newRank <= 4);
            if (!$rankAllowed || $targetRank === null || $targetRank === 6) {
                $db->rollBack();
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }
            $db->prepare('UPDATE `guild_member` SET `rank` = ? WHERE `id_guild` = ? AND `id_player` = ?')
               ->execute([$newRank, $idGuild, $idMember]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
        sendJson(['state' => 'ok']);
    }

    public static function tradePosition($db) {
        $player = self::getPlayer($db);
        $idGuild = self::requireGuildMembership($db, $player);
        $myId = (int)$player['id_player'];
        $targetId = (int)($_REQUEST['idMember'] ?? 0);
        if ($targetId <= 0 || $targetId === $myId) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_input']);
        }

        $db->beginTransaction();
        try {
            $guildStmt = $db->prepare('SELECT `id_leader` FROM `guild` WHERE `id_guild` = ? FOR UPDATE');
            $guildStmt->execute([$idGuild]);
            $idLeader = (int)$guildStmt->fetchColumn();
            $memberIds = [$myId, $targetId];
            sort($memberIds, SORT_NUMERIC);
            $ranks = [];
            foreach ($memberIds as $memberId) {
                $ranks[$memberId] = self::lockGuildMemberRank($db, $idGuild, $memberId);
            }
            $myRank = $ranks[$myId];
            $targetRank = $ranks[$targetId];
            if ($myRank === null || $targetRank === null || $myRank <= $targetRank ||
                ($myRank === 6 && $idLeader !== $myId)) {
                $db->rollBack();
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }

            $db->prepare('UPDATE `guild_member` SET `rank` = ? WHERE `id_guild` = ? AND `id_player` = ?')
               ->execute([$targetRank, $idGuild, $myId]);
            $db->prepare('UPDATE `guild_member` SET `rank` = ? WHERE `id_guild` = ? AND `id_player` = ?')
               ->execute([$myRank, $idGuild, $targetId]);

            if ($myRank === 6) {
                $nameStmt = $db->prepare('SELECT `name` FROM `player` WHERE `id_player` = ?');
                $nameStmt->execute([$targetId]);
                $targetName = $nameStmt->fetchColumn();
                if ($targetName === false) {
                    throw new RuntimeException('Guild leader candidate is unavailable');
                }
                $db->prepare('UPDATE `guild` SET `id_leader` = ?, `leader_name` = ? WHERE `id_guild` = ?')
                   ->execute([$targetId, $targetName, $idGuild]);
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
        sendJson(['state' => 'ok']);
    }

    public static function modifyPrizeShare($db) {
        $player = self::getPlayer($db);
        $idGuild = self::requireGuildMembership($db, $player);
        $idMember = (int)($_REQUEST['idMember'] ?? 0);
        $rawShare = $_REQUEST['newPrizeShare'] ?? null;
        if ($idMember <= 0 || $rawShare === null || !is_scalar($rawShare) ||
            !preg_match('/^\d{1,3}$/', (string)$rawShare) || (int)$rawShare > 100) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_input']);
        }
        $newShare = (int)$rawShare;

        $db->beginTransaction();
        try {
            $guildStmt = $db->prepare('SELECT `id_guild` FROM `guild` WHERE `id_guild` = ? FOR UPDATE');
            $guildStmt->execute([$idGuild]);
            $guildExists = $guildStmt->fetchColumn();
            $actorRank = self::lockGuildMemberRank($db, $idGuild, (int)$player['id_player']);
            $targetRank = self::lockGuildMemberRank($db, $idGuild, $idMember);
            if ($guildExists === false || $actorRank !== 6 || $targetRank === null) {
                $db->rollBack();
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }

            $shareStmt = $db->prepare('SELECT `prize_share` FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ?');
            $shareStmt->execute([$idGuild, $idMember]);
            $oldShare = (int)$shareStmt->fetchColumn();
            $sumStmt = $db->prepare('SELECT COALESCE(SUM(`prize_share`), 0) FROM `guild_member` WHERE `id_guild` = ?');
            $sumStmt->execute([$idGuild]);
            $newTotal = (int)$sumStmt->fetchColumn() - $oldShare + $newShare;
            if ($newTotal < 0 || $newTotal > 100) {
                $db->rollBack();
                sendJson(['state' => 'error_4']);
            }

            $db->prepare('UPDATE `guild_member` SET `prize_share` = ? WHERE `id_guild` = ? AND `id_player` = ?')
               ->execute([$newShare, $idGuild, $idMember]);
            $db->prepare('UPDATE `guild` SET `total_prize_share` = ? WHERE `id_guild` = ?')
               ->execute([$newTotal, $idGuild]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
        sendJson(['state' => 'ok']);
    }

    public static function fireMember($db) {
        $player = self::getPlayer($db);
        $idGuild = self::requireGuildMembership($db, $player);
        $idMember = (int)($_REQUEST['idMember'] ?? 0);
        if ($idMember <= 0 || $idMember === (int)$player['id_player']) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_input']);
        }

        $db->beginTransaction();
        try {
            $guildStmt = $db->prepare('SELECT `mem_num` FROM `guild` WHERE `id_guild` = ? FOR UPDATE');
            $guildStmt->execute([$idGuild]);
            if ($guildStmt->fetchColumn() === false) {
                $db->rollBack();
                sendJson(['state' => 'error_auth']);
            }
            $actorRank = self::lockGuildMemberRank($db, $idGuild, (int)$player['id_player']);
            $targetRank = self::lockGuildMemberRank($db, $idGuild, $idMember);
            if ($actorRank === null || $actorRank < 4 || $targetRank !== 0) {
                $db->rollBack();
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }

            $delete = $db->prepare('DELETE FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ? AND `rank` = 0');
            $delete->execute([$idGuild, $idMember]);
            $clearPlayer = $db->prepare('UPDATE `player` SET `guild` = NULL, `id_guild` = NULL WHERE `id_guild` = ? AND `id_player` = ?');
            $clearPlayer->execute([$idGuild, $idMember]);
            if ($delete->rowCount() !== 1 || $clearPlayer->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_auth']);
            }
            $db->prepare('DELETE FROM `guild_req` WHERE `id_guild` = ? AND `id_player` = ?')
               ->execute([$idGuild, $idMember]);
            $db->prepare('UPDATE `guild` SET `mem_num` = GREATEST(1, `mem_num` - 1) WHERE `id_guild` = ?')
               ->execute([$idGuild]);
            $shareStmt = $db->prepare('SELECT COALESCE(SUM(`prize_share`), 0) FROM `guild_member` WHERE `id_guild` = ?');
            $shareStmt->execute([$idGuild]);
            $db->prepare('UPDATE `guild` SET `total_prize_share` = ? WHERE `id_guild` = ?')
               ->execute([(int)$shareStmt->fetchColumn(), $idGuild]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
        sendJson(['state' => 'ok']);
    }

    public static function resignFromPosition($db) {
        $player = self::getPlayer($db);
        $idPlayer = (int)$player['id_player'];
        $idGuild = self::requireGuildMembership($db, $player);

        $db->beginTransaction();
        try {
            $rankStmt = $db->prepare('SELECT `rank` FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ? FOR UPDATE');
            $rankStmt->execute([$idGuild, $idPlayer]);
            $rank = $rankStmt->fetchColumn();
            if ($rank === false) {
                $db->rollBack();
                sendJson(['state' => 'error_auth']);
            }
            if ((int)$rank === 6) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }
            if ((int)$rank > 0) {
                $db->prepare('UPDATE `guild_member` SET `rank` = 0 WHERE `id_guild` = ? AND `id_player` = ?')
                   ->execute([$idGuild, $idPlayer]);
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
        sendJson(['state' => 'ok']);
    }

    public static function changeGuildRelation($db) {
        $player = self::getPlayer($db);
        $idGuild1 = self::requireGuildRank($db, $player, 5);
        $idGuild2 = (int)($_REQUEST['idGuild'] ?? 0);
        $relation = $_REQUEST['relation'] ?? $_REQUEST['state'] ?? null;
        if ($idGuild2 <= 0 || $idGuild2 === $idGuild1 || !is_scalar($relation) || !in_array((int)$relation, [0, 1, 2], true)) {
            sendJson(['state' => 'error_2']);
        }

        $db->beginTransaction();
        try {
            $guildStmt = $db->prepare('SELECT `id_guild` FROM `guild` WHERE `id_guild` IN (?, ?) ORDER BY `id_guild` FOR UPDATE');
            $guildStmt->execute([$idGuild1, $idGuild2]);
            if (count($guildStmt->fetchAll(PDO::FETCH_COLUMN)) !== 2) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
            }

            $rankStmt = $db->prepare('SELECT `rank` FROM `guild_member` WHERE `id_guild` = ? AND `id_player` = ? FOR UPDATE');
            $rankStmt->execute([$idGuild1, (int)$player['id_player']]);
            if ((int)$rankStmt->fetchColumn() < 5) {
                $db->rollBack();
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }

            $db->prepare("DELETE FROM `guild_allay` WHERE (`id_guild_1` = ? AND `id_guild_2` = ?) OR (`id_guild_1` = ? AND `id_guild_2` = ?)")
               ->execute([$idGuild1, $idGuild2, $idGuild2, $idGuild1]);
            $db->prepare("INSERT INTO `guild_allay` (`id_guild_1`, `id_guild_2`, `state`, `time`) VALUES (?, ?, ?, ?)")
               ->execute([$idGuild1, $idGuild2, (int)$relation, time()]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        sendJson(['state' => 'ok']);
    }
}

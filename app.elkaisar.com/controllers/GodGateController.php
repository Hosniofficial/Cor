<?php
// GodGateController.php - God Gate player data and point handling.

class GodGateController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/AGodGate/getGodGateData':
                self::getGodGateData($db);
                return true;
            case '/api/AGodGate/getRankEffect':
                self::getRankEffect();
                return true;
            case '/api/AGodGate/openGate':
            case '/api/AGodGate/openCell':
            case '/api/AGodGate/changeGateCellState':
            case '/api/AGodGate/changeGateUnlockedCells':
                self::processGateAction($uri, $db);
                return true;
            case '/api/AGodGate/OpenFourthCell':
                self::openFourthCell($db);
                return true;
            case '/api/AGodGate/addGatePoints':
                self::addGatePoints($db);
                return true;
        }
        return false;
    }

    private static function getPlayer($db) {
        return requireAuthenticatedPlayer($db);
    }

    public static function getGodGateData($db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];

        self::ensurePlayerGate($db, $playerId);
        $stmt = $db->prepare('SELECT * FROM `god_gate_player` WHERE `id_player` = ?');
        $stmt->execute([$playerId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $gates = [];
        for ($gate = 1; $gate <= 4; $gate++) {
            $gates[$gate] = null;
            if ((int)$data['gate_' . $gate] > 0) {
                self::ensureGateCells($db, $playerId, $gate);
                $gateStmt = $db->prepare('SELECT * FROM `god_gate` WHERE `id_player` = ? AND `gate` = ?');
                $gateStmt->execute([$playerId, $gate]);
                $gates[$gate] = self::normalizeGate($gateStmt->fetch(PDO::FETCH_ASSOC));
            }
        }

        sendJson([
            'GodGate1' => $gates[1],
            'GodGate2' => $gates[2],
            'GodGate3' => $gates[3],
            'GodGate4' => $gates[4],
            'GodGateData' => [
                'id_player' => $playerId,
                'gate_1'    => (int)$data['gate_1'],
                'gate_2'    => (int)$data['gate_2'] ?: null,
                'gate_3'    => (int)$data['gate_3'] ?: null,
                'gate_4'    => (int)$data['gate_4'] ?: null,
                'points'    => (int)$data['points']
            ]
        ]);
    }

    public static function getRankEffect() {
        sendJson([
            'state'  => 'ok',
            'gate_1' => (object)[],
            'gate_2' => (object)[],
            'gate_3' => (object)[],
            'gate_4' => (object)[]
        ]);
    }

    public static function addGatePoints($db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $item = isset($_REQUEST['Item']) && is_string($_REQUEST['Item'])
            ? trim($_REQUEST['Item'])
            : '';
        $amount = filter_var($_REQUEST['amount'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $pointValues = [
            'god_point_5' => 5,
            'god_point_30' => 30,
            'god_point_75' => 75,
            'god_point_175' => 175,
            'god_point_750' => 750,
            'god_point_1k' => 1000,
            'god_point_2k' => 2000,
            'god_point_5k' => 5000,
            'god_point_10k' => 10000,
            'god_point_50k' => 50000,
        ];

        if (!isset($pointValues[$item])) {
            sendJson(['state' => 'error_invalid_item']);
        }
        if ($amount === false) {
            sendJson(['state' => 'error_invalid_amount']);
        }

        $pointsPerItem = $pointValues[$item];
        $db->beginTransaction();
        try {
            self::ensurePlayerGate($db, $playerId);

            $gateStmt = $db->prepare(
                'SELECT `points` FROM `god_gate_player` WHERE `id_player` = ? FOR UPDATE'
            );
            $gateStmt->execute([$playerId]);
            $currentPoints = (int)$gateStmt->fetchColumn();
            if ($currentPoints < 0 || $currentPoints > 2147483647
                || $amount > intdiv(2147483647 - $currentPoints, $pointsPerItem)) {
                $db->rollBack();
                sendJson(['state' => 'error_points_limit']);
            }

            $pointToAdd = $pointsPerItem * $amount;
            $deduct = $db->prepare(
                'UPDATE `player_item`
                 SET `amount` = `amount` - ?
                 WHERE `id_player` = ? AND `id_item` = ? AND `amount` >= ?'
            );
            $deduct->execute([$amount, $playerId, $item, $amount]);
            if ($deduct->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_no_item']);
            }

            $db->prepare(
                'DELETE FROM `player_item`
                 WHERE `id_player` = ? AND `id_item` = ? AND `amount` <= 0'
            )->execute([$playerId, $item]);
            $db->prepare(
                'UPDATE `god_gate_player` SET `points` = `points` + ? WHERE `id_player` = ?'
            )->execute([$pointToAdd, $playerId]);
            $db->commit();

            sendJson(['state' => 'ok', 'PointToAdd' => $pointToAdd]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function processGateAction($uri, $db) {
        switch ($uri) {
            case '/api/AGodGate/openGate':
                self::openGate($db);
                return;
            case '/api/AGodGate/changeGateCellState':
                self::changeGateCellState($db);
                return;
            case '/api/AGodGate/changeGateUnlockedCells':
                self::changeGateUnlockedCells($db);
                return;
            default:
                http_response_code(501);
                sendJson(['state' => 'error_not_implemented']);
        }
    }

    private static function gateRequirements(): array {
        return [
            1 => ['points' => 500, 'porm' => 4],
            2 => ['points' => 1500, 'porm' => 10],
            3 => ['points' => 2500, 'porm' => 18],
            4 => ['points' => 4000, 'porm' => 28],
        ];
    }

    private static function pointTypes(): array {
        return [
            'attack' => 50,
            'defence' => 50,
            'vit' => 100,
            'damage' => 50,
            'break' => 15,
            'anti_break' => 15,
            'strike' => 15,
            'immunity' => 15,
        ];
    }

    private static function ensurePlayerGate($db, int $playerId): void {
        $db->prepare(
            'INSERT INTO `god_gate_player` (`id_player`, `gate_1`, `gate_2`, `gate_3`, `gate_4`, `points`)
             VALUES (?, 1, 0, 0, 0, 500)
             ON DUPLICATE KEY UPDATE `id_player` = VALUES(`id_player`)'
        )->execute([$playerId]);
    }

    private static function generateGateCells(): array {
        $types = self::pointTypes();
        $availableTypes = array_keys($types);
        shuffle($availableTypes);
        $generated = [];
        for ($cell = 1; $cell <= 4; $cell++) {
            $type = $availableTypes[$cell - 1];
            $generated[$cell] = [
                'type' => $type,
                'score' => random_int(1, $types[$type]),
            ];
        }

        return $generated;
    }

    private static function insertGateCells($db, int $playerId, int $gate): void {
        $cells = self::generateGateCells();
        $values = [$playerId, $gate, 0];
        $columns = ['id_player', 'gate', 'score'];
        $totalScore = 0;
        for ($cell = 1; $cell <= 4; $cell++) {
            $columns[] = 'cell_' . $cell . '_type';
            $columns[] = 'cell_' . $cell . '_score';
            $columns[] = 'c_' . $cell . '_s';
            $values[] = $cells[$cell]['type'];
            $values[] = $cells[$cell]['score'];
            $values[] = $cell === 4 ? 2 : 1;
            if ($cell < 4) {
                $totalScore += $cells[$cell]['score'];
            }
        }
        $values[2] = $totalScore;
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = '`' . implode('`, `', $columns) . '`';
        $db->prepare(
            'INSERT IGNORE INTO `god_gate` (' . $columnList . ') VALUES (' . $placeholders . ')'
        )->execute($values);
    }

    private static function ensureGateCells($db, int $playerId, int $gate): void {
        $stmt = $db->prepare('SELECT 1 FROM `god_gate` WHERE `id_player` = ? AND `gate` = ?');
        $stmt->execute([$playerId, $gate]);
        if (!$stmt->fetchColumn()) {
            self::insertGateCells($db, $playerId, $gate);
        }
    }

    private static function normalizeGate(?array $gate): ?array {
        if (!$gate) {
            return null;
        }
        foreach (['id_player', 'gate', 'score'] as $column) {
            $gate[$column] = (int)$gate[$column];
        }
        for ($cell = 1; $cell <= 4; $cell++) {
            $gate['cell_' . $cell . '_score'] = (int)$gate['cell_' . $cell . '_score'];
            $gate['c_' . $cell . '_s'] = (int)$gate['c_' . $cell . '_s'];
        }
        return $gate;
    }

    private static function getGateIndex($key): int {
        $gate = filter_var($key, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);
        return $gate === false ? 0 : $gate;
    }

    private static function getLockedGate($db, int $playerId, int $gate): array {
        $stmt = $db->prepare(
            'SELECT * FROM `god_gate` WHERE `id_player` = ? AND `gate` = ? FOR UPDATE'
        );
        $stmt->execute([$playerId, $gate]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Opened God Gate is missing its cell data');
        }
        return $row;
    }

    private static function refreshGateScore($db, int $playerId, int $gate, array $row): int {
        $score = 0;
        for ($cell = 1; $cell <= 4; $cell++) {
            if ((int)$row['c_' . $cell . '_s'] !== 2) {
                $score += (int)$row['cell_' . $cell . '_score'];
            }
        }
        $db->prepare(
            'UPDATE `god_gate` SET `score` = ? WHERE `id_player` = ? AND `gate` = ?'
        )->execute([$score, $playerId, $gate]);
        return $score;
    }

    private static function openGate($db): void {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $gate = self::getGateIndex($_REQUEST['gateIndex'] ?? null);
        $requirements = self::gateRequirements();
        if ($gate === 0) {
            sendJson(['state' => 'error_1']);
        }

        $requirement = $requirements[$gate];
        $db->beginTransaction();
        try {
            self::ensurePlayerGate($db, $playerId);
            $stmt = $db->prepare(
                'SELECT * FROM `god_gate_player` WHERE `id_player` = ? FOR UPDATE'
            );
            $stmt->execute([$playerId]);
            $playerGate = $stmt->fetch(PDO::FETCH_ASSOC);
            if ((int)$playerGate['gate_' . $gate] > 0) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }
            if ((int)$player['porm'] < $requirement['porm']) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
            }
            if ((int)$playerGate['points'] < $requirement['points']) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            $db->prepare(
                'UPDATE `god_gate_player`
                 SET `points` = `points` - ?, `gate_' . $gate . '` = 1
                 WHERE `id_player` = ?'
            )->execute([$requirement['points'], $playerId]);
            self::insertGateCells($db, $playerId, $gate);
            $db->commit();

            $playerGate['points'] = (int)$playerGate['points'] - $requirement['points'];
            $playerGate['gate_' . $gate] = 1;
            $gateStmt = $db->prepare('SELECT * FROM `god_gate` WHERE `id_player` = ? AND `gate` = ?');
            $gateStmt->execute([$playerId, $gate]);
            sendJson([
                'state' => 'ok',
                'PlayerGate' => self::normalizePlayerGate($playerGate, $playerId),
                'Gate' => self::normalizeGate($gateStmt->fetch(PDO::FETCH_ASSOC)),
            ]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    private static function normalizePlayerGate(array $row, int $playerId): array {
        $result = ['id_player' => $playerId, 'points' => (int)$row['points']];
        for ($gate = 1; $gate <= 4; $gate++) {
            $result['gate_' . $gate] = (int)$row['gate_' . $gate] ?: null;
        }
        return $result;
    }

    private static function changeGateCellState($db): void {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $gate = self::getGateIndex($_REQUEST['gateIndex'] ?? null);
        $cell = filter_var($_REQUEST['cellIndex'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 4],
        ]);
        $state = filter_var($_REQUEST['state'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 1],
        ]);
        if ($gate === 0 || $cell === false || $state === false) {
            sendJson(['state' => 'error_1']);
        }

        $db->beginTransaction();
        try {
            $playerGate = self::lockPlayerGate($db, $playerId);
            if ((int)$playerGate['gate_' . $gate] <= 0) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
            }
            self::ensureGateCells($db, $playerId, $gate);
            $row = self::getLockedGate($db, $playerId, $gate);
            $column = 'c_' . $cell . '_s';
            if ((int)$row[$column] === 2) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }
            $db->prepare(
                'UPDATE `god_gate` SET `' . $column . '` = ? WHERE `id_player` = ? AND `gate` = ?'
            )->execute([$state, $playerId, $gate]);
            $row[$column] = $state;
            $row['score'] = self::refreshGateScore($db, $playerId, $gate, $row);
            $db->commit();
            sendJson(['state' => 'ok', 'Gate' => self::normalizeGate($row)]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    private static function lockPlayerGate($db, int $playerId): array {
        self::ensurePlayerGate($db, $playerId);
        $stmt = $db->prepare(
            'SELECT * FROM `god_gate_player` WHERE `id_player` = ? FOR UPDATE'
        );
        $stmt->execute([$playerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function changeGateUnlockedCells($db): void {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $gate = self::getGateIndex($_REQUEST['gateIndex'] ?? null);
        if ($gate === 0) {
            sendJson(['state' => 'error_1']);
        }

        $db->beginTransaction();
        try {
            $playerGate = self::lockPlayerGate($db, $playerId);
            if ((int)$playerGate['gate_' . $gate] <= 0) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
            }
            self::ensureGateCells($db, $playerId, $gate);
            $row = self::getLockedGate($db, $playerId, $gate);
            $lockedCells = [];
            $mutableCells = [];
            $fixedTypes = [];
            for ($cell = 1; $cell <= 4; $cell++) {
                $cellState = (int)$row['c_' . $cell . '_s'];
                if ($cellState === 0) {
                    $lockedCells[] = $cell;
                    $fixedTypes[$cell] = $row['cell_' . $cell . '_type'];
                } elseif ($cellState === 1) {
                    $mutableCells[] = $cell;
                } else {
                    $fixedTypes[$cell] = $row['cell_' . $cell . '_type'];
                }
            }
            $cost = 50 + count($lockedCells) * 50;
            if ((int)$playerGate['points'] < $cost) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            $typePool = array_values(array_diff(
                array_keys(self::pointTypes()),
                array_values($fixedTypes)
            ));
            if (count($typePool) < count($mutableCells)) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }
            shuffle($typePool);
            $types = self::pointTypes();
            foreach ($mutableCells as $index => $cell) {
                $type = $typePool[$index];
                $newScore = random_int(1, $types[$type]);
                $db->prepare(
                    'UPDATE `god_gate` SET `cell_' . $cell . '_type` = ?, `cell_' . $cell . '_score` = ?
                     WHERE `id_player` = ? AND `gate` = ?'
                )->execute([$type, $newScore, $playerId, $gate]);
                $row['cell_' . $cell . '_type'] = $type;
                $row['cell_' . $cell . '_score'] = $newScore;
            }

            $db->prepare(
                'UPDATE `god_gate_player` SET `points` = `points` - ? WHERE `id_player` = ?'
            )->execute([$cost, $playerId]);
            $row['score'] = self::refreshGateScore($db, $playerId, $gate, $row);
            $db->commit();
            sendJson(['state' => 'ok', 'Gate' => self::normalizeGate($row)]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    private static function openFourthCell($db): void {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $gate = self::getGateIndex($_REQUEST['GateIndex'] ?? $_REQUEST['gateIndex'] ?? null);
        if ($gate === 0) {
            sendJson(['state' => 'error_0']);
        }

        $db->beginTransaction();
        try {
            $playerGate = self::lockPlayerGate($db, $playerId);
            if ((int)$playerGate['gate_' . $gate] <= 0) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
            }
            self::ensureGateCells($db, $playerId, $gate);
            $row = self::getLockedGate($db, $playerId, $gate);
            if ((int)$row['c_4_s'] !== 2) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }

            $requiredPoints = $gate * 5000;
            $requiredBooks = $gate * 10;
            if ((int)$playerGate['points'] < $requiredPoints) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }
            $deduct = $db->prepare(
                'UPDATE `player_item`
                 SET `amount` = `amount` - ?
                 WHERE `id_player` = ? AND `id_item` = ? AND `amount` >= ?'
            );
            $deduct->execute([$requiredBooks, $playerId, 'skill_book', $requiredBooks]);
            if ($deduct->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_4']);
            }
            $db->prepare(
                'DELETE FROM `player_item`
                 WHERE `id_player` = ? AND `id_item` = ? AND `amount` <= 0'
            )->execute([$playerId, 'skill_book']);
            $db->prepare(
                'UPDATE `god_gate_player` SET `points` = `points` - ? WHERE `id_player` = ?'
            )->execute([$requiredPoints, $playerId]);
            $db->prepare(
                'UPDATE `god_gate` SET `c_4_s` = 1 WHERE `id_player` = ? AND `gate` = ?'
            )->execute([$playerId, $gate]);
            $row['c_4_s'] = 1;
            $row['score'] = self::refreshGateScore($db, $playerId, $gate, $row);
            $db->commit();
            sendJson(['state' => 'ok', 'Gate' => self::normalizeGate($row)]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }
}

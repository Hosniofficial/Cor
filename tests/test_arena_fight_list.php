<?php

$arenaResponse = null;

function sendJson($data): void {
    $GLOBALS['arenaResponse'] = $data;
}

function requireAuthenticatedPlayer(PDO $db): array {
    return ['id_player' => 17];
}

class ArenaFightListTestStatement extends PDOStatement {
    public $sql;
    public $params;

    public function __construct(string $sql) {
        $this->sql = $sql;
    }

    public function execute(?array $params = null): bool {
        $this->params = $params;
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return [[
            'idPlayer' => '16',
            'PlayerName' => 'Dynamic rival',
            'idGuild' => null,
            'GuildName' => null,
            'slog_top' => null,
            'slog_cnt' => null,
            'slog_btm' => null,
            'porm' => '2',
            'rank' => '1',
            'arenaLvl' => '1'
        ]];
    }
}

class ArenaFightListTestDatabase extends PDO {
    public $statement;

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->statement = new ArenaFightListTestStatement($query);
        return $this->statement;
    }
}

require_once __DIR__ . '/../caesar-api/controllers/ArenaController.php';

$db = new ArenaFightListTestDatabase();
ArenaController::getFightList($db);

if ($db->statement->params !== [17, 17]
    || strpos($db->statement->sql, 'JOIN player') === false
    || strpos($db->statement->sql, 'LEFT JOIN guild') === false
    || strpos($db->statement->sql, 'own.id_player = ?') === false
    || strpos($db->statement->sql, 'ap.rank <=') === false
    || strpos($db->statement->sql, 'ap.id_player <> ?') === false
    || strpos($db->statement->sql, 'LIMIT 10') === false) {
    throw new RuntimeException('Arena fight list did not query ranked players for the authenticated player');
}

$expected = [
    'King' => [[
        'idPlayer' => 16,
        'PlayerName' => 'Dynamic rival',
        'idGuild' => null,
        'GuildName' => null,
        'slog_top' => null,
        'slog_cnt' => null,
        'slog_btm' => null,
        'porm' => 2,
        'rank' => 1,
        'arenaLvl' => 1
    ]],
    'Team' => [],
    'Guild' => []
];

if ($arenaResponse !== $expected) {
    throw new RuntimeException('Arena fight list did not return the dynamic player payload');
}

echo "Arena fight list tests passed\n";

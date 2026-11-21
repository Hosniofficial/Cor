<?php

class CrossControllerTransactionResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new CrossControllerTransactionResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/ItemController.php';
require_once __DIR__ . '/../caesar-api/controllers/HeroController.php';
require_once __DIR__ . '/../caesar-api/controllers/PlayerController.php';

function assertCrossController(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "[PASS] {$message}\n";
}

class LetterRefreshTestStatement {
    private $db;
    private $query;
    private $column = false;
    private $affectedRows = 0;

    public function __construct($db, string $query) {
        $this->db = $db;
        $this->query = $query;
    }

    public function execute(array $params = []): bool {
        if (strpos($this->query, 'SELECT `lvl` FROM `city_building`') !== false) {
            $this->column = 6;
        } elseif (strpos($this->query, 'SELECT `id_player` FROM `city`') !== false) {
            $this->column = 1;
        } elseif (strpos($this->query, 'UPDATE `player_item`') !== false) {
            $amount = $params[0];
            if ($this->db->letterAmount >= $amount) {
                $this->db->letterAmount -= $amount;
                $this->affectedRows = 1;
            }
        } elseif (strpos($this->query, 'INSERT INTO `city_theater`') !== false) {
            throw new RuntimeException('theater save failed');
        }
        return true;
    }

    public function fetchColumn($column = 0) {
        return $this->column;
    }

    public function rowCount(): int {
        return $this->affectedRows;
    }
}

class LetterRefreshTestDatabase {
    public $letterAmount = 1;
    private $transactionLetterAmount = 0;
    private $transaction = false;
    public $rolledBack = false;

    public function exec(string $query): int {
        return 0;
    }

    public function prepare(string $query): LetterRefreshTestStatement {
        return new LetterRefreshTestStatement($this, $query);
    }

    public function beginTransaction(): bool {
        $this->transactionLetterAmount = $this->letterAmount;
        $this->transaction = true;
        return true;
    }

    public function commit(): bool {
        $this->transaction = false;
        return true;
    }

    public function inTransaction(): bool {
        return $this->transaction;
    }

    public function rollBack(): bool {
        $this->letterAmount = $this->transactionLetterAmount;
        $this->transaction = false;
        $this->rolledBack = true;
        return true;
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('LEAST', static function ($left, $right) {
    return min($left, $right);
});
$db->exec("
    CREATE TABLE player (
        id_player INTEGER PRIMARY KEY,
        p_token TEXT NOT NULL
    );
    CREATE TABLE city (
        id_city INTEGER PRIMARY KEY,
        id_player INTEGER NOT NULL,
        food INTEGER NOT NULL DEFAULT 0,
        wood INTEGER NOT NULL DEFAULT 0,
        stone INTEGER NOT NULL DEFAULT 0,
        metal INTEGER NOT NULL DEFAULT 0,
        coin INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE city_hero (
        id_hero INTEGER PRIMARY KEY,
        id_city INTEGER NOT NULL,
        id_player INTEGER NOT NULL,
        lvl INTEGER NOT NULL,
        exp INTEGER NOT NULL DEFAULT 0,
        loyal INTEGER NOT NULL DEFAULT 50,
        points INTEGER NOT NULL DEFAULT 0,
        point_a INTEGER NOT NULL DEFAULT 0,
        point_b INTEGER NOT NULL DEFAULT 0,
        point_c INTEGER NOT NULL DEFAULT 0,
        p_b_a INTEGER NOT NULL DEFAULT 0,
        p_b_b INTEGER NOT NULL DEFAULT 0,
        p_b_c INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE player_item (
        id_player INTEGER NOT NULL,
        id_item TEXT NOT NULL,
        amount INTEGER NOT NULL,
        PRIMARY KEY (id_player, id_item)
    );
    CREATE TABLE player_edu (
        id_player INTEGER PRIMARY KEY,
        infantry INTEGER NOT NULL DEFAULT 9
    );
    CREATE TABLE city_building (
        id_building INTEGER PRIMARY KEY,
        id_city INTEGER NOT NULL,
        type INTEGER NOT NULL,
        lvl INTEGER NOT NULL
    );
    CREATE TABLE server_config (
        config_key TEXT,
        category TEXT,
        config_value TEXT,
        value_type TEXT,
        title_ar TEXT,
        description_ar TEXT
    );
    CREATE TABLE timed_task (
        id_task INTEGER PRIMARY KEY AUTOINCREMENT,
        id_city INTEGER NOT NULL,
        id_player INTEGER NOT NULL,
        task_type TEXT NOT NULL,
        item_id TEXT NOT NULL,
        lvl_to INTEGER NOT NULL,
        start_time INTEGER NOT NULL,
        time_arrive INTEGER NOT NULL,
        details TEXT NOT NULL
    );
");
$db->exec("INSERT INTO player VALUES (1, 'cross-controller-token')");
$db->exec("INSERT INTO city (id_city, id_player) VALUES (10, 1)");
$db->exec("
    INSERT INTO city_hero (id_hero, id_city, id_player, lvl, exp, loyal, points,
        point_a, point_b, point_c, p_b_a, p_b_b, p_b_c)
    VALUES (100, 10, 1, 1, 0, 50, 10, 5, 5, 5, 2, 2, 2)
");

$db->exec("INSERT INTO player_item VALUES (1, 'exp_hero_8', 1)");
$db->exec("
    CREATE TRIGGER fail_hero_exp BEFORE UPDATE OF exp ON city_hero
    BEGIN SELECT RAISE(ABORT, 'hero exp update failed'); END
");
$expFailureSurfaced = false;
$_REQUEST = ['idHero' => 100, 'itemToUse' => 'exp_hero_8'];
try {
    HeroController::addExp($db);
} catch (PDOException $error) {
    $expFailureSurfaced = true;
}
assertCrossController($expFailureSurfaced, 'Experience-update failure is surfaced');
assertCrossController(
    (int)$db->query("SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'exp_hero_8'")->fetchColumn() === 1,
    'Experience item is restored when the hero update fails'
);
$db->exec('DROP TRIGGER fail_hero_exp');
try {
    HeroController::addExp($db);
} catch (CrossControllerTransactionResponse $response) {
    assertCrossController(
        ($response->payload['state'] ?? null) === 'ok',
        'Experience item succeeds when the hero update succeeds'
    );
}
assertCrossController(
    (int)$db->query('SELECT exp FROM city_hero WHERE id_hero = 100')->fetchColumn() === 1000
    && (int)$db->query("SELECT COUNT(*) FROM player_item WHERE id_player = 1 AND id_item = 'exp_hero_8'")->fetchColumn() === 0,
    'Successful experience use updates the hero and consumes one item'
);

$db->exec("INSERT INTO player_item VALUES (1, 'luxury_1', 1)");
$db->exec("
    CREATE TRIGGER fail_hero_loyalty BEFORE UPDATE OF loyal ON city_hero
    BEGIN SELECT RAISE(ABORT, 'hero loyalty update failed'); END
");
$loyaltyFailureSurfaced = false;
$_REQUEST = ['idHero' => 100, 'itemToUse' => 'luxury_1'];
try {
    HeroController::addLoy($db);
} catch (PDOException $error) {
    $loyaltyFailureSurfaced = true;
}
assertCrossController($loyaltyFailureSurfaced, 'Loyalty-update failure is surfaced');
assertCrossController(
    (int)$db->query("SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'luxury_1'")->fetchColumn() === 1,
    'Loyalty item is restored when the hero update fails'
);
$db->exec('DROP TRIGGER fail_hero_loyalty');
try {
    HeroController::addLoy($db);
} catch (CrossControllerTransactionResponse $response) {
    assertCrossController(
        ($response->payload['state'] ?? null) === 'ok',
        'Loyalty item succeeds when the hero update succeeds'
    );
}
assertCrossController(
    (int)$db->query('SELECT loyal FROM city_hero WHERE id_hero = 100')->fetchColumn() === 55
    && (int)$db->query("SELECT COUNT(*) FROM player_item WHERE id_player = 1 AND id_item = 'luxury_1'")->fetchColumn() === 0,
    'Successful loyalty use updates the hero and consumes one item'
);

$db->exec("INSERT INTO player_item VALUES (1, 'retreat_point', 1)");
$db->exec("
    CREATE TRIGGER fail_hero_reset BEFORE UPDATE OF point_a ON city_hero
    BEGIN SELECT RAISE(ABORT, 'hero reset failed'); END
");
$resetFailureSurfaced = false;
$_REQUEST = ['idHero' => 100];
try {
    HeroController::resetHeroPoints($db);
} catch (PDOException $error) {
    $resetFailureSurfaced = true;
}
assertCrossController($resetFailureSurfaced, 'Hero-reset failure is surfaced');
assertCrossController(
    (int)$db->query("SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'retreat_point'")->fetchColumn() === 1,
    'Retreat-point item is restored when the hero reset fails'
);
$db->exec('DROP TRIGGER fail_hero_reset');
try {
    HeroController::resetHeroPoints($db);
} catch (CrossControllerTransactionResponse $response) {
    assertCrossController(
        ($response->payload['state'] ?? null) === 'ok',
        'Hero reset succeeds when its database update succeeds'
    );
}
$resetHero = $db->query(
    'SELECT points, point_a, point_b, point_c FROM city_hero WHERE id_hero = 100'
)->fetch(PDO::FETCH_ASSOC);
assertCrossController(
    (int)$resetHero['points'] === 19
    && (int)$resetHero['point_a'] === 2
    && (int)$resetHero['point_b'] === 2
    && (int)$resetHero['point_c'] === 2
    && (int)$db->query("SELECT COUNT(*) FROM player_item WHERE id_player = 1 AND id_item = 'retreat_point'")->fetchColumn() === 0,
    'Successful hero reset returns spent points and consumes its item'
);
$db->exec("INSERT INTO player_item VALUES (1, 'retreat_point', 1)");
$_REQUEST = ['idHero' => 999];
try {
    HeroController::resetHeroPoints($db);
} catch (CrossControllerTransactionResponse $response) {
    assertCrossController(
        ($response->payload['state'] ?? null) === 'error_0',
        'Resetting a missing hero returns an ownership error'
    );
}
assertCrossController(
    (int)$db->query("SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'retreat_point'")->fetchColumn() === 1,
    'Resetting a missing hero does not consume a retreat-point item'
);

$studyReq = PlayerController::getStudyRequirements('infantry', 10);
assertCrossController(is_array($studyReq), 'Study requirements load from the project data');
$db->exec('INSERT INTO city_building VALUES (1, 10, 9, 10)');
$db->exec("INSERT INTO player_edu (id_player, infantry) VALUES (1, 9)");
$db->exec("INSERT INTO player_item VALUES (1, 'law_1', 1)");
$_POST = ['token' => 'cross-controller-token'];
$_REQUEST = ['token' => 'cross-controller-token', 'idCity' => 10, 'idStudy' => 'infantry'];

try {
    PlayerController::upgradeStudyLvl($db);
    throw new RuntimeException('An underfunded study should return an application error');
} catch (CrossControllerTransactionResponse $response) {
    assertCrossController(
        ($response->payload['state'] ?? null) === 'error_res',
        'Underfunded study reports insufficient resources'
    );
}
assertCrossController(
    (int)$db->query("SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'law_1'")->fetchColumn() === 1,
    'Study law item is not consumed when resources are insufficient'
);
assertCrossController(
    (int)$db->query('SELECT COUNT(*) FROM timed_task')->fetchColumn() === 0,
    'Underfunded study does not create a timed task'
);

$wellFunded = [
    'food' => max(1000000000, (int)$studyReq['food']),
    'wood' => max(1000000000, (int)$studyReq['wood']),
    'stone' => max(1000000000, (int)$studyReq['stone']),
    'metal' => max(1000000000, (int)$studyReq['metal']),
    'coin' => max(1000000000, (int)$studyReq['coin']),
];
$db->prepare('UPDATE city SET food = ?, wood = ?, stone = ?, metal = ?, coin = ? WHERE id_city = 10')
    ->execute(array_values($wellFunded));
$db->exec("
    CREATE TRIGGER fail_study_task BEFORE INSERT ON timed_task
    BEGIN SELECT RAISE(ABORT, 'study task insert failed'); END
");
$resourceSnapshot = $db->query(
    'SELECT food, wood, stone, metal, coin FROM city WHERE id_city = 10'
)->fetch(PDO::FETCH_ASSOC);
$taskFailureSurfaced = false;
try {
    PlayerController::upgradeStudyLvl($db);
} catch (PDOException $error) {
    $taskFailureSurfaced = true;
}
assertCrossController($taskFailureSurfaced, 'Study-task insertion failure is surfaced');
assertCrossController(
    (int)$db->query("SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'law_1'")->fetchColumn() === 1,
    'Study law item is restored when task creation fails'
);
assertCrossController(
    $resourceSnapshot === $db->query(
        'SELECT food, wood, stone, metal, coin FROM city WHERE id_city = 10'
    )->fetch(PDO::FETCH_ASSOC),
    'Study resources are restored when task creation fails'
);
assertCrossController(
    (int)$db->query('SELECT COUNT(*) FROM timed_task')->fetchColumn() === 0,
    'Failed study task is not persisted'
);

$db->exec('DROP TRIGGER fail_study_task');
try {
    PlayerController::upgradeStudyLvl($db);
} catch (CrossControllerTransactionResponse $response) {
    assertCrossController(
        ($response->payload['state'] ?? null) === 'ok',
        'Funded study returns success'
    );
}
assertCrossController(
    (int)$db->query("SELECT COUNT(*) FROM player_item WHERE id_player = 1 AND id_item = 'law_1'")->fetchColumn() === 0,
    'Successful study consumes its law item'
);
assertCrossController(
    (int)$db->query('SELECT COUNT(*) FROM timed_task')->fetchColumn() === 1,
    'Successful study creates exactly one timed task'
);

$theaterDb = new LetterRefreshTestDatabase();
$letterFailureSurfaced = false;
$_REQUEST = ['idCity' => 10];
try {
    HeroController::refreshHeroTheater($theaterDb, true);
} catch (RuntimeException $error) {
    $letterFailureSurfaced = $error->getMessage() === 'theater save failed';
}
assertCrossController($letterFailureSurfaced, 'Theater-save failure is surfaced');
assertCrossController($theaterDb->rolledBack, 'Failed theater save rolls back its item transaction');
assertCrossController(
    $theaterDb->letterAmount === 1,
    'Recruitment letter is restored when saving the refreshed theater fails'
);

echo "Cross-controller item transaction tests passed\n";

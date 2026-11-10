<?php

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        header('Content-Type: text/plain; charset=utf-8');
        echo is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function normalizeGameDisplayName($value): ?string {
    if (!is_string($value)) {
        return null;
    }

    $name = trim($value);
    if ($name === '' || preg_match('/\A[\p{L}\p{N}\p{M} ._-]{1,50}\z/u', $name) !== 1) {
        return null;
    }

    return $name;
}

function requireAuthenticatedPlayer(PDO $db): array {
    $token = $_POST['token'] ?? $_GET['token'] ?? $_POST['outhToken'] ?? $_GET['outhToken'] ?? '';
    if (!is_string($token) || $token === '') {
        http_response_code(401);
        sendJson(['state' => 'error_auth']);
    }

    $stmt = $db->prepare('SELECT * FROM `player` WHERE `p_token` = ? LIMIT 1');
    $stmt->execute([$token]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$player) {
        http_response_code(401);
        sendJson(['state' => 'error_auth']);
    }

    return $player;
}

function requirePlayerOwnedCity(PDO $db, array $player, $cityId = null): array {
    if ($cityId === null) {
        $cityId = $_POST['idCity'] ?? $_GET['idCity'] ?? $_POST['id_city'] ?? $_GET['id_city'] ?? 0;
    }

    if (!is_scalar($cityId) || (int)$cityId <= 0) {
        http_response_code(403);
        sendJson(['state' => 'error_auth']);
    }

    $stmt = $db->prepare(
        'SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ? LIMIT 1'
    );
    $stmt->execute([(int)$cityId, (int)$player['id_player']]);
    $city = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$city) {
        http_response_code(403);
        sendJson(['state' => 'error_auth']);
    }

    return $city;
}

function requirePlayerOwnedTask(PDO $db, array $player, $taskId): array {
    if (!is_scalar($taskId) || (int)$taskId <= 0) {
        http_response_code(403);
        sendJson(['state' => 'error_auth']);
    }

    $stmt = $db->prepare(
        'SELECT * FROM `timed_task` WHERE `id_task` = ? AND `id_player` = ? LIMIT 1'
    );
    $stmt->execute([(int)$taskId, (int)$player['id_player']]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        http_response_code(403);
        sendJson(['state' => 'error_auth']);
    }

    return $task;
}

function requirePlayerOwnedHero(PDO $db, array $player, $heroId): array {
    if (!is_scalar($heroId) || (int)$heroId <= 0) {
        http_response_code(403);
        sendJson(['state' => 'error_auth']);
    }

    $stmt = $db->prepare(
        'SELECT * FROM `city_hero` WHERE `id_hero` = ? AND `id_player` = ? LIMIT 1'
    );
    $stmt->execute([(int)$heroId, (int)$player['id_player']]);
    $hero = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hero) {
        http_response_code(403);
        sendJson(['state' => 'error_auth']);
    }

    return $hero;
}

function requirePlayerOwnedEquip(PDO $db, array $player, $equipId): array {
    if (!is_scalar($equipId) || (int)$equipId <= 0) {
        http_response_code(403);
        sendJson(['state' => 'error_auth']);
    }

    $stmt = $db->prepare(
        'SELECT * FROM `player_equip` WHERE `id_equip` = ? AND `id_player` = ? LIMIT 1'
    );
    $stmt->execute([(int)$equipId, (int)$player['id_player']]);
    $equip = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$equip) {
        http_response_code(403);
        sendJson(['state' => 'error_auth']);
    }

    return $equip;
}

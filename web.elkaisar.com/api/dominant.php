<?php
// api/dominant.php - Dominant Army Capitals & Colonizer API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../app.elkaisar.com/db.php';

$capitals = [
    ['title' => 'عاصمة المشاة',   'x' => 235, 'y' => 125, 'type' => 150, 'rank' => []],
    ['title' => 'عاصمة الفرسان',   'x' => 140, 'y' => 170, 'type' => 151, 'rank' => []],
    ['title' => 'عاصمة الرماة',    'x' => 400, 'y' => 230, 'type' => 152, 'rank' => []],
    ['title' => 'عاصمة المقاليع',  'x' => 255, 'y' => 266, 'type' => 153, 'rank' => []],
    ['title' => 'عاصمة المنجنيق',  'x' => 80,  'y' => 280, 'type' => 154, 'rank' => []],
    ['title' => 'عاصمة الدبابات',  'x' => 400, 'y' => 340, 'type' => 155, 'rank' => []],
    ['title' => 'عاصمة التمرد',    'x' => 103, 'y' => 447, 'type' => 105, 'rank' => []],
];

try {
    $db = getDB();
    foreach ($capitals as &$cap) {
        $stmt = $db->prepare("SELECT b.*, p.name as player_name 
                              FROM city_barray b 
                              JOIN player p ON b.id_player = p.id_player 
                              WHERE b.x_coord = ? AND b.y_coord = ? LIMIT 1");
        $stmt->execute([$cap['x'], $cap['y']]);
        $row = $stmt->fetch();
        if ($row && !empty($row['player_name'])) {
            $cap['rank'] = [
                [
                    'name'       => $row['player_name'],
                    'time_stamp' => time() - 3600,
                    'd_sum'      => 3600
                ]
            ];
        }
    }
    unset($cap);
} catch (Exception $e) {}

echo json_encode([
    'state' => 'ok',
    'type'  => 'domain',
    'unit'  => $capitals
], JSON_UNESCAPED_UNICODE);
exit;

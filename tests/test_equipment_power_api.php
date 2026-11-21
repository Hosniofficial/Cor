<?php

class EquipmentPowerResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new EquipmentPowerResponse($data);
}

require_once __DIR__ . '/../caesar-api/controllers/ItemController.php';

function requestEquipmentPower(array $query, string $method = 'GET'): array {
    $_GET = $query;
    $_SERVER['REQUEST_METHOD'] = $method;
    http_response_code(200);
    try {
        ItemController::getEquipmentPower();
    } catch (EquipmentPowerResponse $response) {
        return [
            'status' => http_response_code(),
            'payload' => $response->payload,
        ];
    }
    throw new RuntimeException('Equipment power endpoint did not return a response');
}

$source = json_decode(
    file_get_contents(__DIR__ . '/../caesar-private/data/equipment-power.json'),
    true
);
$expected = null;
foreach ($source as $entry) {
    if ($entry['equip'] === 'loy' && $entry['part'] === 'belt' && (int)$entry['lvl'] === 1) {
        $expected = $entry;
        break;
    }
}
if (!$expected) {
    throw new RuntimeException('Reference equipment power entry is missing');
}

$response = requestEquipmentPower(['equip' => 'loy', 'part' => 'belt', 'lvl' => '1']);
$expectedPower = [];
foreach (['attack', 'defence', 'vitality', 'damage', 'break', 'anti_break', 'strike', 'immunity', 'sp_attr'] as $field) {
    $expectedPower[$field] = (int)$expected[$field];
}
if ($response['status'] !== 200
    || $response['payload'] !== ['state' => 'ok', 'Power' => $expectedPower]) {
    throw new RuntimeException('Endpoint should return only the requested equipment power');
}

$response = requestEquipmentPower(['equip' => '../equipment-power', 'part' => 'belt', 'lvl' => '1']);
if ($response['status'] !== 400 || ($response['payload']['state'] ?? null) !== 'error_invalid_equipment') {
    throw new RuntimeException('Endpoint should reject invalid equipment identifiers');
}

$response = requestEquipmentPower(['equip' => 'loy', 'part' => 'belt', 'lvl' => ['1']]);
if ($response['status'] !== 400 || ($response['payload']['state'] ?? null) !== 'error_invalid_equipment') {
    throw new RuntimeException('Endpoint should reject non-scalar equipment levels');
}

$response = requestEquipmentPower(['equip' => 'unknown', 'part' => 'belt', 'lvl' => '1']);
if ($response['status'] !== 404 || ($response['payload']['state'] ?? null) !== 'error_equipment_not_found') {
    throw new RuntimeException('Endpoint should return not found for unknown equipment');
}

$response = requestEquipmentPower(['equip' => 'loy', 'part' => 'belt', 'lvl' => '1'], 'POST');
if ($response['status'] !== 405 || ($response['payload']['state'] ?? null) !== 'error_method_not_allowed') {
    throw new RuntimeException('Endpoint should allow GET only');
}

echo "Equipment power API checks passed\n";

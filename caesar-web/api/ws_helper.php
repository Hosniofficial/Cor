<?php
// ws_helper.php - Helper to notify WebSocket server of game events

if (!function_exists('notifyWsMail')) {
    function notifyWsMail($playerIds, string $classPath = 'Mail.sentTo'): bool {
        if (!is_array($playerIds)) {
            $playerIds = [(int)$playerIds];
        }
        $playerIds = array_values(array_filter(array_map('intval', $playerIds), fn($id) => $id > 0));
        if (empty($playerIds)) {
            return false;
        }

        $token = getenv('CAESAR_INTERNAL_API_TOKEN') ?: '';
        $headers = ['Content-Type: application/json'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . trim($token);
        }

        $ch = curl_init('http://127.0.0.1:8081/api/internal/notify_mail');
        if ($ch === false) {
            return false;
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'player_ids' => $playerIds,
            'class_path' => $classPath,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 300);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res !== false;
    }
}

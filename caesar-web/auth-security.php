<?php
function consumeAuthRateLimit(PDO $db, string $scope, string $identity, int $maxAttempts): bool
{
    $key = hash('sha256', strtolower(trim($identity)));
    $db->beginTransaction();
    try {
        $insert = $db->prepare(
            'INSERT IGNORE INTO `auth_rate_limit` (`scope`, `key_hash`, `attempts`, `window_started_at`)
             VALUES (?, ?, 0, UTC_TIMESTAMP())'
        );
        $insert->execute([$scope, $key]);

        $select = $db->prepare(
            'SELECT `attempts`, `window_started_at`, `locked_until`
             FROM `auth_rate_limit`
             WHERE `scope` = ? AND `key_hash` = ?
             FOR UPDATE'
        );
        $select->execute([$scope, $key]);
        $record = $select->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            throw new RuntimeException('Unable to load authentication rate-limit record');
        }

        if (!empty($record['locked_until']) && strtotime($record['locked_until'] . ' UTC') > time()) {
            $db->commit();
            return true;
        }

        $windowExpired = strtotime($record['window_started_at'] . ' UTC') < time() - 900;
        $attempts = $windowExpired ? 1 : (int)$record['attempts'] + 1;
        $lockedUntil = $attempts > $maxAttempts ? gmdate('Y-m-d H:i:s', time() + 900) : null;
        $update = $db->prepare(
            'UPDATE `auth_rate_limit`
             SET `attempts` = ?, `window_started_at` = ?, `locked_until` = ?
             WHERE `scope` = ? AND `key_hash` = ?'
        );
        $update->execute([
            $attempts,
            $windowExpired ? gmdate('Y-m-d H:i:s') : $record['window_started_at'],
            $lockedUntil,
            $scope,
            $key,
        ]);
        $db->commit();
        return $lockedUntil !== null;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function clearAuthRateLimit(PDO $db, string $scope, string $identity): void
{
    $db->prepare('DELETE FROM `auth_rate_limit` WHERE `scope` = ? AND `key_hash` = ?')
        ->execute([$scope, hash('sha256', strtolower(trim($identity)))]);
}

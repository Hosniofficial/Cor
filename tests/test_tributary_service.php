<?php
require_once __DIR__ . '/../caesar-api/services/TributaryService.php';

$firstHour = TributaryService::calculatePayment(100, 3600, 0);
if ($firstHour !== ['amount' => 20, 'remainder' => 0]) {
    throw new RuntimeException('A full hour should transfer 20% of production');
}

$partial = TributaryService::calculatePayment(3, 3600, 0);
$nextPartial = TributaryService::calculatePayment(3, 3600, $partial['remainder']);
if ($partial['amount'] !== 0 || $nextPartial['amount'] !== 1) {
    throw new RuntimeException('Fractional tribute must carry over between settlements');
}

$negative = TributaryService::calculatePayment(-100, 3600, 0);
if ($negative !== ['amount' => 0, 'remainder' => 0]) {
    throw new RuntimeException('Negative production must not create tribute');
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/config_loader.php';
loadApiConfig();

$data = readJsonBody();
$nia = normalizeNia($data['nia'] ?? '');

$stmt = db()->prepare('SELECT COUNT(*) AS total FROM reservations WHERE nia = :nia');
$stmt->execute(['nia' => $nia]);
$reservedCount = (int) $stmt->fetch()['total'];

jsonResponse([
    'nia' => $nia,
    'reservedCount' => $reservedCount,
    'remainingSeats' => max(0, MAX_SEATS_PER_NIA - $reservedCount),
]);

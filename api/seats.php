<?php
declare(strict_types=1);
require_once __DIR__ . '/config_loader.php';
loadApiConfig();

$zone = strtoupper(trim($_GET['zone'] ?? ''));
$allowedZones = ['A', 'B', 'C', 'D', 'E'];

if (!in_array($zone, $allowedZones, true)) {
    jsonResponse(['message' => 'Zona no vàlida.'], 422);
}

$stmt = db()->prepare('SELECT seat_code FROM reservations WHERE zone = :zone ORDER BY row_number, seat_number');
$stmt->execute(['zone' => $zone]);
$reservedSeats = array_column($stmt->fetchAll(), 'seat_code');

jsonResponse(['zone' => $zone, 'reservedSeats' => $reservedSeats]);

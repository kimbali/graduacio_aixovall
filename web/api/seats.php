<?php
declare(strict_types=1);

require_once __DIR__ . '/secret-loader.php';

try {
    load_secrets('db');

    $zone = strtoupper(trim($_GET['zone'] ?? ''));
    $allowedZones = ['A', 'B', 'C', 'D', 'E'];

    if (!in_array($zone, $allowedZones, true)) {
        jsonResponse(['message' => 'Zona no vàlida.'], 422);
    }

    error_log(seatsLogMessage('Consultant seients reservats', ['zone' => $zone]));

    $stmt = db()->prepare('SELECT seat_code FROM reservations WHERE zone = :zone ORDER BY row_number, seat_number');
    $stmt->execute(['zone' => $zone]);
    $reservedSeats = array_column($stmt->fetchAll(), 'seat_code');

    error_log(seatsLogMessage('Consulta completada', [
        'zone' => $zone,
        'reserved_count' => count($reservedSeats),
    ]));

    jsonResponse(['zone' => $zone, 'reservedSeats' => $reservedSeats]);
} catch (Throwable $exception) {
    error_log(seatsLogMessage('Error a /api/seats.php', [
        'zone' => $_GET['zone'] ?? null,
        'exception_class' => $exception::class,
        'exception_code' => $exception->getCode(),
        'exception_message' => $exception->getMessage(),
        'exception_file' => $exception->getFile(),
        'exception_line' => $exception->getLine(),
    ]));

    if (function_exists('jsonResponse')) {
        jsonResponse(['message' => 'Error carregant els seients reservats.'], 500);
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => 'Error carregant els seients reservats.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function seatsLogMessage(string $message, array $context = []): string
{
    $baseContext = [
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    return '[api/seats.php] ' . $message . ' ' . json_encode(
        array_filter(array_merge($baseContext, $context), static fn ($value): bool => $value !== null),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

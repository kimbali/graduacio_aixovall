<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$data = readJsonBody();
$nia = normalizeNia($data['nia'] ?? '');
$seats = $data['seats'] ?? [];

if (!is_array($seats) || count($seats) === 0) {
    jsonResponse(['message' => 'Selecciona com a mínim un seient.'], 422);
}

if (count($seats) > MAX_SEATS_PER_NIA) {
    jsonResponse(['message' => 'No pots reservar més de 4 seients.'], 422);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $countStmt = $pdo->prepare('SELECT COUNT(*) AS total FROM reservations WHERE nia = :nia FOR UPDATE');
    $countStmt->execute(['nia' => $nia]);
    $alreadyReserved = (int) $countStmt->fetch()['total'];

    if ($alreadyReserved + count($seats) > MAX_SEATS_PER_NIA) {
        throw new RuntimeException('Aquest NIA supera el màxim de 4 seients.');
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO reservations (nia, zone, row_number, seat_number, seat_code)
         VALUES (:nia, :zone, :row_number, :seat_number, :seat_code)'
    );

    $confirmedSeats = [];

    foreach ($seats as $seat) {
        $zone = strtoupper((string) ($seat['zone'] ?? ''));
        $row = (int) ($seat['row'] ?? 0);
        $seatNumber = (int) ($seat['seat'] ?? 0);
        $seatCode = $zone . '-' . $row . '-' . $seatNumber;

        if (!preg_match('/^[A-E]$/', $zone) || $row < 2 || $row > 30 || $seatNumber < 1 || $seatNumber > 33) {
            throw new RuntimeException('Hi ha un seient invàlid a la selecció.');
        }

        $insertStmt->execute([
            'nia' => $nia,
            'zone' => $zone,
            'row_number' => $row,
            'seat_number' => $seatNumber,
            'seat_code' => $seatCode,
        ]);

        $confirmedSeats[] = $seatCode;
    }

    $pdo->commit();
    jsonResponse(['message' => 'Reserva confirmada.', 'seats' => $confirmedSeats]);
} catch (PDOException $exception) {
    $pdo->rollBack();

    if ($exception->getCode() === '23000') {
        jsonResponse([
            'message' => 'Ho sentim, un altre usuari acaba de reservar aquest seient. Seleccioneu-ne un altre.',
            'reservedSeats' => findReservedSeatsFromSelection($seats),
        ], 409);
    }

    jsonResponse(['message' => 'Error de base de dades.'], 500);
} catch (Throwable $exception) {
    $pdo->rollBack();
    jsonResponse(['message' => $exception->getMessage()], 422);
}

function findReservedSeatsFromSelection(array $seats): array
{
    $conditions = [];
    $params = [];
    $selectedSeats = [];

    foreach ($seats as $index => $seat) {
        $zone = strtoupper((string) ($seat['zone'] ?? ''));
        $row = (int) ($seat['row'] ?? 0);
        $seatNumber = (int) ($seat['seat'] ?? 0);

        if (!preg_match('/^[A-E]$/', $zone) || $row < 2 || $row > 30 || $seatNumber < 1 || $seatNumber > 33) {
            continue;
        }

        $rowParam = 'row_' . $index;
        $seatParam = 'seat_' . $index;
        $conditions[] = "(row_number = :$rowParam AND seat_number = :$seatParam)";
        $params[$rowParam] = $row;
        $params[$seatParam] = $seatNumber;
        $selectedSeats[$row . '-' . $seatNumber] = $zone . '-' . $row . '-' . $seatNumber;
    }

    if ($conditions === []) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT row_number, seat_number FROM reservations WHERE ' . implode(' OR ', $conditions)
    );
    $stmt->execute($params);

    $reservedSeats = [];
    foreach ($stmt->fetchAll() as $reservedSeat) {
        $key = (int) $reservedSeat['row_number'] . '-' . (int) $reservedSeat['seat_number'];
        if (isset($selectedSeats[$key])) {
            $reservedSeats[] = $selectedSeats[$key];
        }
    }

    return array_values(array_unique($reservedSeats));
}

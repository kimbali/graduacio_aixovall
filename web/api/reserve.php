<?php
declare(strict_types=1);

require_once __DIR__ . '/secret-loader.php';
load_secrets('db');

require_once __DIR__ . '/mailer.php';

$data = readJsonBody();

$nia = normalizeNia($data['nia'] ?? '');
ensureGraduatedNia($nia);
$studentName = normalizeStudentName($data['student_name'] ?? $data['studentName'] ?? '');
$studentEmail = normalizeStudentEmail($data['student_email'] ?? $data['studentEmail'] ?? '');
$seats = $data['seats'] ?? [];

if (!is_array($seats) || count($seats) === 0) {
    jsonResponse(['message' => 'Selecciona com a mínim un seient.'], 422);
}

if (count($seats) > MAX_SEATS_PER_NIA) {
    jsonResponse(['message' => 'No pots reservar més de 5 seients.'], 422);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $countStmt = $pdo->prepare(
        'SELECT COUNT(*) AS total 
         FROM `reservations` 
         WHERE `nia` = :nia 
         FOR UPDATE'
    );

    $countStmt->execute([
        'nia' => $nia
    ]);

    $alreadyReserved = (int) $countStmt->fetch()['total'];

    if ($alreadyReserved + count($seats) > MAX_SEATS_PER_NIA) {
        throw new RuntimeException('Aquest NIA supera el màxim de 5 seients.');
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO `reservations` 
            (`nia`, `student_name`, `student_email`, `zone`, `row_number`, `seat_number`, `seat_code`)
         VALUES 
            (:nia, :student_name, :student_email, :zone, :row_number, :seat_number, :seat_code)'
    );

    $confirmedSeats = [];
    $confirmedSeatsForEmail = [];

    foreach ($seats as $seat) {
        $zone = strtoupper((string) ($seat['zone'] ?? ''));
        $row = (int) ($seat['row'] ?? 0);
        $seatNumber = (int) ($seat['seat'] ?? 0);
        $seatCode = $zone . '-' . $row . '-' . $seatNumber;

        if (!seatExists($zone, $row, $seatNumber)) {
            throw new RuntimeException('Hi ha un seient invàlid a la selecció.');
        }

        if (seatIsBlocked($zone, $row)) {
            throw new RuntimeException('Aquest seient no està disponible.');
        }

        $insertStmt->execute([
            'nia' => $nia,
            'student_name' => $studentName,
            'student_email' => $studentEmail,
            'zone' => $zone,
            'row_number' => $row,
            'seat_number' => $seatNumber,
            'seat_code' => $seatCode,
        ]);

        $confirmedSeats[] = $seatCode;

        $confirmedSeatsForEmail[] = [
            'zone' => $zone,
            'row' => $row,
            'seat' => $seatNumber,
        ];
    }

    $pdo->commit();

    /*
     * Enviem l'email DESPRÉS de guardar la reserva.
     * Si l'email falla, la reserva continua sent vàlida.
     */
    $emailSent = false;

    try {
        $emailSent = sendReservationConfirmationEmail(
            $studentEmail,
            $studentName,
            $nia,
            $confirmedSeatsForEmail
        );
    } catch (Throwable $emailException) {
        error_log('Error enviant email de confirmació: ' . $emailException->getMessage());
    }

    jsonResponse([
        'message' => 'Reserva confirmada.',
        'seats' => $confirmedSeats,
        'email_sent' => $emailSent,
    ]);

} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($exception->getCode() === '23000') {
        jsonResponse([
            'message' => 'Ho sentim, un altre usuari acaba de reservar aquest seient. Seleccioneu-ne un altre.',
            'reservedSeats' => findReservedSeatsFromSelection($seats),
        ], 409);
    }

    if (DEBUG_MODE) {
        jsonResponse([
            'message' => 'Error de base de dades.',
            'debug' => $exception->getMessage(),
        ], 500);
    }

    jsonResponse(['message' => 'Error de base de dades.'], 500);

} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    jsonResponse(['message' => $exception->getMessage()], 422);
}

function normalizeStudentName(mixed $studentName): string
{
    $cleanStudentName = trim((string) $studentName);

    if ($cleanStudentName === '' || strlen($cleanStudentName) > 120) {
        jsonResponse(['message' => 'El nom i cognoms no són vàlids.'], 422);
    }

    return $cleanStudentName;
}

function normalizeStudentEmail(mixed $studentEmail): string
{
    $cleanStudentEmail = trim((string) $studentEmail);

    if (!filter_var($cleanStudentEmail, FILTER_VALIDATE_EMAIL) || strlen($cleanStudentEmail) > 160) {
        jsonResponse(['message' => 'L’email no és vàlid.'], 422);
    }

    return $cleanStudentEmail;
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

        if (!seatExists($zone, $row, $seatNumber)) {
            continue;
        }

        $zoneParam = 'zone_' . $index;
        $rowParam = 'row_' . $index;
        $seatParam = 'seat_' . $index;

        $conditions[] = "(`zone` = :$zoneParam AND `row_number` = :$rowParam AND `seat_number` = :$seatParam)";

        $params[$zoneParam] = $zone;
        $params[$rowParam] = $row;
        $params[$seatParam] = $seatNumber;

        $selectedSeats[$zone . '-' . $row . '-' . $seatNumber] = $zone . '-' . $row . '-' . $seatNumber;
    }

    if ($conditions === []) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT `zone`, `row_number`, `seat_number` 
         FROM `reservations` 
         WHERE ' . implode(' OR ', $conditions)
    );

    $stmt->execute($params);

    $reservedSeats = [];

    foreach ($stmt->fetchAll() as $reservedSeat) {
        $key = $reservedSeat['zone'] . '-' . (int) $reservedSeat['row_number'] . '-' . (int) $reservedSeat['seat_number'];

        if (isset($selectedSeats[$key])) {
            $reservedSeats[] = $selectedSeats[$key];
        }
    }

    return array_values(array_unique($reservedSeats));
}

function seatExists(string $zone, int $row, int $seatNumber): bool
{
    return in_array($seatNumber, validSeatNumbers($zone, $row), true);
}

function seatIsBlocked(string $zone, int $row): bool
{
    return ($zone === 'B' && $row >= 2 && $row <= 13)
        || ($zone === 'C' && $row >= 2 && $row <= 12);
}

function validSeatNumbers(string $zone, int $row): array
{
    return match ($zone) {
        'A' => zoneASeats($row),
        'B' => zoneBSeats($row),
        'C' => zoneCSeats($row),
        'D' => $row >= 20 && $row <= 30 ? oddDescending(31, 1) : [],
        'E' => $row >= 20 && $row <= 30 ? evenAscending(2, 32) : [],
        default => [],
    };
}

function zoneASeats(int $row): array
{
    if ($row >= 2 && $row <= 7) {
        return oddDescending(29, 15);
    }

    if ($row >= 8 && $row <= 15) {
        return oddDescending(31, 17);
    }

    if ($row >= 16 && $row <= 19) {
        return oddDescending(33, 19);
    }

    return [];
}

function zoneBSeats(int $row): array
{
    if ($row >= 2 && $row <= 3) {
        return centerSeats(13, 12);
    }

    if ($row >= 4 && $row <= 7) {
        return centerSeats(13, 14);
    }

    if ($row >= 8 && $row <= 11) {
        return centerSeats(15, 14);
    }

    if ($row >= 12 && $row <= 15) {
        return centerSeats(15, 16);
    }

    if ($row >= 16 && $row <= 19) {
        return centerSeats(17, 16);
    }

    return [];
}

function zoneCSeats(int $row): array
{
    if ($row >= 2 && $row <= 3) {
        return evenAscending(14, 28);
    }

    if ($row >= 4 && $row <= 11) {
        return evenAscending(16, 30);
    }

    if ($row >= 12 && $row <= 32) {
        return evenAscending(18, 32);
    }

    return [];
}

function centerSeats(int $leftMax, int $rightMax): array
{
    return array_merge(oddDescending($leftMax, 1), evenAscending(2, $rightMax));
}

function oddDescending(int $max, int $min): array
{
    return array_reverse(array_filter(range($min, $max), fn (int $number): bool => $number % 2 === 1));
}

function evenAscending(int $min, int $max): array
{
    return array_values(array_filter(range($min, $max), fn (int $number): bool => $number % 2 === 0));
}

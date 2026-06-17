<?php
declare(strict_types=1);
// require_once __DIR__ . '/config.php';
require_once __DIR__ . '/local_config.php';

$data = readJsonBody();
$nia = normalizeNia($data['nia'] ?? '');
$studentName = normalizeStudentName($data['student_name'] ?? $data['studentName'] ?? '');
$studentEmail = normalizeStudentEmail($data['student_email'] ?? $data['studentEmail'] ?? '');
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
        'INSERT INTO reservations (nia, student_name, student_email, zone, row_number, seat_number, seat_code)
         VALUES (:nia, :student_name, :student_email, :zone, :row_number, :seat_number, :seat_code)'
    );

    $confirmedSeats = [];

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
    }

    $pdo->commit();

    $emailSent = sendReservationConfirmationEmail($studentEmail, $studentName, $nia, $confirmedSeats);

    jsonResponse([
        'message' => $emailSent
            ? 'Reserva confirmada. Hem enviat el correu de confirmació.'
            : 'Reserva confirmada, però no hem pogut enviar el correu de confirmació.',
        'seats' => $confirmedSeats,
        'emailSent' => $emailSent,
    ]);
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


function sendReservationConfirmationEmail(string $studentEmail, string $studentName, string $nia, array $confirmedSeats): bool
{
    $subject = 'Confirmació de reserva · Graduació CFP Andorra 2026';
    $body = buildReservationConfirmationEmailBody($studentName, $nia, $confirmedSeats);
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: Graduació CFP Andorra <' . RESERVATION_EMAIL_FROM . '>',
        'Reply-To: ' . RESERVATION_CONTACT_EMAIL,
        'X-Auto-Response-Suppress: All',
    ];

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $sent = mail($studentEmail, $encodedSubject, $body, implode("\r\n", $headers), '-f' . RESERVATION_EMAIL_FROM);

    if (!$sent) {
        error_log("No s'ha pogut enviar el correu de confirmació de reserva a " . $studentEmail . ' per al NIA ' . $nia);
    }

    return $sent;
}

function buildReservationConfirmationEmailBody(string $studentName, string $nia, array $confirmedSeats): string
{
    $seatLines = array_map(
        static fn (string $seatCode): string => '- ' . formatSeatForEmail($seatCode),
        $confirmedSeats
    );

    return implode("\n", [
        'Hola ' . $studentName . ',',
        '',
        'La teva reserva de butaques per a la Graduació CFP Andorra 2026 ha quedat confirmada.',
        '',
        'Dades de la reserva:',
        '- NIA: ' . $nia,
        '- Butaques:',
        ...$seatLines,
        '',
        "Recollida d'entrades:",
        "Cal recollir les entrades al centre de FP d'Aixovall entre el 25 de juny a les 8.00 h i el 29 de juny a les 14.00 h. Les entrades seran necessàries per accedir al recinte.",
        '',
        'Contacte:',
        'Per a qualsevol consulta, escriu a ' . RESERVATION_CONTACT_EMAIL . '.',
        '',
        'No responguis a aquest correu: és un missatge automàtic i només informatiu.',
        '',
        'Gràcies,',
        "Centre de Formació Professional d'Andorra",
    ]);
}

function formatSeatForEmail(string $seatCode): string
{
    $parts = explode('-', $seatCode);

    if (count($parts) !== 3) {
        return $seatCode;
    }

    return 'Zona ' . $parts[0] . ' · Fila ' . $parts[1] . ' · Butaca ' . $parts[2];
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
        $conditions[] = "(zone = :$zoneParam AND row_number = :$rowParam AND seat_number = :$seatParam)";
        $params[$zoneParam] = $zone;
        $params[$rowParam] = $row;
        $params[$seatParam] = $seatNumber;
        $selectedSeats[$zone . '-' . $row . '-' . $seatNumber] = $zone . '-' . $row . '-' . $seatNumber;
    }

    if ($conditions === []) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT zone, row_number, seat_number FROM reservations WHERE ' . implode(' OR ', $conditions)
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

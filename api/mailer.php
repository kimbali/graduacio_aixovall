<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

/**
 * Escapa textos para mostrarlos de forma segura dentro del HTML del email.
 */
function mailerEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Convierte un asiento en texto legible para el email.
 *
 * Acepta varios formatos posibles:
 * - ['zone' => 'A', 'row' => 4, 'number' => 12]
 * - ['zone_id' => 'A', 'row_number' => 4, 'seat_number' => 12]
 * - ['label' => 'Zona A · Fila 4 · Seient 12']
 * - 'Zona A · Fila 4 · Seient 12'
 */
function mailerFormatSeat(mixed $seat): string
{
    if (is_string($seat) || is_numeric($seat)) {
        return trim((string) $seat);
    }

    if (!is_array($seat)) {
        return 'Butaca seleccionada';
    }

    if (!empty($seat['label'])) {
        return trim((string) $seat['label']);
    }

    $zone = $seat['zone'] ?? $seat['zone_id'] ?? $seat['zone_name'] ?? '';
    $row = $seat['row'] ?? $seat['row_number'] ?? $seat['fila'] ?? '';
    $number = $seat['number'] ?? $seat['seat_number'] ?? $seat['seat'] ?? $seat['seient'] ?? '';

    $parts = [];

    if ($zone !== '') {
        $parts[] = 'Zona ' . $zone;
    }

    if ($row !== '') {
        $parts[] = 'Fila ' . $row;
    }

    if ($number !== '') {
        $parts[] = 'Seient ' . $number;
    }

    if (empty($parts)) {
        return 'Butaca seleccionada';
    }

    return implode(' · ', $parts);
}

/**
 * Envía el email de confirmación de reserva.
 */
function sendReservationConfirmationEmail(
    string $studentEmail,
    string $studentName,
    string $nia,
    array $reservedSeats
): bool {
    $studentEmail = trim($studentEmail);
    $studentName = trim($studentName);
    $nia = strtoupper(trim($nia));

    if ($studentName === '') {
        throw new Exception('El nom de l’alumne és obligatori.');
    }

    if (!filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email del destinatari no vàlid.');
    }

    if ($nia === '') {
        throw new Exception('El NIA és obligatori.');
    }

    $safeStudentName = mailerEscape($studentName);
    $safeNia = mailerEscape($nia);
    $safeStudentEmail = mailerEscape($studentEmail);

    $seatItemsHtml = '';
    $seatItemsText = '';

    foreach ($reservedSeats as $seat) {
        $seatText = mailerFormatSeat($seat);
        $safeSeatText = mailerEscape($seatText);

        $seatItemsHtml .= "<li>{$safeSeatText}</li>";
        $seatItemsText .= "- {$seatText}\n";
    }

    if ($seatItemsHtml === '') {
        $seatItemsHtml = '<li>No consten butaques en el resum de la reserva.</li>';
        $seatItemsText = "- No consten butaques en el resum de la reserva.\n";
    }

    $mail = new PHPMailer(true);

    // Configuració SMTP CDmon
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = SMTP_PORT;

    // Codificació
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    // Remitent i destinatari
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($studentEmail, $studentName);
    $mail->addReplyTo(SMTP_REPLY_TO_EMAIL, SMTP_REPLY_TO_NAME);

    // Contingut
    $mail->isHTML(true);
    $mail->Subject = 'Confirmació de la reserva de butaques';

    $mail->Body = "
        <!doctype html>
        <html lang='ca'>
        <head>
            <meta charset='UTF-8'>
            <title>Reserva confirmada</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #222; line-height: 1.5;'>
            <div style='max-width: 640px; margin: 0 auto; padding: 24px;'>
                <h1 style='margin-bottom: 8px;'>Reserva confirmada</h1>

                <p>Hola <strong>{$safeStudentName}</strong>,</p>

                <p>
                    Hem guardat correctament la reserva de butaques familiars
                    vinculada al NIA <strong>{$safeNia}</strong>.
                </p>

                <p>
                    Aquest és l’email de contacte associat a la reserva:
                    <strong>{$safeStudentEmail}</strong>
                </p>

                <h2>Butaques reservades</h2>

                <ul>
                    {$seatItemsHtml}
                </ul>

                <hr style='border: none; border-top: 1px solid #ddd; margin: 24px 0;'>

                <p>
                    <strong>Recorda recollir les entrades</strong> al centre de FP d’Aixovall
                    entre el <strong>25 de juny a les 8.00 h</strong> i el
                    <strong>29 de juny a les 14.00 h</strong>.
                </p>

                <p>
                    Les entrades seran necessàries per accedir al recinte.
                </p>

                <p>
                    T’agraïm que ens acompanyis fins al final de l’acte.
                    Acabarem aproximadament a les <strong>21.30 h</strong>.
                </p>

                <p style='margin-top: 24px;'>
                    Gràcies i ens veiem a la graduació!
                </p>

                <p style='font-size: 13px; color: #666; margin-top: 32px;'>
                    {$safeNia} · {$safeStudentName} · Graduació CFP Andorra 2026
                </p>
            </div>
        </body>
        </html>
    ";

    $mail->AltBody =
        "Reserva confirmada\n\n" .
        "Hola {$studentName},\n\n" .
        "Hem guardat correctament la reserva de butaques familiars vinculada al NIA {$nia}.\n\n" .
        "Email de contacte: {$studentEmail}\n\n" .
        "Butaques reservades:\n" .
        $seatItemsText .
        "\nRecorda recollir les entrades al centre de FP d’Aixovall entre el 25 de juny a les 8.00 h i el 29 de juny a les 14.00 h.\n" .
        "Les entrades seran necessàries per accedir al recinte.\n\n" .
        "T’agraïm que ens acompanyis fins al final de l’acte. Acabarem aproximadament a les 21.30 h.\n\n" .
        "Gràcies i ens veiem a la graduació!\n";

    return $mail->send();
}
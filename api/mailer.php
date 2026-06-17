<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

function sendReservationEmail(string $to, string $studentName, string $nia, array $seats): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('No s’ha enviat l’email de reserva: destinatari no vàlid.');
        return false;
    }

    if (!class_exists(PHPMailer::class)) {
        error_log('No s’ha enviat l’email de reserva: PHPMailer no està instal·lat o vendor/autoload.php no existeix.');
        return false;
    }

    $seatList = normalizeSeatList($seats);
    $escapedStudentName = htmlspecialchars($studentName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedNia = htmlspecialchars($nia, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $escapedSeats = array_map(
        static fn (string $seat): string => htmlspecialchars($seat, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        $seatList
    );

    $htmlSeatItems = implode('', array_map(static fn (string $seat): string => '<li>' . $seat . '</li>', $escapedSeats));
    $plainSeatList = implode(', ', $seatList);

    $htmlBody = <<<HTML
<!doctype html>
<html lang="ca">
<head>
    <meta charset="UTF-8">
    <title>Confirmació de reserva</title>
</head>
<body>
    <h1>Reserva confirmada</h1>
    <p>Hola {$escapedStudentName},</p>
    <p>La teva reserva per a la Graduació CFP Aixovall 2026 s’ha confirmat correctament.</p>
    <p><strong>NIA:</strong> {$escapedNia}</p>
    <p><strong>Butacas reservades:</strong></p>
    <ul>{$htmlSeatItems}</ul>
    <p>Recorda recollir les entrades al centre dins del termini indicat.</p>
    <p>Gràcies.</p>
</body>
</html>
HTML;

    $plainBody = "Reserva confirmada\n\n"
        . "Hola {$studentName},\n\n"
        . "La teva reserva per a la Graduació CFP Aixovall 2026 s’ha confirmat correctament.\n"
        . "NIA: {$nia}\n"
        . "Butacas reservades: {$plainSeatList}\n\n"
        . "Recorda recollir les entrades al centre dins del termini indicat.\n\n"
        . "Gràcies.";

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = 'base64';
        $mail->SMTPDebug = SMTP::DEBUG_OFF;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to, $studentName);
        $mail->addReplyTo(SMTP_FROM, SMTP_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = 'Confirmació de reserva - Graduació CFP Aixovall 2026';
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainBody;

        return $mail->send();
    } catch (PHPMailerException $exception) {
        error_log('Error enviant email de reserva: ' . $exception->getMessage());
        return false;
    } catch (Throwable $exception) {
        error_log('Error inesperat enviant email de reserva: ' . $exception->getMessage());
        return false;
    }
}

function normalizeSeatList(array $seats): array
{
    $seatList = [];

    foreach ($seats as $seat) {
        if (is_array($seat)) {
            $zone = strtoupper((string) ($seat['zone'] ?? ''));
            $row = (int) ($seat['row'] ?? 0);
            $seatNumber = (int) ($seat['seat'] ?? 0);
            $seatList[] = $zone . '-' . $row . '-' . $seatNumber;
            continue;
        }

        $seatList[] = (string) $seat;
    }

    return array_values(array_filter($seatList, static fn (string $seat): bool => trim($seat) !== ''));
}

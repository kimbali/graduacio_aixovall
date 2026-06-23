<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/secret-loader.php';
load_secrets('mail');

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
    $mail->SMTPSecure = SMTP_SECURE === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
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
        <body style='margin: 0; padding: 0; background: #fff8f3; font-family: Arial, sans-serif; color: #24110a; line-height: 1.55;'>
            <div style='max-width: 680px; margin: 0 auto; padding: 28px 18px;'>
                <div style='background: #ffffff; border: 1px solid #f1d8c9; border-radius: 22px; overflow: hidden; box-shadow: 0 14px 35px rgba(129, 38, 3, 0.11);'>
                    <div style='background: linear-gradient(135deg, #812603 0%, #b84b18 100%); color: #ffffff; padding: 30px 28px;'>
                        <p style='margin: 0 0 8px; color: #ffd9c4; font-size: 13px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;'>Graduació CFP Andorra 2026</p>
                        <h1 style='margin: 0; font-size: 30px; line-height: 1.15;'>Reserva confirmada</h1>
                    </div>

                    <div style='padding: 28px;'>
                        <p style='margin-top: 0;'>Hola <strong>{$safeStudentName}</strong>,</p>

                        <p>
                            Hem guardat correctament la reserva de butaques dels teus acompanyants
                            (NIA <strong>{$safeNia}</strong>).
                        </p>

                        <div style='background: #fff8f3; border-left: 5px solid #13694b; border-radius: 16px; padding: 16px 18px; margin: 22px 0;'>
                            <p style='margin: 0 0 6px; font-size: 13px; color: #6f3a20; text-transform: uppercase; letter-spacing: .08em; font-weight: 700;'>Email de contacte</p>
                            <p style='margin: 0; font-size: 17px;'><strong>{$safeStudentEmail}</strong></p>
                        </div>

                        <h2 style='color: #812603; margin: 28px 0 12px; font-size: 22px;'>Butaques reservades</h2>
                        <ul style='margin: 0; padding-left: 20px;'>
                            {$seatItemsHtml}
                        </ul>

                        <h2 style='color: #812603; margin: 30px 0 14px; font-size: 22px;'>Informació de l’acte</h2>
                        <div style='border: 1px solid #f1d8c9; border-radius: 18px; padding: 18px; background: #fffdfb;'>
                            <p style='margin: 0 0 8px;'><strong>DATA:</strong> Dimarts 30 de juny de 2026</p>
                            <p style='margin: 0 0 8px;'><strong>LLOC:</strong> Centre de Congressos d’Andorra la Vella</p>
                            <p style='margin: 0;'><strong>HORA:</strong> 19h00</p>
                        </div>

                        <ul style='margin: 22px 0; padding-left: 20px;'>
                            <li style='margin-bottom: 12px;'><strong>Graduats/des:</strong> s'han de presentar al Centre de Congressos a les 18h15 per signar el document de presència i el diploma. A la sala Consorcia (al mateix Centre de Congressos d’Andorra la Vella).</li>
                            <li><strong>Famílies i acompanyants:</strong> les portes de la sala d’actes s'obriran a les 18h45. Es demana arribar abans de les 18h55 per garantir el bon inici de l'acte.</li>
                        </ul>

                        <p>La durada aproximada de la cerimònia és de 2h30. Es demana tant als graduats/des com a la resta d'assistents que romanguin fins al final de l'acte per respecte a tothom.</p>
                        <p><strong>Es prega màxima puntualitat:</strong> a les 19h00 es tancarà les portes i no s’hi podrà accedir durant els parlaments.</p>

                        <div style='background: #f1f8f4; border: 1px solid #cfe7d8; border-radius: 18px; padding: 18px; margin-top: 24px;'>
                            <p style='margin: 0 0 12px;'><strong>Recollida d’entrades</strong></p>
                            <p style='margin: 0 0 12px;'>Les entrades s'han de recollir presencialment al centre escolar corresponent (Aixovall o La Massana) durant el mateix període en què estigui oberta la prereserva preferent, és a dir, entre el 25 i el 29 de juny en horaris d’obertura del centre.</p>
                            <p style='margin: 0;'>El dia 30 de juny, es podrà recollir en el mateix Centre de Congressos d’Andorra la Vella, les butaques addicionals.</p>
                        </div>

                        <p style='margin-top: 28px;'>Gràcies i ens veiem a la graduació!</p>

                        <p style='font-size: 13px; color: #6f3a20; margin-top: 32px; border-top: 1px solid #f1d8c9; padding-top: 18px;'>
                            {$safeNia} · {$safeStudentName} · Graduació CFP Andorra 2026
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>
    ";

    $mail->AltBody =
        "Reserva confirmada\n\n" .
        "Hola {$studentName},\n\n" .
        "Hem guardat correctament la reserva de butaques dels acompanyants del NIA {$nia}.\n\n" .
        "Butaques reservades:\n" .
        $seatItemsText .
        "\nDATA: Dimarts 30 de juny de 2026\n" .
        "LLOC: Centre de Congressos d’Andorra la Vella\n" .
        "HORA: 19h00\n\n" .
        "Graduats/des: s'han de presentar al Centre de Congressos a les 18h15 per signar el document de presència i el diploma. A la sala Consorcia (al mateix Centre de Congressos d’Andorra la Vella).\n\n" .
        "Famílies i acompanyants: les portes de la sala d’actes s'obriran a les 18h45. Es demana arribar abans de les 18h55 per garantir el bon inici de l'acte.\n\n" .
        "La durada aproximada de la cerimònia és de 2h30. Es demana tant als graduats/des com a la resta d'assistents que romanguin fins al final de l'acte per respecte a tothom.\n\n" .
        "Es prega màxima puntualitat, a les 19h00 es tancarà les portes i no s’hi podrà accedir durant els parlaments.\n\n" .
        "Les entrades s'han de recollir presencialment al centre escolar corresponent (Aixovall o La Massana) durant el mateix període en què estigui oberta la prereserva preferent, és a dir, entre el 25 i el 29 de juny en horaris d’obertura del centre.\n\n" .
        "El dia 30 de juny, es podrà recollir en el mateix Centre de Congressos d’Andorra la Vella, les butaques addicionals.\n\n" .
        "Gràcies i ens veiem a la graduació!\n";

    return $mail->send();
}
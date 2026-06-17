<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Fes una petició POST amb email, student_name i nia per provar l’enviament.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = $_POST;
}

$email = trim((string) ($data['email'] ?? $data['student_email'] ?? ''));
$studentName = trim((string) ($data['student_name'] ?? $data['studentName'] ?? 'Prova Graduació'));
$nia = strtoupper(trim((string) ($data['nia'] ?? '000000A')));
$seats = $data['seats'] ?? ['PROVA-1-1', 'PROVA-1-2'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Email de destinatari no vàlid.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($studentName === '' || strlen($studentName) > 120) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Nom de prova no vàlid.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^\d{6}[A-Z]$/', $nia)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'NIA de prova no vàlid. Format esperat: 000000A.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_array($seats) || $seats === []) {
    $seats = ['PROVA-1-1', 'PROVA-1-2'];
}

$emailSent = sendReservationEmail($email, $studentName, $nia, $seats);

echo json_encode([
    'success' => $emailSent,
    'message' => $emailSent
        ? 'Email de prova enviat correctament.'
        : 'No s’ha pogut enviar l’email de prova. Revisa SMTP_PASS, vendor/ i el log PHP del servidor.',
], JSON_UNESCAPED_UNICODE);

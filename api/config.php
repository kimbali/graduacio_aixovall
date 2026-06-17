<?php
// Copia aquest fitxer i posa les dades reals de CDMON.
declare(strict_types=1);

const DB_HOST = 'localhost';
const DB_NAME = 'graduacio';
const DB_USER = 'gradua4250';
const DB_PASS = 'Lentejas07!!';
const MAX_SEATS_PER_NIA = 4;
const RESERVATION_EMAIL_FROM = 'info@graduaciocfpandorra.ad';
const RESERVATION_CONTACT_EMAIL = 'jcampsl@educand.ad';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function readJsonBody(): array
{
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);

    if (!is_array($data)) {
        jsonResponse(['message' => 'Petició invàlida.'], 400);
    }

    return $data;
}

function normalizeNia(mixed $nia): string
{
    $cleanNia = strtoupper(trim((string) $nia));

    if (!preg_match('/^\d{6}[A-Z]$/', $cleanNia)) {
        jsonResponse(['message' => 'El NIA no és vàlid.'], 422);
    }

    return $cleanNia;
}

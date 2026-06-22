<?php
declare(strict_types=1);

/*
 * Configuració de base de dades i helpers comuns del projecte.
 *
 * IMPORTANT per a CDmon:
 * - DB_HOST: posa el host/IP que indica el panell de CDmon per a MySQL.
 *   Si amb "localhost" no connecta, posa la Web IP del hosting.
 * - DB_NAME: ha de ser EXACTAMENT el nom de la base de dades creada al panell.
 * - DB_USER / DB_PASS: usuari MySQL assignat a aquesta base de dades.
 */

const APP_NAME = 'Graduació CFP Andorra 2026';
const MAX_SEATS_PER_NIA = 4;
const DEBUG_MODE = false;

const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'graduacio_aixovall_2026';
const DB_USER = 'root';
const DB_PASS = '';

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            $payload = [
                'message' => 'No s’ha pogut connectar amb la base de dades. Revisa secrets/db.php.',
            ];

            if (DEBUG_MODE) {
                $payload['debug'] = $exception->getMessage();
            }

            jsonResponse($payload, 500);
        }
    }

    return $pdo;
}

function readJsonBody(): array
{
    $rawBody = file_get_contents('php://input') ?: '';
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

return [
    'app_name' => APP_NAME,
    'max_seats_per_nia' => MAX_SEATS_PER_NIA,
    'debug_mode' => DEBUG_MODE,
    'db_host' => DB_HOST,
    'db_port' => DB_PORT,
    'db_name' => DB_NAME,
    'db_user' => DB_USER,
    'db_pass' => DB_PASS,
];

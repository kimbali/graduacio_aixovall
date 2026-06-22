<?php
declare(strict_types=1);

/*
 * Configuració única del projecte.
 *
 * IMPORTANT per a CDmon:
 * - DB_HOST: posa el host/IP que indica el panell de CDmon per a MySQL.
 *   Si amb "localhost" no connecta, posa la Web IP del hosting.
 * - DB_NAME: ha de ser EXACTAMENT el nom de la base de dades creada al panell.
 * - DB_USER / DB_PASS: usuari MySQL assignat a aquesta base de dades.
 *
 * IMPORTANT per a l'email:
 * - El correu s'envia des del compte del domini.
 * - No posis mai aquestes dades en cap fitxer JS.
 */

const APP_NAME = 'Graduació CFP Andorra 2026';
const MAX_SEATS_PER_NIA = 4;
const DEBUG_MODE = false;


// ==========================
// CONFIGURACIÓ BASE DE DADES
// ==========================

const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'graduacio';
const DB_USER = 'gradua4250';
const DB_PASS = 'Lentejas07!!';


// ==========================
// CONFIGURACIÓ EMAIL CDMON
// ==========================

const SMTP_HOST = 'smtp.graduaciocfpandorra.ad';
const SMTP_PORT = 465;

const SMTP_USER = 'info@graduaciocfpandorra.ad';
const SMTP_PASS = 'Lentejas07!!';

const SMTP_FROM_EMAIL = 'info@graduaciocfpandorra.ad';
const SMTP_FROM_NAME = APP_NAME;

const SMTP_REPLY_TO_EMAIL = 'info@graduaciocfpandorra.ad';
const SMTP_REPLY_TO_NAME = APP_NAME;


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
                'message' => 'No s’ha pogut connectar amb la base de dades. Revisa api/config.php.'
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
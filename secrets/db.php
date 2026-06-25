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
const MAX_SEATS_PER_NIA = 5;
const DEBUG_MODE = false;
const GRADUATES_XLSX_PATH = __DIR__ . '/../web/assets/llista_graduats.xlsx';

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

function ensureGraduatedNia(string $nia): void
{
    if (!isGraduatedNia($nia)) {
        jsonResponse([
            'message' => 'El NIA introduït no consta a la llista de graduats.',
        ], 422);
    }
}

function isGraduatedNia(string $nia): bool
{
    $graduatedNias = getGraduatedNias();

    return isset($graduatedNias[$nia]);
}

function getGraduatedNias(): array
{
    static $nias = null;

    if ($nias !== null) {
        return $nias;
    }

    if (!is_file(GRADUATES_XLSX_PATH)) {
        jsonResponse([
            'message' => 'No s’ha trobat la llista de graduats.',
        ], 500);
    }

    if (!class_exists(ZipArchive::class)) {
        jsonResponse([
            'message' => 'El servidor no pot llegir la llista de graduats.',
        ], 500);
    }

    $zip = new ZipArchive();

    if ($zip->open(GRADUATES_XLSX_PATH) !== true) {
        jsonResponse([
            'message' => 'No s’ha pogut obrir la llista de graduats.',
        ], 500);
    }

    $sharedStrings = readXlsxSharedStrings($zip);
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if ($sheetXml === false) {
        jsonResponse([
            'message' => 'No s’ha pogut llegir la llista de graduats.',
        ], 500);
    }

    $nias = [];
    $worksheet = simplexml_load_string($sheetXml);

    if ($worksheet === false) {
        jsonResponse([
            'message' => 'La llista de graduats no té un format vàlid.',
        ], 500);
    }

    $worksheet->registerXPathNamespace('xlsx', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $cells = $worksheet->xpath('//xlsx:sheetData/xlsx:row/xlsx:c[starts-with(@r, "B")]') ?: [];

    foreach ($cells as $cell) {
        $cellReference = (string) $cell['r'];

        if ($cellReference === 'B1') {
            continue;
        }

        $nia = normalizeGraduatedNia(readXlsxCellValue($cell, $sharedStrings));

        if ($nia !== null) {
            $nias[$nia] = true;
        }
    }

    return $nias;
}

function readXlsxSharedStrings(ZipArchive $zip): array
{
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

    if ($sharedStringsXml === false) {
        return [];
    }

    $sharedStrings = [];
    $xml = simplexml_load_string($sharedStringsXml);

    if ($xml === false) {
        return [];
    }

    $xml->registerXPathNamespace('xlsx', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    foreach ($xml->xpath('//xlsx:si') ?: [] as $sharedString) {
        $parts = [];
        $sharedString->registerXPathNamespace('xlsx', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        foreach ($sharedString->xpath('.//xlsx:t') ?: [] as $text) {
            $parts[] = (string) $text;
        }

        $sharedStrings[] = implode('', $parts);
    }

    return $sharedStrings;
}

function readXlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): string
{
    $value = isset($cell->v) ? (string) $cell->v : '';

    if ((string) $cell['t'] === 's' && $value !== '') {
        return $sharedStrings[(int) $value] ?? '';
    }

    return $value;
}

function normalizeGraduatedNia(string $nia): ?string
{
    $cleanNia = strtoupper(trim($nia));

    if (preg_match('/^\d{6}[A-Z]$/', $cleanNia) !== 1) {
        return null;
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

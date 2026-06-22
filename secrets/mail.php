<?php
declare(strict_types=1);

/*
 * Configuració SMTP del projecte.
 *
 * IMPORTANT:
 * - El correu s’envia des del compte del domini.
 * - No posis mai aquestes dades en cap fitxer JS.
 */

const SMTP_HOST = 'smtp.graduaciocfpandorra.ad';
const SMTP_PORT = 465;
const SMTP_SECURE = 'ssl';

const SMTP_USER = 'info@graduaciocfpandorra.ad';
const SMTP_PASS = 'Lentejas07!!';

const SMTP_FROM_EMAIL = 'info@graduaciocfpandorra.ad';
const SMTP_FROM_NAME = 'Graduació CFP Andorra 2026';

const SMTP_REPLY_TO_EMAIL = 'info@graduaciocfpandorra.ad';
const SMTP_REPLY_TO_NAME = 'Graduació CFP Andorra 2026';

return [
    'smtp_host' => SMTP_HOST,
    'smtp_port' => SMTP_PORT,
    'smtp_secure' => SMTP_SECURE,
    'smtp_user' => SMTP_USER,
    'smtp_pass' => SMTP_PASS,
    'smtp_from_email' => SMTP_FROM_EMAIL,
    'smtp_from_name' => SMTP_FROM_NAME,
    'smtp_reply_to_email' => SMTP_REPLY_TO_EMAIL,
    'smtp_reply_to_name' => SMTP_REPLY_TO_NAME,
];

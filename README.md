# Reserva de seients · Graduació Aixovall 2026

Web senzilla amb HTML, CSS, JavaScript i PHP + MySQL.

## Instal·lació en local amb XAMPP

1. Obre XAMPP i engega Apache i MySQL.
2. Descarrega el projecte des de GitHub dins del directori `htdocs` de XAMPP:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs
git clone https://github.com/kimbali/graduacio_aixovall.git graduacio-aixovall-2026
```

Si no tens Git instal·lat, també pots descarregar el projecte com a ZIP des de GitHub, descomprimir-lo i posar la carpeta dins de `htdocs`.

3. Obre phpMyAdmin:

```txt
http://localhost/phpmyadmin
```

4. Crea una base de dades nova amb el nom:

```txt
graduacio_aixovall_2026
```

5. Entra dins la base de dades, ves a la pestanya **Importa** i importa `sql/schema.sql`.
6. Edita `api/config.php` amb les dades locals de connexió:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
7. Obre el projecte al navegador:

```txt
http://localhost/graduacio-aixovall-2026/
```

## Instal·lació a CDMON

1. Crea una base de dades MySQL des del panell de CDMON.
2. Obre phpMyAdmin i importa `sql/schema.sql`.
3. Edita `api/config.php` amb les dades reals de connexió:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
4. Puja tots els fitxers al directori públic del domini.
5. Obre `index.html`.

## Fitxers SQL

- `sql/schema.sql`: esquema complet per a una instal·lació nova. És el fitxer que cal importar si la base de dades encara és buida.
- `sql/add-student-fields.sql`: migració per a bases de dades antigues que ja tenien la taula `reservations`, però encara no tenien els camps `student_name` i `student_email`.

Per tant, tots dos fitxers són vàlids, però tenen usos diferents. En una instal·lació nova, n'hi ha prou amb `sql/schema.sql`.

## Ajustar seients bloquejats

Els bloquejos estan a `assets/js/config.js`:

- `BLOCKED_SEATS`: autoritats, alumnes graduats i docents.
- `ACCESSIBLE_SEATS`: seients PMR marcats en blau.

Format d’un seient:

```txt
ZONA-FILA-SEIENT
B-2-1
E-20-8
```

## Notes

- Cada NIA pot reservar màxim 4 seients.
- La base de dades impedeix que dues persones reservin el mateix seient.
- Les zones del mapa s’han simplificat en A, B, C, D i E.
# graduacio_aixovall

## Enviament d'emails amb CDmon

El projecte envia emails des de PHP amb PHPMailer i SMTP autenticat de CDmon. No es fa cap enviament des de JavaScript.

### Instal·lar PHPMailer

En un entorn amb accés a Packagist executa:

```bash
composer install
# o bé, si encara no tens composer.json:
composer require phpmailer/phpmailer
```

A CDmon has de pujar també la carpeta `vendor/`, perquè conté PHPMailer i l'autoload de Composer.

### Configuració SMTP

Edita les constants SMTP de `api/config.php` amb les dades reals del compte de correu creat a CDmon. `SMTP_USER` ha de ser l'email complet i `SMTP_PASS` ha de ser la contrasenya real del compte de correu; no la posis mai en JavaScript.

### Provar l'email sense reservar butaques

Quan PHPMailer estigui instal·lat i la contrasenya SMTP sigui correcta, pots provar només l'enviament amb:

```bash
curl -X POST https://EL_TEU_DOMINI/api/test-mail.php \
  -H 'Content-Type: application/json' \
  -d '{"email":"el-teu-email@example.com","student_name":"Prova Email","nia":"000000A"}'
```

En local, si tens el servidor PHP arrencat, canvia la URL pel teu host local. Aquest endpoint no escriu a la base de dades ni reserva butaques; només envia un email de prova amb butaques fictícies.

Si el navegador mostra `Unexpected end of JSON input`, normalment vol dir que PHP ha fallat abans de retornar JSON. El projecte carrega `api/local_config.php` automàticament només en local (`localhost`, `127.0.0.1`, servidor PHP integrat o `APP_ENV=local`) i `api/config.php` en producció. Per CDmon configura `api/config.php` amb les credencials reals del hosting; per proves locals mantén `api/local_config.php` amb les credencials locals de MySQL.

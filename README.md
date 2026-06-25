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
6. Edita `secrets/db.php` amb les dades locals de connexió:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`

7. Edita `secrets/mail.php` amb les dades SMTP:
   - `SMTP_HOST`
   - `SMTP_PORT`
   - `SMTP_USER`
   - `SMTP_PASS`
   - `SMTP_FROM_EMAIL`
8. Obre el projecte al navegador:

```txt
http://localhost/graduacio-aixovall-2026/web/
```

## Instal·lació a CDMON

1. Crea una base de dades MySQL des del panell de CDMON.
2. Obre phpMyAdmin i importa `sql/schema.sql`.
3. Edita `secrets/db.php` amb les dades reals de connexió:
   - `DB_HOST`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`

4. Edita `secrets/mail.php` amb les dades SMTP:
   - `SMTP_HOST`
   - `SMTP_PORT`
   - `SMTP_USER`
   - `SMTP_PASS`
   - `SMTP_FROM_EMAIL`
5. Puja el contingut de `web/` dins del directori `web/` del hosting.
6. Mantén `secrets/` al mateix nivell que `web/`, fora del directori públic.
7. Obre `index.html`.

## Fitxers SQL

- `sql/schema.sql`: esquema complet per a una instal·lació nova. És el fitxer que cal importar si la base de dades encara és buida.
- `sql/add-student-fields.sql`: migració per a bases de dades antigues que ja tenien la taula `reservations`, però encara no tenien els camps `student_name` i `student_email`.

Per tant, tots dos fitxers són vàlids, però tenen usos diferents. En una instal·lació nova, n'hi ha prou amb `sql/schema.sql`.

## Ajustar seients bloquejats

Els bloquejos estan a `web/assets/js/config.js`:

- `BLOCKED_SEATS`: autoritats, alumnes graduats i docents.
- `ACCESSIBLE_SEATS`: seients PMR marcats en blau.

Format d’un seient:

```txt
ZONA-FILA-SEIENT
B-2-1
E-20-8
```

## Notes

- Cada NIA pot reservar màxim 5 seients.
- La base de dades impedeix que dues persones reservin el mateix seient.
- Les zones del mapa s’han simplificat en A, B, C, D i E.
# graduacio_aixovall

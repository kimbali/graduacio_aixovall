# Reserva de seients · Graduació Aixovall 2026

Web senzilla amb HTML, CSS, JavaScript i PHP + MySQL.

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

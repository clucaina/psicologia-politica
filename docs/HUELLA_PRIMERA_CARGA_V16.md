# Fuente consolidada V16 para primera carga

- **Fecha:** 2026-10-08.
- **Archivo preparado localmente:** `PSICOLOGIAPOLITICA_V16_PREPRODUCCION_AUDITADA.zip`.
- **SHA-256 del ZIP:** `64138804aebb70af5520c3d3509489dab0ea3b10c36a7435577bb13c9df66d3f`.
- **Entradas verificadas:** 293 (incluyendo manifest `RELEASE_CHECKSUMS.json`).
- **Destino Git:** `clucaina/psicologia-politica`, rama `preproduccion/auditoria-2026-10-08`.
- **No subir todavía a `main`.** No usar Deploy ni sobrescribir MySQL.

Cambios consolidados respecto a la fuente V16 original:
1. GA4 `G-D7KPSFPMM5` incorporado a `analytics.js` y `_private/pages.php`, con CSP.
2. Fixture de `tests/qa_cli.php` actualizado con metadatos de comparabilidad.
3. Prueba adicional `tests/qa_integracion_ga4.py` (16 comprobaciones locales).
4. Se ha **retirado** `database/migrations/20261004_001_T19_REFERENCE.sql` por contener una sentencia DELETE, y se ha reforzado `.gitignore` para ignorar todos los SQL salvo `database/migrations/*.schema.sql`.
5. No se incluyen `_private/config.php`, volcados de base de datos, credenciales, sesiones ni uploads personales.

Requisito posterior: el contenido vivo del blog, los comentarios, revisiones, cuentas y el seguimiento instalado en Hostinger deben preservarse con backup actual independiente. **La primera carga a GitHub aún no se ha realizado.** Este archivo es una huella de procedencia; no contiene el ZIP ni da acceso a él.

**Fe de erratas:** el ZIP V16 previamente preparado usaba `/assets/analytics.js`. La instalación real usa `/analytics.js` en raíz. No desplegar aquel ZIP sin regenerarlo y repetir pruebas; no se ha aplicado a Hostinger.

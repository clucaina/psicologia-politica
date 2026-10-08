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

## Nueva candidata corregida — 8 de octubre de 2026

El artefacto antiguo descrito arriba queda **SUPERADO / NO DESPLEGAR** por la revisión de ubicación GA4. La candidata más reciente se ha construido desde `pp_v16_work` sin enviar a producción:

- Nombre: `PSICOLOGIAPOLITICA_V16_STAGING_GA4_RAIZ_20261008.zip`
- SHA-256 exacto: `01229454c93876c9be22595e8f22571fd2118c4fde4e9010267400f4b87fa4ad`
- Tamaño ZIP: **16.375.053 bytes**.
- **291 entradas** con manifest `RELEASE_CHECKSUMS.json`.
- GA4: `/analytics.js` en raíz de `public_html`, no en `/assets/`.
- `_private/pages.php` corregido para cargar `/analytics.js` y librería gtag, conservando `G-D7KPSFPMM5`.
- Las 16 verificaciones de integridad GA4, la batería PHP principal, QA semántica V16, QA estática V16, pruebas de navegador V16 768/1280px, 45 JSON, 53 JS y 27 PHP han pasado en entorno local (NO staging real).
- Descarta `_private/config.php`, cualquier SQL salvo las dos migraciones `*.schema.sql`, todos los ficheros sensibles y el archivo SQL destructivo T19.
- **Su ZIP todavía NO está en GitHub y no debe utilizarse para desplegar directamente en Hostinger.** Se requiere integrar el código completo en rama, mantener intacta la MySQL viva y probar rollback real.

### Respaldo del artículo y la portada finalizado en GitHub
- Rama de retención independiente `respaldo/decreto-mari-carmen-2026-10-08`.
- Portada JPEG comprobada por GitHub Actions (20.461 bytes), SHA-256 `12c45091feee01a11355310cef09ae83cd6262cf4271fad718f0814acfc289d3`.
- Ocho HTML públicos archivados con hashes. El artículo nuevo **no** está en el SQL anterior.
- Workflow público de humo para artículo, portada y GA4 ya obtuvo resultado **success** (`actions/runs/37760985422`).

# Recuperación del seguimiento y plan V16 (8/10/2026)

## Configuración existente, recuperada de la instalación original (04/10/2026)

- **Google Analytics 4 / gtag.js: `G-D7KPSFPMM5`.**
- En **`public_html/_private/pages.php`**, método `pp_render_page()`: inserción de la librería externa en `<head>`, referencia a `/assets/analytics.js` y CSP que autoriza `www.googletagmanager.com` para scripts y dominios `google-analytics.com` para conexión.
- En **`public_html/assets/analytics.js`**: `dataLayer` y `gtag('config', 'G-D7KPSFPMM5')`.
- Se diagnosticó anteriormente un `404` de `/assets/analytics.js`; la ruta correcta era `assets/` dentro de `public_html` (NO `_private/assets.json`). La usuaria confirma en conversación actual que después quedó funcionando.
- **GTM-... no confirmado**. No duplicar Google Analytics con otra instalación de GTM; no añadir una etiqueta distinta sin identificarla.

## V16 encontrada y validada localmente

La Library contiene `PAQUETE_SABADO_MASTER_V16_PREPRODUCCION.zip`, de fecha 2026-10-08; **V16 sustituye V15**. V16 incluye controles de concurrencia del blog, autosave v3 por cuenta/entrada, metadatos de comparabilidad para Conflictómetro, controles de años, valores negativos, revisión editorial y conjuntos de datos de preparación.

Se ha generado copia de desarrollo local V16, incorporando el ID GA4 al CSP/head y `assets/analytics.js`. Lint local tras integración: **27 PHP, 53 JS, 45 JSON correctos**; `tests/qa_semantic_v16.php`, `tests/qa_v16.py`, `tests/qa_browser_v16.py` correctos en el entorno local. Se corrigió un fixture antiguo de `tests/qa_cli.php` para proporcionar los metadatos que V16 exige; ya pasa sin desactivar la validación. **Estos cambios a `pages.php` aún no se han subido al GitHub, ni se han aplicado a Hostinger.**

## No GO producción

1. La web pública contiene el artículo «Decreto Mari Carmen», slug `decreto-mari-carmen-0071e38b`, y otras seis entradas. Se han preservado en la rama de auditoría copias de texto público, que NO preservan comentarios, revisiones ni edición SQL.
2. El volcado SQL antiguo no incluye este artículo. Es obligatorio hacer backup **ACTUAL** de MySQL antes de cambiar cualquier archivo o base.
3. El directorio `public_html` de Hostinger contiene una modificación manual a GA4 que no figura en archivos antiguos; comparar instalación real para evitar duplicados.
4. El autosave de borradores antiguos v2 en localStorage puede requerir recuperación antes de reemplazar el código: V16 **no migra automáticamente** borradores entre usuarios.
5. No consta staging remoto ni pruebas de MariaDB, acceso real, roles, editor de blog, importación y rollback.
6. **GitHub hoy contiene los respaldos, documentación, workflow y un JS de analítica; no contiene todavía el árbol completo V16.** No conectar desplegado automático de Hostinger hasta completar la subida de código, pruebas y autorización.

## Reglas

- Prohibido desplegar en Hostinger durante la preparación.
- Código y migraciones aditivas sí; `_private/config.php`, contraseñas, sesiones, `.env`, volcados SQL, uploads y datos de usuarios NO.
- Consolidar mejoras en una única versión; no pedir al usuario repetir descargas de paquetes anteriores.

# Instrucciones para completar Psicología Política desde Work o un agente GitHub

**Objetivo:** cargar la última versión V16 sin descargas manuales ni modificar la web publicada prematuramente.

## Fuentes disponibles

- **Biblioteca de ChatGPT**: `PAQUETE_SABADO_MASTER_V16_PREPRODUCCION.zip` (ZIP externo). Dentro de él se encuentra `PSICOLOGIAPOLITICA_REPO_PATCHED_V16.zip` (**ZIP interno del árbol de código**, 289 entradas originales). No subir el ZIP externo como único archivo al repositorio.
- **Copia candidata corregida en la conversación**: `PSICOLOGIAPOLITICA_V16_STAGING_GA4_RAIZ_20261008.zip` (291 entradas, con manifest de hashes), SHA-256 `01229454c93876c9be22595e8f22571fd2118c4fde4e9010267400f4b87fa4ad`. Si no se puede acceder a ella, extraer el ZIP interno anterior y aplicar los cambios de QA/GA4.
- **GitHub destino**: `clucaina/psicologia-politica`, rama `preproduccion/auditoria-2026-10-08`. `main` NO está desplegable y no debe fusionarse todavía.
- **Respaldo independiente e inmutable por convención**: rama `respaldo/decreto-mari-carmen-2026-10-08`; **NO BORRAR NI MODIFICAR ESTA RAMA**.

## Importación segura de código

1. Leer `docs/CONSERVACION_PRODUCCION_2026-10-08.md`, `preservacion/2026-10-08/README.md` y `docs/HUELLA_PRIMERA_CARGA_V16.md`.
2. Extraer el árbol V16 de origen y verificar que tiene `index.php`, `_private/pages.php`, `_private/editorial.php`, `assets/site.css`, `database/`, `tests/`.
3. Excluir `_private/config.php`, `uploads/`, sesiones, backups MySQL, archivos `.env`, dumps `*.sql` y el SQL antiguo destructivo `20261004_001_T19_REFERENCE.sql`. Solo permitir **dos migraciones `database/migrations/*.schema.sql`**.
4. GA4: ID real **`G-D7KPSFPMM5`**. El JS correcto debe ir en raíz **`/analytics.js`**, nunca en `/assets/analytics.js`. Insertar una única referencia a `https://www.googletagmanager.com/gtag/js?id=G-D7KPSFPMM5` y una única referencia a `/analytics.js` en `_private/pages.php`, actualizando CSP. Sin `debug_mode:true` ni evento sintético en la versión final. Consultar `scripts/reconciliar_ga4_v16.py`.
5. No pisar archivos ya archivados en `preservacion/`, ni `.github/workflows/preservar-portada.yml`, `.github/workflows/preservar-blog-html.yml`, `.github/workflows/verificar-publicacion.yml`. Conservar toda su historia.
6. Confirmar lint de los 27 PHP y 53 JavaScript, lectura de 45 JSON, `tests/qa_cli.php`, `tests/qa_semantic_v16.php`, `tests/qa_v16.py`, las suites `qa_browser_v9` a `qa_browser_v16` y `tests/qa_integracion_ga4.py`.
7. Tras cargar todo en **staging**, corregir CI. No fusionar `main` ni configurar Deploy todavía.

## Producción Hostinger — prohibición de sobrescritura

- La publicación **Decreto Mari Carmen** está viva en `/blog/decreto-mari-carmen-0071e38b`. La imagen asociada tiene clave `971a8ade-e871-48ce-85cc-fe4df9048352.jpg`. El JPG original, su SHA y las copias HTML están en `preservacion/2026-10-08`; la rama de respaldo independiente también los contiene.
- En PHP actual, las imágenes subidas viven en **`public_html/uploads/`**, mientras la tabla **`media_assets`** y la tabla **`editorial_posts`** mantienen las referencias. Respaldar **AMBOS** antes de tocar producción.
- Backup **ACTUAL** de MySQL, `public_html`, `uploads`, control de acceso, comentarios y revisión editorial. El SQL antiguo no contiene la entrada nueva; **nunca reemplazar MySQL con él**.
- Probar staging real PHP 8.3/MariaDB con base clonada y sin correo/cuentas productivas, permisos, inicio de sesión, herramientas interactivas, portada, comentarios y rollback.
- Publicar únicamente con aceptación explícita tras pruebas de restauración. GitHub CI de fuente completa falla actualmente porque aún no se ha subido todo V16: **no es permiso para saltarse pruebas**.

Esta documentación permite retomar la importación directamente desde el repositorio conectado, sin repetir al usuario las instrucciones, contraseñas o descargas.

## IMPORTANTE: separación entre copias privadas y raíz HTTP

La rama de auditoría contiene `preservacion/` porque se preparó antes de cargar el código V16. **NO se debe copiar esa carpeta a `public_html`**, ni publicar manifiestos, HTML históricos, diagnósticos o copias de artículos como rutas adicionales. Su respaldo definitivo está en la rama privada independiente `respaldo/decreto-mari-carmen-2026-10-08`.

Cuando se prepare la rama definitiva del código para despliegue, usar un árbol de aplicación limpio que incluya únicamente archivos necesarios de PHP, JS, CSS, recursos públicos y migraciones seguras. Mantener documentación y copias fuera del directorio desplegado mediante selección explícita de archivos/paths. No asumir que Hostinger hace esa separación por sí solo.

**HTML real ya comprobado:** `<script async src="https://www.googletagmanager.com/gtag/js?id=G-D7KPSFPMM5">` y `<script src="/analytics.js">` aparecen en la respuesta HTML publicada del artículo. El canario ampliado de GitHub Actions pasó en `actions/runs/37761581083`. Esto verifica inserción del código, no eventos recibidos en GA4 ni consentimiento.

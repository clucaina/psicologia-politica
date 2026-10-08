# Estado de producción y cortafuegos de despliegue · 2026-10-08

**Producción:** https://psicologiapolitica.es/ (Hostinger, PHP 8.3, MySQL/MariaDB). **NO DESPLEGAR / NO importación SQL destructiva.**

## Elementos que NO pueden perderse

1. Publicación original de «Decreto Mari Carmen», slug `decreto-mari-carmen-0071e38b`, accesible en https://psicologiapolitica.es/blog/decreto-mari-carmen-0071e38b . Copia textual pública preservada en `preservacion/2026-10-08/decreto-mari-carmen-publicado.md`. Es una transcripción recuperable, NO su registro editable ni su historial/autoria: exportar filas originales de `editorial_posts` y tablas asociadas desde MySQL actual antes de migrar. No recrear duplicado.
2. Google Tag Manager añadido manualmente a `public_html` con posterioridad a la copia ZIP antigua. La etiqueta **no figura en la copia PHP de origen ni en V15**. Verificar ID, ubicación del script en `<head>`, `<noscript>` tras apertura de `<body>`, consentimiento y funcionamiento con Tag Assistant en la página actual, antes de introducirlo en el código versionado. No inventar el ID.
3. Usuarios, credenciales en hosting, roles de administrador, entradas, noticias, comentarios, uploads, adjuntos e imágenes de `public_html/uploads`. No sobrescribir ni borrar; realizar backup real de archivos y MySQL en la cuenta propietaria antes de pruebas.
4. Rutas y contenidos de la web pública. El blog muestra 7 entradas en extracción pública; no confundir con el total MySQL. La ruta de Mari Carmen muestra símbolos de Markdown literales y requiere corrección sin reescritura ni pérdida de texto.
5. El paquete V15 de otro chat es **preproducción**, con 282 archivos; incorpora QA local, datos preparados y posibles cambios que NO están desplegados. El ZIP antiguo `01_SOLO_PUBLIC_HTML_PSICOLOGIAPOLITICA.zip` tiene 169 entradas; el SQL `02_SOLO_PHPMYADMIN_PSICOLOGIAPOLITICA.sql.zip` no contiene la entrada «Decreto Mari Carmen». Nunca usar SQL antiguo como reemplazo de producción.

## Evidencia pública a revisar en staging

- `/`: portada y noticias visibles.
- `/blog`: entradas específicas, incluida «Decreto Mari Carmen».
- `/quienes-somos`: cinco miembros.
- `/conflictometro`, `/herramientas/analizar-texto`, `/herramientas/datos-espana`, `/herramientas`, `/contacto`: el HTML extraído devuelve contenido introductorio genérico de noticias; **no demuestra por sí solo el comportamiento con JS activado**. Probar UI real en navegador.
- `/mapa-espana`: 404 público al 08-10-2026, no publicado aún.
- Verificar autenticación, editor de blog, permisos, vista móvil, carga de mapas y conteos NPL con MariaDB real.

## Puertas obligatorias

- Cargar **V15 en una rama de staging**, sin unirla a main automáticamente.
- Excluir `_private/config.php`, tokens, claves, `.env`, copias SQL de producción, logs y datos personales del repositorio.
- Backup fechado verificable de producción: archivos + base real + uploads + configuración analítica (no guardar secretos en Git).
- Comparar código vivo actual con V15; integrar GTM sin crear dobles etiquetas.
- Migraciones aditivas e idempotentes con rollback probado; no `DROP`, `TRUNCATE`, ni sobreescribir filas editadas/publicadas.
- Ejecutar CI, staging aislado, pruebas multiusuario, regresión editorial, restauración de backup, accesibilidad y Tag Assistant.
- Requiere GO expreso del titular antes de apuntar Git de Hostinger a `public_html` o ejecutar despliegue.

**Estado:** AUDITORÍA / PREPRODUCCIÓN. Ninguna operación aquí modifica Hostinger.

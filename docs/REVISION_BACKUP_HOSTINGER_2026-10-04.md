# Auditoría de copia Hostinger recibida — 08/10/2026

**No publicar este documento ni el archivo de origen fuera del repositorio privado.**

## Archivo recibido

- Nombre: `u789705224.20261004125146.tar.gz` (respaldo de **4 de octubre de 2026**, no copia actual de 8/10).
- Tamaño aprox.: 48 MiB comprimidos, 244.546.515 bytes de contenido, 390 entradas, de las cuales 372 son archivos.
- Carpeta: `domains/psicologiapolitica.es/public_html/`.
- Archivo de PHP, JS, estilos, cartografía, medios y configuración local presente.
- **Contiene `_private/config.php` (secreto), 57 archivos de sesiones y un SQL de ~190 MB** en `_private/_import_pp/PSICOLOGIAPOLITICA_DATOS_SOLO.sql`, fechado el **03/10/2026**. Por ello **NO SUBIR EL TAR A GITHUB ni situarlo en un directorio público**. No mostrar credenciales ni contenido privado en logs.
- Ese SQL es un archivo histórico dentro del árbol de archivos; **no demuestra un respaldo de la MySQL viva del 08/10**, ni contiene el slug `decreto-mari-carmen` en una búsqueda binaria.
- `public_html/analytics.js` ya estaba presente en ese respaldo y `_private/pages.php` aparece modificado el 04/10. Por tanto es falso que el archivo JS de GA4 solo existiera en `assets/`.

## Portada Mari Carmen

- En el respaldo antiguo está el archivo `public_html/uploads/971a8ade-e871-48ce-85cc-fe4df9048352.jpg`, de 20.727 bytes, modificado el 04/10/2026, SHA-256 `4b3c3197008dca0f5fd543ad635e59b0579d152e713287428375c67c1ddb230d`.
- La imagen servida y preservada en GitHub el 08/10, de 20.461 bytes, SHA-256 `12c45091feee01a11355310cef09ae83cd6262cf4271fad718f0814acfc289d3`, **no coincide binariamente**. No reemplazar una por otra sin verificar; conservar ambas como variantes históricas, no como evidencia de que la portada actual haya desaparecido.
- Archivo multimedia nuevo y HTML de siete artículos respaldados en la rama privada `respaldo/decreto-mari-carmen-2026-10-08`.

## BLOQUEO DE PUBLICACIÓN

1. Obtener copia posterior a la última edición del artículo **del MySQL actual** y de **archivos actuales** (especialmente `uploads/`, `_private/pages.php`, `analytics.js`). Guardar en lugar privado, nunca GitHub.
2. Verificar que el export actual contiene registro editable de `editorial_posts`, relaciones `media_assets`, comentarios, revisiones, lectores y datos NPL.
3. Montar un staging con base aislada y comprobar restauración real antes de conectar Hostinger Git a `public_html`.
4. `preproduccion/v16-runtime-limpio` es rama candidata de código, **no desplegar** sin validar los tres puntos anteriores.

**Acciones ejecutadas en esta auditoría:** lectura del TAR y comprobaciones de nombres, fechas, SHA y términos. Ninguna restauración, modificación del archivo original ni conexión a producción.

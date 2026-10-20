# Protección de la publicación «Decreto Mari Carmen»

**Fecha de captura:** 2026-10-08 (UTC). **Repositorio privado.** Esta carpeta no representa un volcado de MySQL.

## Evidencia conservada y comprobada

| Elemento | Archivo en el repositorio | Integridad |
|---|---|---|
| Imagen de portada JPEG original servida en el blog | `decreto-mari-carmen-portada-original.jpg` | **20.461 bytes**, SHA-256 `12c45091feee01a11355310cef09ae83cd6262cf4271fad718f0814acfc289d3` |
| Texto extraído del artículo | `decreto-mari-carmen-publicado.md` | Copia de lectura; no es el registro editable |
| HTML público exacto del artículo | `html-publicado/decreto-mari-carmen-0071e38b.html` | **248.724 bytes**, SHA-256 `f5ea85a4a94bcaa467dd1d8b56a754dadc84f530cc55464e418e1f37dbd18745` |
| Páginas públicas restantes del blog | `html-publicado/*.html` | 7 artículos más portada de blog: 8 documentos registrados en `html-publicado/manifest.json` |
| Copias legibles de las otras seis publicaciones | `blog/*.md` | Texto extraído de páginas públicas |

**URL original portada:** `https://psicologiapolitica.es/api/media/971a8ade-e871-48ce-85cc-fe4df9048352.jpg`.

**URL del artículo:** `https://psicologiapolitica.es/blog/decreto-mari-carmen-0071e38b`.

**Pruebas:** GitHub Actions `preservar-portada.yml` descargó el JPG, validó `image/jpeg`, verificó límites de tamaño y realizó un commit binario; `preservar-blog-html.yml` archivó y verificó las 8 páginas HTTP con manifiesto SHA-256.

## Límites expresos

- La copia HTML conserva la respuesta pública generada (incluye la imagen por referencia), no garantiza que todos sus recursos CSS/JS estén incluidos ni que el sitio pueda ejecutarse fuera de Hostinger.
- **NO se han preservado las filas actuales de MySQL** (artículos editables, revisiones, comentarios, usuarios, control de roles, cambios locales y borradores). El volcado SQL antiguo es insuficiente: no contiene la entrada de Mari Carmen.
- **NO desplegar la base SQL antigua**, ni sobreescribir `public_html/uploads` o `_private/config.php`.
- La imagen original se conserva como copia de respaldo en el repositorio privado. Antes de hacerla pública fuera del sitio debe revisarse su licencia o permiso de reutilización.
- Un cambio de código en GitHub nunca debe eliminar archivos de media ni contenido de producción; restauraciones de MySQL y uploads requieren prueba independiente.

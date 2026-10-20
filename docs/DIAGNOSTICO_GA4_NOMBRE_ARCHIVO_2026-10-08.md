# Auditoría corregida de GA4 — 08/10/2026

## Hechos verificados en la web pública (peticiones de solo lectura)

- `https://psicologiapolitica.es/analytics.js`: **200 OK**, contenido JavaScript GA4 `G-D7KPSFPMM5`, con `debug_mode: true`.
- `https://psicologiapolitica.es/assets/analytics.json`: **200 OK**, conserva también JavaScript GA4 y un evento automático `prueba_psicologiapolitica`.
- `https://psicologiapolitica.es/assets/analytics.js`: **404**. Eso es coherente con que no hay archivo JS en esa carpeta: **NO demuestra un fallo de Google Analytics**.
- La usuaria ha aclarado la ubicación real del JS: `public_html/analytics.js` (raíz); la URL correspondiente es `/analytics.js`.

## Corrección de interpretación anterior

La hipótesis anterior de que había que renombrar `assets/analytics.json` a `assets/analytics.js` **era equivocada**: el JavaScript correcto ya existe como `public_html/analytics.js`. No renombrar, mover ni sobrescribir archivos en Hostinger a partir de esa hipótesis. Se conserva el duplicado `.json` mientras no se haya verificado si se está usando; no modificar producción.

## Integración en GitHub (rama de preproducción, no en main)

- Código GA4 de destino en `/analytics.js` (sin `debug_mode` permanente).
- Copia del código actual de `/analytics.js`: `preservacion/2026-10-08/analytics-js-raiz-publicado.js`.
- Copia de `/assets/analytics.json` (incluye evento de prueba): `preservacion/2026-10-08/analytics-json-capturado.js`.
- Referencias `/assets/analytics.js` retiradas de script, tests y CI; la copia temporal `assets/analytics.js` introducida erróneamente en staging fue eliminada.
- **Pendiente** verificar cómo lo referencia el HTML generado actualmente por `_private/pages.php`: el hecho de obtener 200 en `/analytics.js` no prueba por sí solo que las páginas lo estén cargando, que no exista una doble inicialización ni que GA4 registre sesiones correctamente. Comprobar código fuente/Network y GA4 DebugView o Tiempo real.
- No hay confirmación de un contenedor `GTM-...` independiente; `G-D7KPSFPMM5` es el identificador de Google Analytics 4.

## Prohibición de despliegue hasta revisar

No tocar `public_html`, MySQL, artículos, usuarios, configuraciones ni GA4 real sin respaldo actual y comprobación de integración. `main` y Hostinger permanecen intactos.

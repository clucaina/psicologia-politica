# Causa localizada del 404 en Google Analytics — 08/10/2026

La usuaria ha compartido **captura directa de Hostinger** donde la ruta del editor indica `public_html > assets > analytics.json`, aunque el archivo contiene JavaScript (véase `preservacion/2026-10-08/analytics-json-capturado.js`).

## Diagnóstico

- La web intenta cargar `/assets/analytics.js`, confirmado por el 404 del recurso público comprobado anteriormente.
- **El fichero editado está llamado `analytics.json`, no `analytics.js`**. Esto es una explicación directa y probable del 404; falta comprobar si también existe `assets/analytics.js` en el alojamiento y si `pages.php` sigue apuntando a él.
- El identificador real es `G-D7KPSFPMM5` (Google Analytics 4). No sustituir por otro ni crear doble contenedor GTM.
- El código mostrado por la usuaria activa `debug_mode: true` e introduce un evento `prueba_psicologiapolitica` en cada ejecución. Son elementos de **diagnóstico**, no adecuados como tracking permanente porque añadirían eventos de prueba.
- El archivo `assets/analytics.js` del repositorio de staging contiene una versión de producción sin el evento sintético. La copia fiel con debug queda preservada aparte para contraste.

## Corrección en Hostinger (a realizar solo con acceso autorizado y comprobación previa)

1. En el Administrador de Archivos, abrir `public_html/assets` y confirmar que el archivo se llama `analytics.json`.
2. Comprobar si ya existe `analytics.js`. Si existe, abrir ambos y compararlos; **no sobrescribir sin verificar**.
3. Si `analytics.js` no existe, **renombrar** `analytics.json` a `analytics.js` (o crear un nuevo `.js` con el contenido aprobado y guardar el JSON original como respaldo).
4. En `public_html/_private/pages.php`, comprobar que el HTML añade `https://www.googletagmanager.com/gtag/js?id=G-D7KPSFPMM5` y `/assets/analytics.js`, que la CSP autoriza ese JS y el envío de eventos, y que no se duplican etiquetas.
5. Visitar `https://psicologiapolitica.es/assets/analytics.js` y confirmar HTTP 200 y contenido JavaScript, luego Tag Assistant/GA4 DebugView (con consentimiento pertinente), sin depender solo del acceso al archivo.
6. Tras verificación, eliminar `debug_mode: true` y el evento automático `prueba_psicologiapolitica` de la versión en producción; el diagnóstico quedará versionado en el respaldo.

**Ninguna de estas acciones ha sido realizada en el Hostinger real por el chat.** El trabajo permanece en rama de preproducción y no toca la web ni la base de datos.

## Verificación externa posterior a las capturas

Petición pública de solo lectura (08/10/2026):
- `https://psicologiapolitica.es/assets/analytics.json`: **200, contiene el JavaScript con GA4 `G-D7KPSFPMM5`, `debug_mode:true` y evento automático de prueba**.
- `https://psicologiapolitica.es/assets/analytics.js`: **404**.

Esto confirma que el archivo fue publicado con la extensión equivocada y está accesible por `.json`. Para ejecutar el JavaScript referenciado como `/assets/analytics.js`, hace falta crear o renombrar el archivo físico a `.js` en el directorio correcto, y comprobar que `_private/pages.php` lo enlaza desde el HTML. El código recuperado queda conservado en GitHub y no altera la web pública.

# Analítica GA4

ID confirmado: `G-D7KPSFPMM5`

Archivos de producción identificados en la conversación del 4 de octubre: `public_html/_private/pages.php` para la cabecera HTML/CSP; `public_html/analytics.js` para la inicialización gtag. La versión pública añadida manualmente no debe sobrescribirse sin comparar los archivos actuales. Nunca instalar un segundo rastreador en paralelo.
\nConfirmación HTTP del 08/10: `/analytics.js` responde 200 desde la raíz, mientras `/assets/analytics.js` no existe. Conservar también el archivo público distinto `/assets/analytics.json` como respaldo, sin usarlo para ejecutar la medición.\n
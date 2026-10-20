# Sprint V12 · cambios y QA

2026-10-08 · preparación offline sobre V11. Producción no tocada.

1. Auditoría pública read-only de 12 rutas: seis con extracto genérico de noticias fuera de la sección Noticias; Blog «Decreto Mari Carmen» aún con Markdown escapado. No confundir HTML extraído con prueba de fallo de la SPA.
2. Ingesta: bloqueo MySQL compartido cron/manual, revalidación DOI/URL/título bajo bloqueo, normalización de títulos, RSS + Atom con límites de tamaño y allowlist heredados. Validación real de feeds pendiente.
3. Observatorio: anti-race para respuestas a preguntas, ficha de evidencia con metadatos y límites, UI móvil 320px, rechazo de preguntas sobre población no cubierta.
4. Contacto: fallback SSR específico, descripción SEO y advertencia de privacidad.
5. NPL: conversor offline JSON-stat 2.0 y test multidimensional (incluye cero, null, flags), catálogo de fuentes oficiales y auditoría de las 521 observaciones; ninguna cifra nueva.
6. CI: incorpora tests V12, Playwright y auditoría NPL. No se ha ejecutado en GitHub.

**Límites:** no MySQL, no staging, no integración SPA, no publicación, no verificación de RSS/Atom PHP por ausencia local de SimpleXML. El estado es NO-GO.

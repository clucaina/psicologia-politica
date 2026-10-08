# Handoff GPT Work · Psicología Política V12 · PREPRODUCCIÓN

**Única base de código:** `PSICOLOGIAPOLITICA_REPO_PATCHED_V12.zip` dentro del paquete maestro. No reconstruir V4–V11. **Producción no modificada. NO-GO** hasta pruebas en staging, MySQL y aprobación expresa. GitHub privado como fuente de verdad, Hostinger PHP 8.3/MySQL, sin Cloudflare/Workers/D1/R2.

## Implementado en V12 (copia segura)

- Ingesta editorial: el cron y la importación manual comparten bloqueo MySQL; al confirmar se vuelve a leer el índice de duplicados dentro del bloqueo. Dedupe de DOI/URL y títulos normalizados. Parser RSS/Atom; prueba de Atom pendiente en runtime con SimpleXML (CI lo instalará).
- Observatorio España: «Pregunta a España» ignora respuestas tardías de consultas anteriores; muestra fuente, unidad, población, periodo, método, fecha de revisión, límites y enlace primario. Cero válido; null como ausencia. Test Playwright 320 px con respuestas API simuladas.
- Motor semántico: no sustituye indicadores generales por tasas de pobreza infantil o paro juvenil, ni compara si falta el segundo territorio. Mantiene salario por sexo y abandono 18–24 cuando procede.
- SEO/SSR: contenido específico para Contacto y aviso de no enviar datos clínicos/terceros. V11 ya contenía los SSR de herramientas y render Markdown seguro, **todavía no desplegados**.
- NPL: conversor **offline** Eurostat JSON-stat 2.0 → lote NPL en revisión, con SHA-256 del original, fecha real aportada por operador, selección explícita de dimensiones, status de fuente, null≠0 y sin interpolación. No descarga, importa ni publica automáticamente. Registro de 4 fuentes oficiales y sus gates.
- QA cruzada: 521 observaciones únicas preparadas; 36 derivadas, 237 fechas de consulta pendientes, 19 valores AROPE coincidentes entre dos fuentes (no evidencia independiente). **0 observaciones nuevas V12**.

## Orden de ejecución en Work

1. Importar ZIP V12 a GitHub privado y activar QA. Validar SimpleXML y casos RSS/Atom en PHP 8.3, corregir cualquier incompatibilidad; no ejecutar migraciones en producción.
2. Crear staging Hostinger y MySQL aislados, con copias sintéticas o anonimizadas. Probar importador NPL, rollback, bloqueo de revisión, migraciones y backup/restore. La importación de datos reales debe permanecer en revisión hasta verificar fuente, fecha y metodología.
3. Integrar `docs/observatorio-espana-v8.*` en SPA principal; resolver rutas de assets, CSP, responsividad, SSR/SEO, accesibilidad y sincronización de URL. Probar endpoints reales con MySQL, indicadores publicados y vacíos.
4. Probar Blog autenticado con dos autores y lector: `/blog/escribir?edit=<id>`, autosave por artículo, 409 por concurrencia, perfil/foto, Markdown, revisión, permisos y CSRF.
5. Ejecutar ingestión RSS real en staging: cron vs importación manual concurrentes, DOI repetidos, enlaces Atom, feeds caídos, títulos similares y `review_only`. Añadir revisión editorial humana antes de publicar.
6. Probar «Pregunta a España» con poblaciones no compatibles, citas de fuentes, años sin datos, cero, duplicados y latencia real. Integrar límites/metodología en UI principal.
7. Comprobar los 12 hallazgos del audit público `database/PUBLIC_AUDIT_V12.json`; verificar HTML sin JS, títulos, descripciones, sitemap, imágenes y lectores de pantalla.
8. Validar seguridad, WAF/429, caché, rendimiento, accesibilidad WCAG 2.2 AA, móvil, CI, migraciones, despliegue reversible, backups y plan de rollback. Emitir GO/NO-GO; **no publicar sin autorización**.

## Gates de datos

Los 521 registros son lotes preparados, no nuevas estadísticas publicadas. No crear series regionales a partir de una cifra nacional. No tratar condenas como delitos registrados, encuestas como diagnósticos, porcentajes como conteos ni correlaciones como causas. Mantener fuentes, URL, periodo, población, geografía, estado provisional, fecha real de consulta y linaje de derivados. No inventar las 237 fechas pendientes.

## Estado de validación

PHP/JS lint y JSON parseados; QA semántica V10–V12, navegador V9–V12 (API simulada), decodificador Eurostat sintético y auditoría NPL cruzada: PASS. **NO probado**: parser Atom con extensión SimpleXML en este contenedor, feeds reales HTTP desde Hostinger, MySQL de staging, SPA integrada, Blog con dos usuarios reales, imágenes finales, despliegue ni producción.

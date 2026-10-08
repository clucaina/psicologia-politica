# GPT Work handoff · Psicología Política V13 · PREPRODUCCIÓN

**Código único**: `PSICOLOGIAPOLITICA_REPO_PATCHED_V13.zip` dentro del paquete maestro. No reconstruir versiones anteriores. **No se ha desplegado ni autorizado desplegar.** GitHub privado y Hostinger PHP 8.3/MariaDB siguen pendientes de integración real.

## Cambio de mayor impacto: mapa 2D accesible como ruta PHP real

- `GET /mapa-espana` sirve ahora una página HTML autónoma y progresiva, sin depender del router React. `index.php` responde a esta ruta antes de abrir MySQL o construir el bootstrap SPA.
- Recursos reales en `/assets/observatorio/{mapa-espana.css,mapa-espana.js,mapa-espana.svg}`; no se intenta cargar archivos desde `/docs`, directorio bloqueado por Apache.
- Geometría SVG NUTS2: 17 CCAA + Ceuta/Melilla. Incluye teclado, selector, búsqueda con botón, zoom +/−/reset y desplazamiento del mapa dentro de su tarjeta, comparaciones, series, ranking y pregunta basada en API local.
- El mapa **no muestra números preparados o simulados**: usa exclusivamente `/api/v1/npl` y endpoints publicados/revisados. Si MySQL no devuelve valores revisados, muestra ausencia de datos, nunca cifras ficticias.
- Se enlaza desde los fallbacks SSR de Inicio, Herramientas, Conflictómetro y Datos de España. **Sigue en `noindex` y excluido de robots** hasta validación con datos reales y autorización editorial. La SPA React original no se ha sustituido.

## Hallazgo de integridad corregido

- El backend anterior leía `population`, `measurement_type`, `limits` y `source_url` solo de `npl_observations.data`. Sin embargo, el proceso de publicación exige y almacena esos metadatos **verificados** en `npl_reviews.evidence`. Consecuencia: un mapa podía perder la población, la cita y los límites aun después de la revisión humana.
- V13 añade `r.evidence` a series y comparaciones, prioriza la evidencia revisada, expone `source_locator`, `citation`, `retrieved_at`, `reviewed_at` y conserva `0` como valor legítimo. Si el valor es `NULL`, no numérico o infinito, la API lo representa como ausencia.
- El endpoint `/api/v1/npl/coverage` ya **no cuenta territorios con filas duplicadas** como celdas válidas; informa de territorios ambiguos y de filas redundantes. Requiere prueba SQL real con MySQL 8/MariaDB antes de producción.

## Auditoría de datos reproducible

- `scripts/npl_quality_report_v13.py` valida 521 observaciones preparadas, 521 claves naturales únicas, 36 derivadas y fórmulas de brecha salarial, URLs HTTPS, códigos geográficos, porcentajes y batch IDs.
- Hallazgo nuevo: **327 observaciones carecen de `population` explícita** en su JSON de origen y **237 de `measurement_type` explícito**; además, 237 carecen de fecha `retrieved_at`. **No rellenar por inferencia**. En publicación, el revisor debe registrar población/tipo en `npl_reviews.evidence` y fecha de consulta real. Estos datos no se publican automáticamente.
- 19 coincidencias AROPE entre INE/Eurostat siguen identificadas como solapamiento, no replicación independiente.
- Se añaden 3 candidatas editoriales de fuente académica primaria en `database/T35_CANDIDATAS_EDITORIALES_20261008.json`; **0 nuevas observaciones NPL**. Cada una tiene método, límites, DOI, enlace y brief visual original; ninguna se publica sin revisión.

## QA ejecutada

- `php -l`: 23 PHP sin errores; `node --check`: 51 JS sin errores; 34 JSON parseados.
- Batería previa V8–V12 PASS. Nuevo `php tests/qa_semantic_v13.php` PASS: evidencia revisada prevalece, `0 != null`, campos de fuente y población. `python tests/qa_v13.py` PASS.
- Chromium con API simulada PASS en 320, 375, 768 y 1280 px: 19 territorios, cero, ausencia, zoom, búsqueda, comparación, timeline, Pregunta a España, sin errores JS ni scroll horizontal de página.
- PHP built-in local devolvió 200 y cabeceras CSP/no-store para `/mapa-espana` sin MySQL. **No sustituye a una prueba con Apache/hPanel**.
- **No probado**: endpoints con MySQL real, SQL de cobertura en MariaDB, auth Blog con dos usuarios, editor SPA, feed Atom con SimpleXML, cron HTTP real, WAF/429, staging, GitHub→Hostinger, rollback ni producción.

## Prioridad Work (gastar créditos en implementación/QA, no investigación)

1. GitHub privado como fuente de verdad y staging aislado; backup verificado, persistentes protegidos, QA CI en PR.
2. Ejecutar MySQL de staging y probar SQL de cobertura, evidencia revisada y carga de tandas con 0/null, duplicados y población; no publicar lotes sin revisión.
3. Probar `/mapa-espana` con 19 territorios y datos reales; después integrar experiencia 2D en SPA o mantenerla como página principal enlazada con navegación consistente. Quitar `noindex` solo tras GO.
4. Blog: dos autores, edición por ID, autosave por artículo, 409, firma/foto y perfil, CSRF, revisiones y pruebas de recuperación.
5. Editorial: SimpleXML, RSS/Atom reales, lock de ingesta, cola de revisión, DOI dedupe, imágenes originales, no autopublicar sin control humano.
6. Analizador V2: verificar API→SPA, citas y negaciones, falsas alarmas, veracidad `not_assessed`, enlaces a NPL/mapa.
7. Auditoría real SSR/SEO/caché/429 y móvil; despliegue reversible y release gate final. No tocar producción sin autorización explícita.

**Estado: PREPRODUCCIÓN / NO-GO.**

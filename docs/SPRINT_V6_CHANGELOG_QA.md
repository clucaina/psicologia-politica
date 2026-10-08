# V6 · Sprint técnico autónomo · 2026-10-07

## Estado de ejecución
**Copia local preparada y validada estáticamente. NO desplegada.** Punto de partida: `PSICOLOGIAPOLITICA_REPO_PATCHED_V5_T26.zip` recuperado de Library. El archivo V5 no contenía aún las rutas de importador y migraciones que se habían descrito anteriormente; esta V6 las implementa realmente, con limitaciones indicadas abajo.

## Cambios implementados (código real)

1. **SSR/SEO** `_private/pages.php`: fallback específico para `/conflictometro`, `/herramientas/datos-espana`, `/herramientas/analizar-texto`, `/herramientas`; elimina la enumeración indiscriminada de noticias en estas rutas. Metadescripciones diferenciadas. Render Markdown escapado y seguro, enlaces HTTPS/HTTP validados, listas y encabezados. Autoría enlazada a ficha personal y foto relativa si existe; JSON-LD Person con URL. Estilos accesibles y responsive en `assets/site.css`.
2. **Blog**: consulta de artículos propios por `author_id` tanto email como ID de usuario heredado; mismo criterio para autorización de actualización. No se ha demostrado todavía en producción que esta fuese la única causa del bug. El cliente compilado ya incluye autosave por documento y un wrapper global que añade CSRF a las peticiones `fetch`; no duplicar esas funciones innecesariamente.
3. **NPL T26**: corregidos **76 `geo_code:null`** de CCAA/Ceuta/Melilla; se normalizan a NUTS2 (ES61 Andalucía, ES30 Madrid, etc.). Enriquecidas las 19 features de `geo/spain-communities.geojson` con `nuts2` y añadido `geo/ccaa-code-map.json`. Se mantienen 85 observaciones; ahora 80 tienen cobertura territorial útil (España+19 territorios ×4 indicadores) y 5 son nacionales.
4. **T27 salarios**: `database/T27_SALARIOS_CCAA_2024.json`, 54 observaciones oficiales (España+17 CCAA × 3 grupos de sexo), fuente primaria INE EAES 2024 definitiva publicada 28/05/2026. No inventar Ceuta/Melilla. Media bruta anual descriptiva, NO brecha salarial ajustada ni prueba de discriminación.
5. **Importador de datos** `_private/npl_batch.php`: `preview` sin escritura, `stage` transaccional por chunks de 100, deduplicación por clave natural, comparación con filas preexistentes, checksum, ledger, bloqueo concurrente, revisión humana obligatoria, rollback protegido para filas no revisadas. No publica nada automáticamente. Soporta T19/T26/T27.
6. **Migraciones** `_private/migrations.php`: API administrativa para previsualizar y aplicar solo `CREATE TABLE IF NOT EXISTS` de archivos `*.schema.sql`, checksums, lock, registro de estado y rechazo de SQL destructivo. Dos migraciones de ledger NPL y cola editorial. `20261004_001_T19_REFERENCE.sql` queda explícitamente fuera del runner automático.
7. **Conflictómetro analítico** `_private/npl_analytics.php`: comparador por territorio/año/subgrupo con `null` para ausencia y fuente, y correlación Pearson/Spearman para NUTS2 del mismo año con `n>=8`, series homogéneas y exclusión de observaciones ambiguas. Ningún resultado se presenta como causal o inferencial. **La UI todavía debe cablearse a estos endpoints**.
8. **Noticias** `_private/news_ingest.php`: prototipo de ingestión RSS desde una URL oficial allowlisted de Comisión Europea, clasificación temática, dedupe por URL/título, hash de previsualización y cola editorial, sin publicar noticias automáticamente. Brief para imagen editorial original, sin copiar imágenes. Pendiente validar RSS/extension SimpleXML en Hostinger y ampliar fuentes contrastadas.
9. **Analizador v2** `_private/text_analysis_v2.php`: segmentación heurística de proposiciones, tipos argumentales, señales con contexto, alternativas y límites. `truth_status=not_assessed`, `intent_status=not_assessed`, `stored=false`. La taxonomía conceptual de 95 entradas está disponible como referencia, NO implica 95 detectores operativos. La UI actual aún debe integrarse con esta API.
10. **Backups** `_private/backup.php`: backups nuevos incluyen las tablas auxiliares de migraciones, batches y cola de noticias cuando existen. Una restauración de backup antiguo con estructura distinta queda bloqueada por diseño; Work debe preparar procedimiento de migración/recuperación de backups históricos antes de producción.

## Endpoints nuevos (todos pendientes de pruebas de integración MySQL/Hostinger)

- `POST /api/admin/schema-migrations` (superadmin + CSRF): `{"mode":"preview"}`; `{"mode":"apply","confirmation":"APLICAR_ESQUEMA_SEGURO"}`.
- `POST /api/admin/npl-batches` (superadmin + CSRF): `{"mode":"preview","batch_id":"T26_VULNERABILIDAD_SEGURIDAD_2025"}`; `stage` requiere `confirm_sha256` devuelto por preview; `rollback` además requiere `confirm_batch_id` idéntico. No hay acción `publish` masiva.
- `GET /api/v1/npl/compare?indicator=ecv_arope_pct&period=2025&group=total&geos=ES30,ES61`: exige filas publicadas/revisadas; valores por geo o `null`.
- `GET /api/v1/npl/correlation?x=INDICADOR_A&y=INDICADOR_B&period=2025&group_x=total&group_y=total`: NUTS2, N mínimo 8, mismas fechas, series homogéneas. No calcula significación.
- `POST /api/admin/news-ingest` (editor/admin + CSRF): `{"mode":"preview","feed_id":"eu_commission_press_en"}`; `stage` requiere `confirm_hash` devuelto por preview.
- `POST /api/v1/text/analyze` (CSRF + rate limit): `{"text":"..."}`; sin persistencia.
- `GET /api/v1/text/taxonomy`: taxonomía conceptual v2.

## QA ejecutada

- `php -l` para todos los PHP del repositorio: sintaxis limpia.
- `tests/qa_cli.php`: validación estática de Markdown seguro, SSR por ruta, T19/T26/T27, geocódigos, `0` vs `null`, migraciones no destructivas, correlaciones y empates, dedupe URL, clasificación temática y analizador heurístico.
- Todos los JSON del repo parsean; T19 152, T26 85, T27 54; claves naturales sin duplicados dentro de cada tanda.
- `node --check` en scripts JS de distribución.
- PHP CLI de este entorno es 8.4 pero no dispone de extensiones `mbstring`, `pdo_mysql` ni `simplexml`: el test CLI aplica polyfill SOLO para mbstring; no se ha ejecutado integración DB ni ingestión RSS real. **Hostinger debe confirmar extensiones y PHP 8.3.**

## Release gate P0: no desplegar hasta cumplir

1. Backup real completo, descarga fuera del hosting, prueba de restauración en staging. Incluir nuevas tablas; diseñar compatibilidad de backups antiguos.
2. Staging MySQL con T19, T26 y T27: `preview → stage → repeated stage → rollback`, fallos parciales, duplicados, checksums, permisos y revisión manual; confirmar cero publicaciones involuntarias.
3. Confirmar integridad y existencia de los 50.031 registros originales / 318 indicadores / 41 fuentes **con consulta real**; no asumir que están todos presentes en el sitio actual.
4. Validar los tres endpoints de análisis con datos reales revisados, comparaciones ES30 vs ES61, correlaciones con N y ausencia de datos.
5. Probar edición de artículo propio, autosave, conflicto 409, imagen, firma, foto y Markdown real con dos cuentas distintas. No perder borradores.
6. Pruebas de navegador móvil/escritorio y accesibilidad WCAG 2.2 AA (teclado, lector de pantalla, focus, contraste, estados vacíos). El mapa mundial, timeline y filtros requieren QA visual real.
7. Verificar 429 de portada con `Retry-After`, cabeceras y logs de Hostinger/WAF; no desactivar limitadores de seguridad por intuición.
8. Confirmar disponibilidad real de feed RSS de la Comisión Europea, SimpleXML y política editorial de copyright antes de activar cron.
9. Integrar UI de Conflictómetro y Analizador con los endpoints V6; todavía no está cableada. Diseñar gráficos y leyendas basados en valores reales.
10. Conectar repo privado GitHub → Hostinger y probar despliegue + rollback + persistencia de `_private/config.php`, sesiones y `uploads/`. No subir ZIP a producción de forma destructiva.

## Orden recomendado a GPT Work

Primero staging+backup+MySQL+permisos; después migraciones/importador; luego Blog y SSR; después UI Conflictómetro/Analizador; por último RSS editorial, imágenes y optimización. No introducir cambios en producción hasta superar todos los P0.

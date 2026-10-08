# Handoff único a GPT Work · Psicología Política V15 · PREPRODUCCIÓN

**Base única:** `PSICOLOGIAPOLITICA_REPO_PATCHED_V15.zip` (280 archivos). No partir de V14 ni reconstruir versiones anteriores. La web pública no se ha modificado. **PRODUCCIÓN NO-GO.**

## Cambios funcionales comprobados en V15

1. **Correlaciones NPL:** `_private/npl_analytics.php` ya no convierte `null`, texto o valores no numéricos en `0` al calcular correlaciones. La función pura `pp_analytics_correlation_quality` exige al menos ocho parejas territoriales válidas y metadatos explícitos de fuente, URL, población, tipo de medida, unidad, periodo y revisión. Rechaza series heterogéneas; devuelve territorios excluidos y motivos. Los ceros medidos permanecen válidos. No implica causalidad ni significación estadística.
2. **Comparabilidad general:** `pp_analytics_compatibility`, ranking y series temporales no autorizan inferencias comparativas si ambas observaciones omiten la misma población/tipo de medida. Se mantienen visibles los puntos individuales con fuente; se bloquea gradiente/ranking/comparación engañosos.
3. **Ranking 2D:** empates reciben rangos de competición (1,1,3 / 1,2,2), no posiciones consecutivas. Probado con Chromium a 320, 375, 768 y 1280 px. Recurso versionado `mapa-espana.js?v=15` para invalidar caché.
4. **Datos:** sin nuevas cifras numéricas no verificadas; se conservan **522 observaciones preparadas** y 36 derivadas. Nuevo `database/NPL_REVIEW_BACKLOG_V15.json` transforma **327 observaciones con campos faltantes en 19 tareas de verificación agrupadas por indicador y fuente**. No rellena campos por heurística. `scripts/npl_review_backlog_v15.py` reproduce el informe.
5. **Noticias:** T38 añade tres investigaciones con fuente primaria, método, límites, relevancia y propuesta de imagen original. **18 candidatas editoriales en total**, sin DOI ni URL duplicados dentro de los lotes auditados. Estado `review_only`, ninguna publicada.
6. **Auditoría pública real:** `database/PUBLIC_AUDIT_V15.json` registra 10 rutas: SSR genérico en Conflictómetro, Analizador, Contacto y Herramientas; `/mapa-espana` 404 esperado por no despliegue; Blog y Quiénes somos con contenido específico; Markdown literal visible en el artículo Mari Carmen, no generalizado a todos los artículos. Esta auditoría es de extracción pública, no de interacción autenticada.

## Orden de ejecución de GPT Work

1. Importar V15 en **repositorio GitHub privado**, crear rama `staging`, activar QA CI y corregir incompatibilidades de entorno. Mantener secretos fuera de Git. No publicar nada aún.
2. Crear **staging aislado** en Hostinger con MariaDB real y copia autorizada/anonimizada. Ejecutar migraciones en orden, `dry-run`, importar lotes revisados, verificar ledger, idempotencia, rollback y restauración de backup. No importar como publicados T36 ni datos pendientes de revisión.
3. Ejecutar los **19 paquetes de revisión de fuentes** priorizados en `NPL_REVIEW_BACKLOG_V15.json`; documentar población, tipo de medida y fecha real de consulta en evidencias revisadas, sin inventar. Resolver 19 coincidencias INE/Eurostat AROPE sin contarlas como estudios independientes.
4. Probar rutas NPL reales con cero/null, 20 ámbitos, duplicados, series, rankings, correlaciones y preguntas. En especial confirmar que valores sin metadatos no reciben escala cromática, ranking ni correlación. Mantener `/mapa-espana` con `noindex` hasta GO.
5. Probar Blog autenticado con dos editores y un lector: recuperación por ID, autoría, foto, autosave aislado, concurrencia `expected_content_hash`, estado de guardado, XSS/CSRF y formato Markdown. Verificar artículo Mari Carmen en staging.
6. Validar RSS/Atom con PHP SimpleXML real, cron y cola editorial; deduplicación DOI/URL/título y revisión humana. Producir imágenes originales únicamente tras revisión; no publicar automáticamente.
7. Integrar Analizador V2 y componentes React con backend, eliminar controles decorativos, comprobar rutas móviles, accesibilidad, SEO/OG, errores 404/429, caché y rendimiento. Ensayar deploy reversible y recuperación. **Solicitar autorización antes de tocar producción.**

## QA / restricciones

- PHP, JavaScript, JSON, pruebas NPL y Chromium con API simulada: **PASS local**. Nuevo gate CI V15 añadido a `.github/workflows/qa.yml` y `staging-hostinger.yml`.
- PHP SimpleXML **no disponible** en entorno local: RSS/Atom real **NO PROBADO**.
- MariaDB, permisos autenticados multiusuario, CI remoto, staging Hostinger y producción: **NO PROBADOS**.
- `522` = observaciones preparadas, no registros publicados; `18` = candidatas editoriales, no noticias publicadas.
- **GO requiere** pruebas de integración, fuentes trazables, accesibilidad y despliegue reversible. Nunca presentar simulaciones como pruebas en producción.

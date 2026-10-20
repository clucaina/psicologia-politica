# Handoff GPT Work · Psicología Política V11 · fuente única preproducción

**Usa `PSICOLOGIAPOLITICA_REPO_PATCHED_V11.zip` de este paquete como única base de código**. No reconstruyas V4–V10. GitHub debe ser la fuente de verdad; Hostinger PHP 8.3/MySQL, staging aislado; no Cloudflare/Workers/D1/R2. No despliegues producción sin aprobación expresa, backup restaurado y release gate.

## Orden de ejecución para ahorrar créditos

1. Importa el repo V11 a un GitHub privado y ejecuta CI (incluye QA V8–V11). Conserva `config.php`, sesiones, media y backups fuera de Git. El workflow de staging solo permite destino terminado en `/staging-pp`, sin borrados y con `apply=false` por defecto.
2. Crea staging con BD aislada y datos sintéticos. Comprueba MySQL y migraciones preview/apply, staging de T19/T26/T27/T28/T30/T31/T32, rollback parcial y transaccional, dependencia T28→T27, colisiones semánticas y gate de revisión humana. No inventes fechas de consulta.
3. Integra el prototipo 2D de `/docs/observatorio-espana-v8.*` en la SPA y enlaza APIs revisadas. Verifica catálogo, cobertura, 19 territorios, 0≠null, años, series, ranking, correlaciones, comparación y Pregunta a España; prueba ausencia de datos y cambios rápidos de filtros. **No expongas `/docs` como solución definitiva.**
4. Blog: prueba `/blog/escribir?edit=<id>` con dos autores y un lector, lectura de borradores propios, autosave por ID, recuperación local, conflicto 409, guardado parcial, foto y perfil de autor, Markdown/SSR, CSRF y permisos. La nueva ruta requiere rol editorial desde PHP; la SPA debe mostrar estado coherente de acceso.
5. Noticias: verificar feeds RSS institucionales reales y deduplicación por DOI/URL/título, cola `review_only`, cron, permisos, trazabilidad y estados de error. T34 aporta tres candidatas científicas (no noticias publicadas). Validar fuentes primarias y generar imágenes originales solo tras revisión; no copiar fotografías protegidas.
6. Analizador: conectar UI con `evidence_needed`, señales con revisión humana, etiquetas múltiples, citas, negaciones y `truth_status=not_assessed`. La herramienta nunca decide veracidad a partir del estilo.
7. SEO/SSR: comprobar HTML público sin JS en `/conflictometro`, `/herramientas/datos-espana`, `/herramientas/analizar-texto` y el Blog. El fallback genérico y Markdown escapado siguen visibles en la web actual; los fixes están solo en la copia.
8. Validar permisos, WAF/429, `Retry-After`, `X-RateLimit-Policy`, métricas, móvil, teclado, lector de pantalla, WCAG 2.2 AA, Lighthouse, carga controlada, backup/restore y rollback. Emitir informe GO/NO-GO. **No activar producción.**

## Datos y evidencia

- 521 observaciones preparadas (36 derivadas), **0 nuevas observaciones numéricas V11**; 25 indicadores y 27 indicador/subgrupo. Inventario `database/NPL_PREPARED_COVERAGE_V11.json`.
- AROPE 2025 T19/Eurostat vs T26/INE: 19 coincidencias numéricas territoriales; **no fusionar ni duplicar como evidencia independiente** sin revisar equivalencia metodológica. `database/NPL_CROSS_SOURCE_OVERLAP_V11.json`.
- T34: 3 artículos de investigación para revisión; 0 publicados. Mantener separación entre experimentos, análisis integradores y argumentación filosófica.
- Las 237 fechas pendientes T19/T26 y la verificación final T30/T31 siguen siendo bloqueos de publicación. El prototipo solo usa datos publicados/revisados de MySQL; los JSON de preparación no deben aparecer como resultados oficiales publicados.

## No hacer

No borrar tablas, no `rsync --delete`, no subir manualmente ZIP a `public_html` como flujo permanente, no copiar datos nacionales a CCAA, no confundir encuestas con causalidad ni registros policiales con prevalencia, no atribuir intenciones en el Analizador y no anunciar pruebas MySQL/producción que no se hayan realizado.

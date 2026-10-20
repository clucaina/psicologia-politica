# Sprint V13 — 2026-10-08

**Producción intacta.** Sobre la V12 se ha creado ruta 2D PHP autónoma `/mapa-espana`, moviendo recursos desde `/docs` bloqueado hacia `/assets/observatorio`. Incluye búsqueda visible, zoom y responsive; queda `noindex` hasta staging/GO. Se ha reparado la pérdida de metadatos de revisión (`npl_reviews.evidence`) en las APIs analíticas, además de la cobertura inflada por duplicidades territoriales y la conversión potencial de `null` a cero. Tres estudios nuevos se han añadido solo a cola editorial T35. Sin nuevas observaciones numéricas.

**Hallazgos QA**: 521 observaciones preparadas, 237 fechas de consulta ausentes, 327 poblaciones sin explicitar en origen, 237 tipos de medida ausentes, 36 derivados; no inventar ni completar automáticamente. Chromium y pruebas estáticas PASS; MySQL/Hostinger/Blog autenticado/SPA integración PENDIENTES.

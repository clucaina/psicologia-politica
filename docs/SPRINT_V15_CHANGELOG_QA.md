# Sprint V15 · registro de cambios

- `npl_analytics.php`: puerta de calidad de correlación, sin convertir nulos a cero; metadatos explícitos obligatorios para comparabilidad y rankings; serie vacía no marcada comparable.
- `mapa-espana.js`: ranking con empates; bloqueo de gradiente, comparación y ranking si faltan metadatos; avisos precisos y control de caché `v=15`.
- `tests/qa_semantic_v15.php`, `tests/qa_browser_v15.py`, `tests/qa_v15.py`: nuevos tests de nulidad, metadatos, comparabilidad, ranking y editoriales.
- `database/T38_CANDIDATAS_EDITORIALES_20261008.json`: tres investigaciones revisables, cero publicación automática.
- `scripts/npl_review_backlog_v15.py` + `database/NPL_REVIEW_BACKLOG_V15.json`: 19 grupos de revisión con URLs y claves naturales de ejemplo, 327 registros afectados.
- `database/PUBLIC_AUDIT_V15.json`: auditoría pública, distinguiendo 404 predespliegue de regresiones y SSR de interacción JS.
- `.github/workflows/*.yml`: gates V15; `VERSION.json`: V15.
- No cambios de producción ni credenciales. No se añadieron valores numéricos nuevos sin fuente primaria.

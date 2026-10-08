# Sprint V11 · 8 octubre 2026 · copia segura, sin despliegue

## Correcciones aplicadas

1. **Ruta del editor**: `/blog/escribir` ya no entrega el shell de edición a usuarios anónimos ni lectores no editores. El servidor exige sesión y rol editorial; los endpoints seguían protegidos. Esta es una corrección de autorización a nivel de ruta, no una prueba autenticada completa.
2. **Pregunta a España**: preguntas del tipo «AROPE de Andalucía, España» no se interpretan como comparación territorial; las comparaciones explícitas «frente a España» siguen disponibles. Las preguntas causales se rechazan en vez de producir una respuesta estadística que pueda parecer causal. Las respuestas de Pew incluyen aviso explícito de que miden percepción declarada.
3. **Ingesta de noticias**: identidad de fuente basada en DOI cuando puede extraerse del enlace, o URL HTTPS normalizada; deduplicación frente a publicaciones y candidatos en cola. Se conserva revisión humana obligatoria. No se ha probado contra RSS ni MySQL reales.
4. **Analizador V2**: señales con `requires_human_review` y marca `evidence_needed` para afirmaciones verificables o causales. No se deduce verdad, intención ni diagnóstico.
5. **Diagnóstico 429**: respuestas de limitación de tasa desde PHP añaden `Retry-After` y `X-RateLimit-Policy: application` sin modificar los umbrales. No identifica límites externos del WAF: deben distinguirse en staging/logs.
6. **Accesibilidad del mapa**: reduce animación cuando el sistema lo solicita, presenta bordes legibles en colores forzados y aumenta áreas táctiles. Pruebas Chromium en modo reducido y colores forzados.
7. **NPL**: inventario reproducible de 521 observaciones preparadas (25 indicadores, 27 combinaciones indicador/subgrupo) y detección de solapamiento AROPE 2025: 19 valores coinciden entre Eurostat T19 e INE T26, pero **no se fusionan ni cuentan como evidencia independiente** hasta cotejar metodología.
8. **Editorial**: T34 incorpora tres estudios primarios revisados en sus páginas editoriales con método, límites, advertencia de extrapolación y brief de imagen original. Solo `review_only`; **0 nuevas observaciones numéricas**.

## Pruebas locales realizadas

- Sintaxis PHP y JavaScript; parseo JSON/YAML; pruebas `qa_cli.php`, `qa_semantic_v10.php`, `qa_semantic_v11.php`, `qa_v8.py`–`qa_v11.py`, `qa_source_tables_v10.py`, `qa_autosave_v9.js`.
- Chromium `qa_browser_v9.py`, `qa_browser_v10.py` y `qa_browser_v11.py`, con **APIs simuladas**: 320/375/768/1280 px, carreras asíncronas, selección teclado, `prefers-reduced-motion`, `forced-colors`, 0/null y estados vacíos.
- Las pruebas PHP CLI se ejecutaron en un contenedor PHP 8.4 sin mbstring; el test V11 contiene fallback **solo de test** para la comparación ASCII de títulos. GitHub CI instala PHP 8.3 con mbstring.

## Limitaciones y release gate

- Auditoría pública: extracción remota confirmó que `/conflictometro` continúa mostrando un fallback de noticias; otras rutas fallaron por conectividad de herramientas. No inferir estado de toda producción.
- SSR corregido solo en repo V11, aún no integrado en Hostinger.
- No se ha ejecutado MySQL real, integración SPA/React, test autenticado multiusuario, restore de backup, 429/WAF, lector de pantalla ni pruebas de rendimiento de producción.
- T19/T26 siguen con 237 fechas de consulta históricas sin verificar; no se han rellenado.
- T30 y T31 siguen pendientes de revisión editorial final. T34 no ha sido publicado.
- **NO-GO producción** sin QA integral y autorización expresa.

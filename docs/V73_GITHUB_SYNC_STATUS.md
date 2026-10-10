# Integración remota V73 — 10/10/2026

**Estado: NO-GO para publicación en Hostinger.** La rama real en GitHub `integracion/v70-preparacion-segura` nace de V16. No coincide todavía con el paquete maestro V72 ni con los ficheros restaurados en el alojamiento.

## Código integrado y comprobable en GitHub
- `_private/pages.php`: autoría pública por identidad única (prohibido fallback por nombre ante author_id distinto, nombres repetidos o IDs duplicados); reparación de énfasis Markdown escapado sin ejecutar HTML.
- `scripts/npl_semantic_gate_v73.py`: auditoría en modo lectura de observaciones candidatas; cero medido != null, frecuencia/periodos, porcentajes acotados y ventanas temporales. No importa ni aprueba observaciones.
- `assets/observatorio/correlation-integrity-v73.mjs`: verifica filtros, pares CCAA y coeficientes Pearson/Spearman recalculados; impide falsear valores o correlacionar null.
- `scripts/editorial_dedup_gate_v73.py`: identidad de fuente con DOI/URL normalizados, duplicados y campos editoriales esenciales; prohíbe autopublicar y solo prepara revisión.
- `tests/qa_v73_*.php`, `*.py`, `*.mjs`: regresiones aisladas para tres motores.
- `.github/workflows/predeploy-guard-v70.yml`: CI read-only que **nunca** despliega; incluye sintaxis y pruebas autoría, NPL y correlación. El test del control editorial requiere incorporarse a un gate futuro.

## Fuente original de mejoras aún por integrar
`PSICOLOGIAPOLITICA_MASTER_V72_PREPRODUCCION.zip`, conservado en ChatGPT, contiene backend más avanzado, frontend React compilado, cartografía 2D y 1.213 observaciones científicas candidatas. **Ese ZIP no está importado íntegramente** en esta rama. No mezclar sus archivos ciegamente con el servidor ni publicar la base candidata.

## Riesgos de producción
1. Recuperación 403 desde copia Hostinger 04/10; `main` del repo solo tenía README y provocó borrado de `public_html` tras despliegue. Mantener auto-deploy desactivado.
2. MariaDB real conserva el contenido publicado, incluido Decreto Mari Carmen; no reemplazar con SQL antiguo.
3. Los secretos (`_private/config.php`), uploads, fotografías y autorías deben preservarse fuera del paquete de despliegue.
4. GitHub sigue público: no subir datos privados, imágenes protegidas, borradores, correos ni material clínico.
5. Para Work: comparar hashes Hostinger ↔ V72; integrar editor compilado/Blog; revisar CI, MariaDB staging, mapa y noticias; exigir autorización de publicación.

Ninguno de estos commits está desplegado en Hostinger.
# Release gate V15 · 2026-10-08

**NO-GO producción.**

## Verificado localmente
- Sintaxis PHP 26 archivos; JS 52 archivos; JSON 42 archivos.
- QA regresión NPL V8–V15, tests de evidencia y linaje, editor/autosave y tests de compatibilidad.
- Chromium V9–V15 con API simulada; V15 320/375/768/1280 px y empates 1,1,3 / 1,2,2.
- Correlación V15: cero conservado; nulos y texto excluidos; menos de 8 parejas bloqueadas; población no documentada y heterogeneidad bloqueadas.
- 522 observaciones preparadas; 36 derivadas; 18 candidatas editoriales sin DOI/URL duplicados; 19 tareas de revisión de fuentes.
- Auditoría pública de contenido de 10 rutas, sin escritura en producción.

## Bloqueos
- Sin MariaDB real de staging, sin pruebas multiusuario, sin SimpleXML local, sin ejecución de GitHub Actions, sin validación de staging ni backup restaurado.
- 237 fechas de consulta, 327 poblaciones y 237 tipos de medida faltantes en los JSON preparados. Revisar, no autocompletar.
- SSR genérico de herramientas en la web pública; la copia V15 incorpora fallback pero no está desplegada.
- Ruta `/mapa-espana` no publicada (404 esperado). Markdown literal en un artículo público, corregir en staging.

**Nunca desplegar automáticamente ni desactivar noindex antes de un GO documentado y autorización.**

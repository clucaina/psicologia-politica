# Handoff a GPT Work — V9

**Fuente única de código**: `PSICOLOGIAPOLITICA_REPO_PATCHED_V9.zip`. No desplegar en producción. V8 queda sustituida.

## Orden de implementación y aceptación

1. Crear repositorio privado GitHub a partir de V9. No subir secretos. Activar QA. Preparar staging Hostinger con base MySQL aislada y backup restaurado.
2. Ejecutar `php tests/qa_cli.php`, `python tests/qa_v8.py`, `python tests/qa_v9.py`, `node tests/qa_autosave_v9.js`. Si Playwright/Chromium está disponible, ejecutar `python tests/qa_browser_v9.py`. Todos los test de navegador usan mocks; **no sustituyen staging**.
3. Inspeccionar visualmente las tablas oficiales T30 y T31: 
   - T30: Ministerio Educación, PDF tabla 1 abandono temprano 2022–2025, `database/T30_ABANDONO_EDUCATIVO_2022_2025.json` (80 filas).
   - T31: Ministerio Educación, PDF tabla 1 tasas empleo/paro 25–64 en 2015/2020/2025, `database/T31_EMPLEO_PARO_25_64_2015_2025.json` (108 filas).
   - Documentar fuente, unidad, año, subgrupo, error muestral y revisión; no publicar si hay dudas. Ceuta 2025 T30 11,65%; T31 excluye Ceuta/Melilla individuales.
4. Ejecutar migraciones `preview` y `apply` con checksum; importar T19/T26/T27/T28/T30/T31 mediante dry-run y stage idempotente. T28 depende de T27. No publicar observaciones sin revisión humana. Las 237 fechas ausentes T19/T26 siguen pendientes.
5. Integrar el prototipo de `docs/observatorio-espana-v8.html` (ahora **código corregido V9**) con la SPA. Mover SVG/CSS/JS a `assets` versionados; `docs/` permanece bloqueado por Apache. Verificar 19 NUTS2, selector alternativo, cero/null, escala incompatible, timeline con huecos, ranking, comparación y deep-links.
6. Conectar Blog a API de borradores propios, comprobar recuperación de copia local más reciente, guardado del mismo ID, CSRF, conflictos 409, portadas, firma/foto tanto SSR como React y perfiles individuales. Dos cuentas distintas obligatorias.
7. Confirmar HTML SSR por ruta y Markdown, no servir corpus genérico en páginas de herramientas; revisar el 429 desde logs de Hostinger sin desactivar WAF a ciegas.
8. Activar pipeline de noticias en staging solo como cola de revisión; probar RSS real y duplicados. `database/EDITORIAL_VISUAL_BRIEFS_V9.json` ofrece tres prompts originales y ALT, **no imágenes ya creadas**. No autopublicar.
9. Analizador V2: mostrar tipos múltiples y contexto de citas/negaciones sin atribuir veracidad, intención ni diagnóstico. Testear casos adversariales.
10. Pasar release gate completo: MySQL, permisos, backup/restore, rollback, WCAG 2.2 AA, móvil 320/375/768/1440, SEO, CSP, rendimiento, fuentes y auditoría de seguridad. Solicitar autorización expresa antes de cualquier despliegue en producción.

## Advertencias que no deben perderse

- 515 observaciones son **preparadas**, no necesariamente importadas ni publicadas; 36 son derivadas y 188 nuevas requieren cotejo visual de fuente.
- No hay datos oficiales desagregados para Ceuta/Melilla en la tabla de empleo/paro T31: nunca dividir su cifra agregada.
- T30 abandono educativo temprano no es absentismo escolar, ni fracaso escolar; T31 25–64 no equivale a T19 15–64.
- Las tasas descriptivas y correlaciones no prueban causas, discriminación ni diagnósticos.
- No ejecutar `rsync --delete` sobre `public_html`; mantener sesiones, config, medios, logs y backups.
- El navegador offline ha validado el prototipo con **respuestas API simuladas**, no una aplicación React integrada.

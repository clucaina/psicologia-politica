# Sprint V9 — 2026-10-08 · copia segura, no desplegada

## Cambios reales

1. **T30: educación**: 80 observaciones oficiales candidatas de abandono educativo temprano (2022–2025, España + 19 territorios), extraídas del texto indexado de una tabla PDF del Ministerio de Educación. La extracción **no sustituye la inspección visual del PDF original**. Se conserva advertencia de error muestral elevado en Cantabria, Navarra, La Rioja, Ceuta y Melilla. Ceuta 2025 figura con 11,65 % en la tabla, no 11,6 % redondeado.
2. **T31: empleo y paro**: 108 observaciones candidatas de tasas de empleo/desempleo para población de **25–64 años** en 2015, 2020 y 2025 (España + 17 CCAA). **Ceuta y Melilla no se asignan por separado** porque el documento oficial las agrega. No mezclar con empleo de 15–64 años de T19. Tres cortes temporales no son una serie anual completa.
3. **Total acumulado preparado V9: 515 observaciones** (327 heredadas + 188 nuevas), 36 derivadas de T28. Cero duplicados por clave natural entre T19/T26/T27/T28/T30/T31. Ninguna observación nueva se ha publicado.
4. **Bug crítico del mapa V8 detectado en Chromium real con API simulada**: el SVG carecía de `xmlns`, por lo que `DOMParser(..., 'image/svg+xml')` creaba nodos sin `SVGElement.style` y el mapa fallaba. Corregido.
5. **Segundo bug runtime del mapa**: `d.append(p).append(a)` fallaba porque `Element.append()` devuelve `undefined`. Corregido; ficha y enlace de fuente se renderizan.
6. **Comparabilidad visual**: el mapa ya no aplica gradiente cromático cuando hay fuentes, unidades o metodologías incompatibles; los datos se consultan individualmente, sin ranking engañoso. Los valores cero permanecen válidos y distintos de `null`.
7. **UX móvil**: selector nativo de 19 territorios accesible con teclado, alternativa al mapa pequeño; corregido borrado accidental del encabezado de la ficha en estado sin dato; limpiados estados de API fallida.
8. **Blog**: recuperación de copia local de un artículo editado si es posterior a la versión del servidor, con confirmación expresa; no sobrescribe automáticamente la versión remota y preserva `updated_at` del servidor para control 409.
9. **Seguridad NPL**: preservación del indicador de calidad de origen (`source_quality_flag`) al importar; publicación de T30/T31 bloqueada hasta confirmación explícita de cotejo visual del PDF y fecha real, con sello de revisor/fecha. El importador no trunca la búsqueda de duplicados históricos a 100 registros.
10. **Pregunta a España**: reconoce abandono educativo temprano, empleo 25–64 y paro 25–64, siempre con indicador, territorio y año explícitos.
11. **Analizador V2**: identifica citas incrustadas y condicionales como etiquetas simultáneas, sin convertirlas en juicios de verdad o intencionalidad.
12. **Seguridad de RSS**: el límite de 2 MB se aplica durante la descarga cURL (callback de escritura) y también en el fallback stream; evita descargar íntegramente feeds desproporcionados antes de rechazarlos.
13. **Imágenes editoriales**: tres briefs originales y sus textos alternativos, no imágenes generadas. Las noticias siguen sujetas a revisión humana.

## Pruebas ejecutadas

- PHP `php -l` y JavaScript `node --check` sobre copia, JSON válidos.
- `php tests/qa_cli.php`: pasan validaciones NPL, SSR, preguntas, bloqueo de publicación y reglas lingüísticas.
- `python tests/qa_v8.py` y `python tests/qa_v9.py`: pasan integridad, 515 claves únicas, cobertura y derivaciones.
- `node tests/qa_autosave_v9.js`: pasa recuperación por documento y prevención de sobrescritura silenciosa.
- `python tests/qa_browser_v9.py`: Chromium headless con **APIs simuladas** pasa en 320/375/768/1280 px: 19 regiones, cero/null, selección, comparación, timeline, preguntas, firmas metodológicas incompatibles y sin overflow horizontal. **No equivale a integración MySQL ni QA de producción.**

## Release gate pendiente

- Inspección visual de los PDF oficiales de T30/T31, revisión humana de 188 filas y 237 fechas pendientes de T19/T26.
- MySQL real, migraciones, importador, concurrencia y rollback con staging aislado.
- Integración de observatorio 2D en SPA React, assets versionados y verificación de CSP/Apache.
- Pruebas autenticadas Blog (dos autores, CSRF, autosave, 409, firma/foto) y lectores de pantalla.
- Confirmar feeds RSS reales, permisos de cron y cola editorial, sin autopublicación.
- Auditoría pública nueva no concluida: herramientas de lectura externas no permitieron abrir las páginas en esta ejecución. Mantener defectos SSR/Markdown observados anteriormente como pendientes de verificación tras staging.
- Revisión de accesibilidad WCAG 2.2 AA, logs 429, rendimiento, backup/restore y aprobación antes de producción.

**No se ha modificado producción. No se afirma que la web pública use V9.**

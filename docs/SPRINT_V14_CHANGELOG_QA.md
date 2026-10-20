# Psicología Política · Sprint V14 · 8 octubre 2026

**Ámbito:** copia segura V13 → V14. **Producción: sin cambios.** No se han creado usuarios, alterado MySQL, activado cron ni desplegado archivos.

## Código funcional

1. **Mapa España + dato nacional:** `/mapa-espana` permite seleccionar **España** además de las 19 CCAA/ciudades. La API de comparación recibe los 20 ámbitos; el mapa colorea y el ranking ordena **solo los 19 territorios** para que el agregado nacional no contamine la escala. La comparación España–CCAA advierte que no son muestras independientes.
2. **CSV reproducible:** botón real de exportación con 20 ámbitos, indicador, subgrupo, año, valor, unidad, fuente, URL, población, método, revisión, límites y estado. `0` se conserva; los ausentes se exportan vacíos, no como cero. Protección de fórmulas en celdas de texto, con números negativos legítimos preservados. No se exportan observaciones distintas de las devueltas por API revisada.
3. **Blog y concurrencia:** servidor calcula una huella SHA-256 del contenido relevante, expuesta en lectura y guardado. El cliente compilado del editor **envía y renueva** `expected_content_hash`, además de `expected_updated_at`; evita sobrescrituras incluso si dos cambios comparten la misma marca temporal. El bootstrap de artículos propios incorpora la huella. Las copias locales siguen separadas por ID.
4. **Pregunta a España:** añade enrutamiento específico del indicador AROPE en personas de hogares con hijos dependientes, priorizando la frase específica frente al genérico `AROPE`; conserva el bloqueo de inferencias causales y de pobreza infantil no disponible.
5. **Responsive:** etiquetas largas de botones ya no generan scroll horizontal a 320 px. Se mantiene accesibilidad con teclado, tabla, ausencia/cero y fuentes.
6. **CI:** GitHub Actions de QA y staging incorporan QA V13/V14, revisión estructural V14 y pruebas de navegador. Se corrigió una captura de test V13 que escribía en un directorio `/mnt/data` no garantizado en GitHub Actions. **Work debe ejecutar el workflow real**: pasar localmente no garantiza pasar en GitHub.

## Datos / fuentes / editorial

- **T36:** 1 observación oficial nueva candidata (Eurostat, 2025, España, **29,9 %** AROPE entre *personas que viven en hogares con hijos dependientes*). Fuente primaria: https://ec.europa.eu/eurostat/en/web/products-eurostat-news/w/ddn-20260506-1 (publicada el 6 de mayo de 2026; consultada el 8 de octubre de 2026). **No es pobreza infantil ni porcentaje de hogares**. Queda pendiente cotejar la tabla `ilc_peps03n` y revisión humana antes de publicar.
- **T37:** 3 investigaciones candidatas, sin publicar: experimentos de deepfakes y reputación (DOI 10.1177/00936502261421437), registered report sobre saturación informativa y confusión (10.1111/pops.70076), panel europeo sobre percepción partidista de desinformación (10.1080/10584609.2026.2671760). Cada ficha incluye método, evidencia accesible, límites y brief de imagen editorial original.
- Auditoría global preparada: **522 observaciones / 522 claves naturales únicas / 36 derivadas**. Faltan 237 fechas de consulta, 327 poblaciones explícitas y 237 tipos de medida. Los 19 solapamientos AROPE INE/Eurostat siguen identificados; no constituyen replicaciones independientes. T36 no incrementa estos pendientes.
- **15 candidatas editoriales** en tandas T29, T33, T34, T35 y T37, sin DOI repetido entre los que lo tienen. Tres son nuevas de esta ejecución. Ninguna se ha publicado ni se ha creado una imagen definitiva.

## QA ejecutada

- **25 PHP:** `php -l` PASS; **51 JS:** `node --check` PASS; **38 JSON:** parse PASS.
- `qa_cli`, `qa_v8–v11`, `qa_v13–v14`, `qa_source_tables_v10`, `qa_semantic_v10–v14`, `qa_eurostat_v12`, `npl_crossbatch_qa_v12`, `npl_quality_report_v13–v14`, `qa_autosave_v9`: PASS.
- Chromium `qa_browser_v9–v14`: PASS. V14 comprobó 320/375/768/1280 px, dato nacional, ranking sin España, cero frente a ausencia, CSV descargado, neutralización de fórmula y ausencia de scroll horizontal.
- PHP servidor local con router de estáticos: `/mapa-espana`, JS, CSS y SVG devolvieron HTTP 200. Sin MySQL, por lo que **no** valida consultas SQL reales.
- SimpleXML no está disponible en el PHP local: prueba RSS/Atom runtime **omitida**, pendiente de Hostinger/CI.

## Auditoría pública y limitaciones

- La lectura pública de `https://psicologiapolitica.es/conflictometro` sigue mostrando un fallback SSR genérico con una larga lista de noticias, no el contenido específico de la herramienta. Es un problema **de producción aún no corregido allí**, no de la copia V14.
- La portada y `/blog` no pudieron verificarse con el lector web de esta ejecución. **No se concluye que den error HTTP** a usuarios reales.
- Sin MySQL de staging, PHP PDO MySQL, SimpleXML, cuentas editoriales de prueba, despliegue GitHub/Hostinger ni pruebas autenticadas. **RELEASE GATE: NO-GO.**

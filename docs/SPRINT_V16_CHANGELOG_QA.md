# Psicología Política · V16 · cambios comprobados (8 octubre 2026)

## Alcance
Partimos de la copia maestra V15 de Library. Cambios **solo en copia local de preproducción**. Sin escrituras en `psicologiapolitica.es`, GitHub ni Hostinger.

## Bloque 1 · Blog y seguridad
- **Control de concurrencia obligatorio** para guardar artículos existentes: el servidor exige `expected_content_hash` SHA-256 con formato correcto, además de `expected_updated_at`. Antes, un cliente que omitiera la huella podía eludir el control reforzado si coincidía la fecha.
- La huella ahora incluye `post_kind`, además de contenido, título, estado, sección, imagen y fuente.
- **Autosave por documento y cuenta**: las claves locales pasan de `pp-author-desk-autosave-v2:<id>` a `pp-author-desk-autosave-v3:<sha256(email)>:<id>`. La página autenticada emite el identificador pseudónimo exclusivamente en `/blog/escribir`; la SPA lo utiliza para aislar borradores en un navegador compartido.
- **Precaución de migración:** las copias antiguas v2 no se recuperan automáticamente para evitar filtraciones entre cuentas. Antes de desplegar, exportar manualmente cualquier borrador local v2 que no esté guardado en el servidor. No se borra almacenamiento existente automáticamente.

## Bloque 2 · Conflictómetro
- Comparaciones de porcentajes muestran diferencias en **puntos porcentuales**, no en «%»; mantiene el aviso de no causalidad.
- Años futuros o inválidos se rechazan **antes de consultar la API**, también en la línea temporal. Los límites de año se actualizan al año corriente.
- Al cambiar rápidamente de indicador se evita la consulta redundante iniciada por un selector ya obsoleto.
- Rankings con valores derivados negativos incluyen aviso de que la longitud de barra es valor absoluto y el signo aparece en la cifra.
- Se mantiene cero observado distinto de ausencia, con 19 territorios y dato nacional independiente.

## Bloque 3 · Integridad de evidencia y Analizador
- `pp_analytics_metadata_complete` exige fuente HTTPS sin credenciales incrustadas para habilitar comparaciones. La correlación comparte ese criterio; los datos individuales siguen visibles cuando no son comparables.
- La negación del Analizador se vincula a la **cláusula inmediata**; ya no atribuye un «no» de la frase anterior a una señal posterior. Sigue siendo heurístico, sin atribución de verdad o intención.

## Bloque 4 · Noticias y fuentes
- El índice de deduplicación editorial deja de truncarse silenciosamente al superar 10.000 registros: falla de forma segura hasta implementar paginación. No se publica automáticamente.
- **T39**: tres estudios científicos no repetidos en tandas T29–T38, con DOI, fecha, evidencia, metodología, límites y brief de imagen original. El cotejo detectó tres duplicados antes de escoger estos tres: se evitó contabilizarlos otra vez. Total: **21 candidatas en revisión**, 0 publicaciones nuevas.
- **T40**: fichas de dos barómetros CIS de 2026 (estudios 3540 y 3557), con población, geografía ES y plan de extracción responsable. **0 observaciones numéricas añadidas**; prohibido inferir resultados autonómicos de encuestas nacionales.

## Bloque 5 · Auditoría pública y QA
- La extracción pública del 8/10/2026 continúa mostrando contenido genérico de noticias bajo las rutas `/conflictometro`, `/herramientas` y `/contacto`, mientras `/blog` muestra listado propio. La inspección web no prueba si la SPA funciona con JavaScript.
- Pasan pruebas estáticas PHP/JS/JSON; suites semánticas V10–V16; pruebas Chromium V9–V16 con API simulada a 320/375/768/1280; 0 %, puntos porcentuales y año futuro bloqueado.
- Entorno local: PHP 8.4 sin `mbstring`, `pdo_mysql` ni `SimpleXML`; **no hay integración real MariaDB, autenticación multiusuario ni RSS/Atom**. CI configura PHP 8.3 con esas extensiones, pero no se ha ejecutado remotamente.
- La base preparada se mantiene en **522 observaciones** según inventario V15, incluidas 36 derivadas; las pruebas de cruce T19/T26–T35 cuentan 521 porque excluyen la candidata aislada T36. Siguen pendientes 237 fechas de consulta, 327 poblaciones y 237 tipos de medición, agrupados en 19 tareas de revisión.

## Decisión
**PRODUCCIÓN NO-GO.** Validar en staging MariaDB, permisos, cron/feeds, restore de backup, accesibilidad y pruebas de despliegue reversible antes de solicitar autorización para producción.

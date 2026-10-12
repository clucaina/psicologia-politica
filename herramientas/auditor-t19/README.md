# T19, revisión previa a integración

Este módulo pertenece a **Psicología Política / Conflictómetro**. No depende de NeuroLab, no añade otro mapa y no toca el publicador ni la base MySQL.

La herramienta de consola \`tools/validar_t19.py\` lee el lote que ya existe en \`database/seeds/T19_CCAA_BASELINE.json\`. Comprueba integridad de las celdas, claves duplicadas, 19 territorios por cada uno de ocho indicadores, unidades, valor numérico, procedencia HTTPS y la coherencia del incremento respecto del checkpoint declarado. Conserva y contabiliza las reservas de metodología; nunca las considera equivalentes a un dato definitivo.

## Ejecución

\`\`\`bash
python3 tools/validar_t19.py \
  --input database/seeds/T19_CCAA_BASELINE.json \
  --output qa_t19_report.json
\`\`\`

El JSON de salida incluye recuentos, avisos, errores, huella SHA-256 del archivo de entrada y \`publication_approved: false\`. Un resultado sin errores solo valida la **estructura del lote**, no la exactitud de las estadísticas ni su compatibilidad con otras ediciones.

La acción de GitHub \`.github/workflows/t19-integrity.yml\` repite la prueba en solicitudes de cambios que afecten al lote y permite su ejecución manual, sin desplegar ni modificar producción.

## Siguientes controles obligatorios antes de publicar

Contrastar nuevamente las cifras y periodos frente a Eurostat, revisar las observaciones provisionales y las diferencias de definición, y hacer una comparación interlotes con el maestro por clave semántica. La aceptación final debe ser humana.

**Procedencia:** el archivo T19 conserva 152 observaciones regionales y la información de origen Eurostat. Se mantiene el material auditado anteriormente en \`integracion/v78-maestro-sin-despliegue\`.

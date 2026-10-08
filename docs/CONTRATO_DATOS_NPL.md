# Contrato de datos NPL

Clave lógica recomendada de una observación:
`indicator_id + period + geo_code + subgroup + source_id (+ dataset_code cuando sea necesario)`.

Campos que toda API debe intentar devolver:
- indicator_id
- indicator_name
- domain/subdomain
- value
- unit
- period
- geo_code
- geography
- territory_level
- subgroup
- source_id
- source_name
- source_url
- retrieved_at / source_updated
- quality_status
- methodology_flag
- batch_id
- coverage

Calidad pública:
- VERIFIED: contrastado / publicado.
- PROVISIONAL: fuente oficial o válida, pendiente de revisión completa o con flag provisional.
- ARCHIVE: disponible para investigación, no presentado como consolidado.

Nunca:
- tratar ausencia como 0;
- unir series de diferente unidad sin transformación explícita;
- afirmar causalidad a partir de correlación;
- ocultar cambios metodológicos;
- reutilizar el dato nacional para una CCAA.

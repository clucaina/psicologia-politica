# Cartografía local

Se conserva la cartografía que utiliza el mapa original, descargada el 3 de octubre de 2026:

- https://raw.githubusercontent.com/codeforgermany/click_that_hood/refs/heads/main/public/data/spain-communities.geojson
- https://raw.githubusercontent.com/codeforgermany/click_that_hood/refs/heads/main/public/data/spain-provinces.geojson

Proyecto de procedencia: https://github.com/codeforgermany/click_that_hood
Se mantienen los nombres, códigos y propiedades de los GeoJSON originales sin alterarlos.
El producto sirve estos archivos desde /geo/, sin descarga de GitHub en producción.

## V40: geometría de visualización (no fuente jurídica)

`spain-communities.geojson` permanece intacto. `spain-communities-display.geojson`
es una **copia derivada** para representación, generada mediante
`scripts/geo_display_sanitize_v40.py` (Shapely `make_valid`, conservando únicamente
los polígonos). Se corrigió una componente degenerada de área nula en Canarias
(ES70), sin variar el área poligonal. `GEOMETRY_QA_V40.json` contiene hashes,
identificadores y la trazabilidad exacta.

El archivo histórico simplificado tiene **29 pares de territorios con pequeñas
superposiciones geométricas**. No debe emplearse para intersecciones espaciales,
medir superficies, inferir límites administrativos/jurídicos o repartir cifras.
Los datos del Conflictómetro se vinculan mediante códigos NUTS2 explícitos;
no mediante intersecciones con polígonos. Ceuta conserva los islotes de la
cartografía fuente: no se reasignan sin una referencia oficial autorizada.

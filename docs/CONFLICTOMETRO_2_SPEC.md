# Conflictómetro 2.0 — especificación funcional

## Mapa
- Default: España 2D.
- Toggle Mundo 3D.
- 19 territorios NUTS2 españoles seleccionables.
- Hover: territorio, indicador, valor, unidad, año.
- Click: ficha territorial.
- Sin dato: gris/tramado. Cero: color válido de la escala.
- Leyenda cuantitativa siempre visible.

## Ficha territorial
Con T19 debe incluir cuando exista:
- población
- desempleo
- empleo
- PIB por habitante
- esperanza de vida
- AROPE
- renta disponible/habitante
- edad mediana
Cada tarjeta: valor, periodo, fuente, calidad y `Ver serie`.

## Tiempo
- año anterior/siguiente
- slider
- play/pausa
- velocidad
- rango
- animación del mapa
- si no existe dato para un año, explicarlo.

## Comparación
Hasta 5 territorios:
- líneas superpuestas
- tabla
- diferencia absoluta / %
- media España
- ranking
- cobertura.

## Correlación
X/Y, territorio o corte CCAA:
- scatter
- Pearson
- Spearman
- N
- missingness
- lag 0/1/3
- aviso fijo asociación != causalidad.

## Pregunta a España
Interpretar consultas como:
- dato puntual
- ranking
- serie temporal
- comparación
- relación entre variables
y responder solo con datos disponibles:
respuesta breve + gráfico/tabla + fuentes + enlaces de exploración.

## Export
CSV, PNG y URL reproducible que conserve estado/filtros.

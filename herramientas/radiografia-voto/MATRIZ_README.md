# Matriz de coincidencias parlamentarias

**Proyecto:** Psicología Política. **Ubicación:** `herramientas/radiografia-voto/matriz.html` en la misma carpeta que `archivo-xv-votos.json`. **Estado:** módulo independiente en revisión, todavía no desplegado en `psicologiapolitica.es`.

## Utilidad real

Calcula y explica una matriz simétrica de coincidencia entre grupos parlamentarios, basada únicamente en el sentido mayoritario que consta en cada votación documentada. La persona puede filtrar por período, materia y tipo, incluir o excluir abstenciones como tercera categoría, abrir cualquier cruce para inspeccionar las fuentes y exportar una matriz CSV.

La matriz lee **el archivo real** `archivo-xv-votos.json` ya recuperado en la PR #12. En la instantánea del 12/10/2026 había 2.175 votaciones y 19.561 registros por grupo. La etiqueta `Sin grupo` solo aparecía en cuatro registros y no se utiliza en la matriz; el grupo Mixto sí aparece, identificado como grupo heterogéneo, no como partido.

## Límites interpretativos

- **No mide afinidad electoral de la ciudadanía, intención de voto, afinidad ideológica ni pactos.**
- Una votación de trámite o una enmienda puede no representar el apoyo o rechazo a la totalidad de una política.
- Muchos votos de un mismo expediente pueden sobrerrepresentar un asunto, incluso filtrando por materia.
- Los grupos no son necesariamente equivalentes a partidos concretos y las decisiones internas pueden no ser unánimes.
- Una puntuación debe interpretarse junto a su denominador y el número de votos excluidos. Umbral configurable entre 10 y 100 registros.
- Una nueva publicación exige volver a ejecutar las pruebas y actualizar la fecha de la fuente, sin inventar actualidad.

## Pruebas

- `node --test matriz-qa.test.mjs` desde la carpeta junto a `archivo-xv-votos.json`.
- Prueba local de interfaz con Chromium, escritorio de 1440 px y móvil de 390 px, **sobre 90 votos sintéticos claramente identificados como fixture**. Se probaron carga, matriz, selección, cambio de filtros, abstenciones, umbral y reinicio; sin errores JavaScript ni desplazamiento horizontal fuera de la matriz.
- Auditoría del archivo real con el conector GitHub: 2.175 IDs únicos y fechas válidas; 61 votos mayoritarios en blanco y 36 empates, sin imputarlos como abstención; la cobertura sin grupo está apartada.

## Fuentes y licencia

Congreso Abierto (CC BY 4.0), que reutiliza datos oficiales del Congreso. [Descargas](https://congresoabierto.org/datos/), [método](https://congresoabierto.org/metodologia/) y [datos abiertos del Congreso](https://www.congreso.es/es/opendata/votaciones).

## Integración sin riesgo

Montar en la sección Herramientas de `psicologiapolitica.es`, primero en staging. No fusionar por esta entrega con el módulo de NeuroLab ni publicarlo automáticamente. Las respuestas del comparador de decisiones y la navegación interna de la matriz no deben enviarse a GTM, GA4 ni otros registros individualizados de preferencias políticas.
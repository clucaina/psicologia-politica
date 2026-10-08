# Analizador de texto v2 — especificación

## Objetivo
Pasar de detector de palabras a disección argumental y factual interactiva.

## Pipeline
1. Segmentación de oraciones y proposiciones.
2. Tipo: hecho / opinión / inferencia / predicción / causalidad / cita / comparación.
3. Detección de afirmaciones verificables.
4. Contraste con NPL local cuando haya cobertura.
5. Respaldo factual: ALTO / MEDIO / BAJO / CONTRADICHO / NO VERIFICABLE.
6. Falacias.
7. Sesgos cognitivos potencialmente activados.
8. Técnicas persuasivas/manipulativas.
9. Trampas estadísticas.
10. Mapa argumental.
11. Reformulación.

## Falacias mínimas
ad hominem, hombre de paja, falso dilema, pendiente resbaladiza, autoridad, mayoría,
generalización apresurada, petición de principio, falsa causa, correlación-causalidad,
cherry-picking, falsa equivalencia, whataboutism, tu quoque, moving goalposts,
motte-and-bailey, anécdota, carga de la prueba, comparación incompleta.

## Sesgos/técnicas
confirmación, disponibilidad, negatividad, anclaje, halo, homogeneidad exogrupal,
atribución, arrastre, identidad grupal, aversión a la pérdida, framing, saliencia,
urgencia, amenaza, enemigo común, culpabilización, nosotros/ellos, cuantificadores vagos,
presuposición, lenguaje cargado, repetición, certeza injustificada, autoridad vaga.

## Estadística
Porcentajes sin denominador, tasas vs absolutos, media vs mediana, riesgo relativo vs absoluto,
selección temporal, muestras pequeñas, base cambiante, cherry-picking, correlación/causalidad.

## UX
El texto queda resaltado por capas. Click en fragmento -> panel con:
- qué se detectó;
- evidencia;
- por qué;
- confianza;
- contraejemplo;
- reformulación;
- `Comprobar en Datos`;
- `Ver en mapa`;
- `Explorar relación`.

Nunca atribuir intención psicológica no demostrada. Usar `señal compatible con` / `puede activar`.

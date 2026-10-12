# Radiografía del voto · Psicología Política

**Estado 12/10/2026:** implementación independiente en rama de revisión. No se ha desplegado a psicologiapolitica.es ni fusionado con la rama principal.

## Dos herramientas complementarias

- **Comparador documental:** abrir index.html. Contiene ocho votaciones explicadas, ocho grupos parlamentarios y 64 posiciones verificadas frente al CSV de fuente. Devuelve coincidencias con esta muestra, no recomendaciones electorales.
- **Archivo completo:** abrir archivo.html desde un servidor HTTP(S). Consulta archivo-xv-votos.json con 2.175 votaciones del Pleno de la XV legislatura y 19.561 registros de grupo (descarga de 12/10/2026, últimas votaciones de 30/09/2026). Filtros, serie anual, sentido mayoritario y exportación CSV.
- **Banco para programas:** banco-preguntas-programas.json contiene 40 preguntas originales sobre 14 áreas. Ninguna posición de partido está asignada: no usar para calcular afinidades sin fuente, fecha y revisión.

## Fuente y términos

Reutilización bajo CC BY 4.0. Atribución obligatoria: **Congreso Abierto (congresoabierto.org), a partir de datos oficiales del Congreso de los Diputados**.

- https://www.congreso.es/es/opendata/votaciones
- https://congresoabierto.org/datos/
- https://congresoabierto.org/metodologia/
- https://github.com/MarcoAnarmo/CongresoAbierto

Un grupo parlamentario no equivale necesariamente a todos los partidos integrantes. El Grupo Mixto se excluye del comparador porque no tiene postura partidista homogénea, aunque aparece en el archivo. Las materias se etiquetan con palabras del título, no tras una calificación jurídica exhaustiva.

## Metodología

El usuario elige sí, no o no tengo criterio, y la importancia 1, 2 o 3. Solamente se comparan respuestas sí/no frente a mayorías sí/no. Las abstenciones no se imputan como apoyo ni oposición. Primero se obtiene la coincidencia ponderada dentro de cada tema; después se calcula la media entre temas comparables. Se exigen al menos dos temas para mostrar un porcentaje transversal. Este porcentaje no es probabilidad de voto ni una medida psicométrica. Las respuestas viven exclusivamente en la memoria de la pestaña y desaparecen al recargar.

La muestra actual no representa toda la legislatura, pues 6 de 8 casos tratan sobre vivienda. El archivo completo no se convierte sin revisión en un test: contiene votos procedimentales, por puntos y complejos.

## Actualización reproducible

    python3 scripts/actualizar_archivo.py --dry-run
    python3 scripts/actualizar_archivo.py

El importador usa Python estándar: obtiene los dos CSV de origen, comprueba cabeceras, identificadores, duplicados, referencias cruzadas, fechas, cifras, votos permitidos y SHA-256, y evita regresiones de cobertura sin autorización. Si falla una descarga no sobrescribe datos. El script aún no se ha ejecutado aquí; el primer archivo se elaboró directamente de ambos CSV mediante un procedimiento equivalente y se comprobó su integridad referencial.

Un voto secreto o no nominal puede carecer de detalle de los grupos; ausencia de detalle no significa abstención.

## Integración posterior

Destinarlo únicamente a Psicología Política, bajo Herramientas, sin sustituir Conflictómetro, mapas, noticias, blog ni la base de datos. Montar primero en staging y comprobar navegador real y móvil: fuentes, controles, contraste, accesibilidad y exportación. Ninguna respuesta personal del test debe enviarse a GTM, GA4 ni a un perfil persistente. Si se mide el uso del módulo, hacerlo con eventos agregados y el consentimiento que corresponda.

## Fuera del alcance comprobado

- No hay matriz completa de los programas electorales de 2026.
- No hay pruebas de navegador y móvil finalizadas para esta herramienta.
- No hay publicación en psicologiapolitica.es ni sincronización automática de los CSV.
- No se han unido votos con los 50.031 datos de NPL; correlación no implica causalidad.
- Esta entrega es una instantánea documentada, no datos en tiempo real.

## Pruebas de aceptación

Exigir: ocho fichas con fuentes, tabla completa de votos, filtros y CSV, correspondencia con votos por grupo, tratamiento no sesgado de abstenciones y coberturas bajas, y ausencia de perfilado político personal.
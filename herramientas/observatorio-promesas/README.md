# Observatorio de promesas documentadas

**Destino:** Herramientas de Psicología Política. Este módulo no sustituye el Conflictómetro, el archivo de votaciones ni el comparador de partidos. Es un cuaderno editorial independiente para estudiar afirmaciones específicas con su fecha y ámbito competencial.

## Qué permite hacer
- Crear, buscar, modificar, duplicar y eliminar fichas documentales.
- Diferenciar actor, fecha, plazo, competencia, texto original y conclusión.
- Anexar documentos con enlaces verificables, naturaleza primaria/secundaria y alcance del hallazgo.
- Construir un grafo de relaciones visual y cronología de documentos.
- Comprobar seis elementos de documentación; **no hay puntuación de verdad ni detector automático de mentiras**.
- Guardar únicamente en el navegador, importar o exportar JSON, exportar CSV saneado contra ejecución de fórmulas y generar informe imprimible.
- Cargar un único escenario expresamente ficticio, sin atribuir ningún hecho a formaciones reales.

## Limitaciones y salvaguardas
La existencia de un voto favorable, un presupuesto aprobado o un plazo vencido no demuestra por sí sola cumplimiento o incumplimiento de una promesa. Revisar el órgano competente, condiciones y fase real de ejecución. La herramienta no descarga documentos: el investigador debe abrirlos y leerlos.
Los seis controles de documentación indican presencia de información, no calidad de la evidencia. Dos URL diferentes pueden depender del mismo comunicado. El número de dominios no se presenta como validación de independencia.
No recopilar opiniones políticas personales de visitantes ni enviar texto de fichas a GTM, GA4 u otro servicio.
Los datos se guardan mediante localStorage en el mismo equipo/navegador, sin cifrado ni acceso multiusuario. No introducir información confidencial o datos personales sensibles.

## Implementación
Ruta propuesta: `/herramientas/observatorio-promesas/index.html` en Psicología Política. Es un HTML autónomo, sin librerías externas; la rama de desarrollo no modifica index.php, MySQL ni las rutas actuales. Validar en un staging con la .htaccess del hosting, probar importación/exportación, accesibilidad y visualización antes de integrarlo en la navegación pública.
Para uso editorial en equipo y datos privados sería necesario sustituir el almacenamiento local por un backend con autenticación, versiones, auditoría de cambios y controles RGPD. No está implementado.

## Fundamentos documentales y normativos
- Congreso de los Diputados, datos abiertos: https://www.congreso.es/es/datos-abiertos
- AEPD, categorías especiales de datos: https://www.aepd.es/preguntas-frecuentes/2-tus-obligaciones-como-responsable-del-tratamiento/5-bases-legitimadoras-del-tratamiento/FAQ-0215-cuales-son-las-bases-de-legitimacion-para-el-tratamiento-de-las-categorias-especiales-de-datos

## Comprobaciones
`node --test herramientas/observatorio-promesas/tests/test-promises.mjs`
Las pruebas automatizadas verifican modelo documental, sintaxis, URL y ausencia de telemetría. No reemplazan una prueba visual/interactiva en navegador.

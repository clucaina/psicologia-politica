# NeuroLab — benchmark funcional y hoja de implementación original

**Fecha:** 12/10/2026. **Estado:** MVP autónomo comprobado en Chromium. **Objetivo:** adaptar patrones de producto públicos a Psicología Política, NPL/Conflictómetro, SICNAR, Neuropsicolucía, Psicología Judicial y NeuroCity, sin reutilizar activos protegidos ni afirmar adopciones inexistentes.

## Fuentes públicas consultadas

- https://replikactor.es/ — talleres, ejercicios interactivos, ensayo con IA, directorios, comunidades y empleabilidad.
- https://replikactor.es/universo — módulos formativos y utilidades, algunas experimentales.
- https://replikactor.es/manana-tienes-casting — decisiones sobre escenarios con retroalimentación.
- https://replikactor.es/para-productoras — directorios, verificaciones, paneles y visibilidad de perfiles.
- https://carnetsim.com/ — situaciones en primera persona con consecuencias y referencias normativas; glosarios, simulacros y progreso.
- https://tarotdorado.es/ — conversación guiada, relatos, juegos de mentalismo, progreso y audios; distinción entre simbolismo y ciencia.
- https://tarotdorado.app/privacidad — enfoque local y límites declarados del tratamiento de datos.

La referencia documental recoge **funcionalidades generales**, no transcribe materiales formativos, escenas, textos, diseños, personajes, marcas ni código de terceros. Las soluciones propuestas pueden desarrollarse de forma independiente. Revisión jurídica de propiedad intelectual recomendada antes de adoptar cualquier elemento expresivo identificable.

## Capacidades priorizadas — producto y rigor

| Patrón general | Implementación original | Destino | Prioridad | Cautelas |
|---|---|---|---|---|
| Microlecciones breves | Academia de lectura crítica con bibliografía, cuestionarios propios | Psicología Política / Neuropsicolucía | P0 | Revisión de expertos, fuentes |
| Decisiones con consecuencias | Doce escenarios originales con alternativas y feedback contextual | Psicología Política / Conflictómetro | P0 — MVP | No evaluar personas ni riesgos reales |
| Progreso sin cuenta | Almacenar módulos visitados solo en navegador, exportar/borrar | Transversal | P0 — MVP | Nada de historiales clínicos |
| Simulador con parámetros | Escenarios contrafactuales *hipotéticos* con fórmulas transparentes | NPL / Conflictómetro | P0 — MVP | No confundir índice didáctico con validación |
| Glosario contextual | Diccionario de conceptos científicos, jurídicos y estadísticos con referencias | NPL / Neuropsicolucía | P1 | Versionar definiciones |
| Comparador de formaciones | Buscador por ámbito, precio, acreditación, accesibilidad, fechas | Psicología Judicial | P1 | Comprobar centros y precios |
| Mapa de oportunidades | Convocatorias de investigación, ponencias, becas y encuentros científicos | Neuropsicolucía | P1 | Procedencia, actualización, expiración |
| Detector de anuncios problemáticos | Lista de comprobación de transparencia de ofertas, formaciones y servicios | Psicología Política | P1 | Explicación de reglas, sin acusaciones |
| Editor de guiones y flujos | Secuencias de entrevista, preguntas, hipótesis rivales y revisiones | SICNAR | P1 | Confidencialidad pericial, nada público |
| Simulaciones de entrevistas | Respuesta guiada por reglas en escenarios ficticios, con trazabilidad | SICNAR / docencia | P1 | No reemplazar perito ni dictaminar |
| Tutor de métodos | Explica errores de razonamiento y pide evidencias adicionales | NPL | P1 | Calibración, no falsa certeza |
| Casos de decisiones éticas | Dilemas de confidencialidad, capacidad, consentimiento y derechos | Neuropsicolucía | P1 | Material educativo, no asesoría legal |
| Formación basada en misiones | Ruta por barrios reales con retos de movilidad, espacio público y fuentes | NeuroCity | P2 | Separar ficción de geodatos reales |
| Diccionario de territorio | Fichas de zonas de Alicante con procedencia cartográfica y cambios | NeuroCity GEO | P2 | IGN/PNOA/OSM y atribución |
| directorio profesional | Profesionales, escuelas, grupos y recursos con consentimiento | Psicología Judicial | P2 | Moderación, protección datos |
| Portfolio verificado | Credenciales comprobables, sin rankings ni títulos ficticios | Psicología Judicial | P2 | Consentimiento y verificabilidad |
| Video breve didáctico | Microcápsulas originales de prevención de errores metodológicos | Neuropsicolucía | P2 | Metadatos y accesibilidad |
| Audio de aprendizaje | Audioguías originales de técnicas de estudio y razonamiento | Neuropsicolucía | P2 | No presentarlo como psicoterapia |
| Mapa cronológico | Serie temporal sobre fenómenos públicos y límites de inferencia | NPL / Conflictómetro | P1 | Fuente, población, incertidumbre |
| Comparador de narrativas | Relatos alternativos sobre mismos hechos con detección de sesgos | Psicología Política | P1 | Distinguir hechos de opiniones |
| Conocimiento por niveles | Rutas para alumnos, juristas, docentes, ciudadanos | Academia | P1 | Criterios pedagógicos transparentes |
| Automatización editorial | Plantillas originales de revisión científica y atribución | Psicología Política | P2 | Revisión humana de publicaciones |
| Laboratorio de fuentes | Fichas de fiabilidad, método, cobertura y conflicto de interés | NPL | P1 | Trazabilidad y licencias de datasets |

## Qué se ha implementado

La demo `index.html` contiene:

- 12 escenarios hipotéticos en cuatro categorías y tres opciones por caso.
- Explicaciones de por qué una inferencia es o no defendible y el siguiente dato a contrastar.
- 6 microlecciones de 2 minutos y prácticas guiadas originales.
- Modelo demostrativo con 3 parámetros, pesos visibles y actualización inmediata.
- Registro local de casos y lecciones, borrado y exportación JSON.
- HTML/CSS/JS autónomo, sin frameworks, llamadas externas, cookies, telemetría ni registro.
- Presentación responsive; controles accesibles con teclado y foco visible.

**No implementado:** integración con bases NPL reales, módulos periciales con casos, administración, acceso multiusuario, servidor ni publicación en Hostinger. No existe aún validación psicométrica ni causal. El índice de simulación es puramente didáctico.

## Verificaciones realizadas

Chromium automatizado con Playwright: carga sin errores de JavaScript; 12 escenarios, 6 lecciones, filtrado, respuestas y retroalimentación, simulación, progreso, presentación móvil y escritorio. Revisiones de accesibilidad adicionales pendientes: WCAG completo, lectores de pantalla y contraste especializado.

## Plan de integración seguro

1. **Rama separada en GitHub**, sin tocar `main`, despliegue automático ni `public_html`.
2. Publicar esta demo como archivo estático en ruta independiente solo tras revisión editorial.
3. Resolver discrepancias entre las fuentes oficiales de NPL antes de importar datos reales.
4. Sustituir en el simulador los valores ficticios por datos verificados únicamente cuando se acuerden definición, cobertura, métodos y umbrales; no trasladar arbitrariamente la fórmula de demo.
5. Ofrecer revisión profesional y piloto docente con evaluación de usabilidad y aprendizaje; no calificar clínicamente a usuarios.
6. Ampliar módulos SICNAR solo en infraestructura confidencial separada y autorizada.
7. Para NeuroCity, utilizar las mismas mecánicas educativas reescribiendo los casos y asociándolos a las geoteselas validadas, sin intervenir en jugabilidad ni versiones operativas.

## Límites legales y de integridad

Los conceptos de producto, mecánicas pedagógicas y flujos generales pueden inspirar aplicaciones independientes. No copiar textos concretos, interfaces distintivas, artículos, imágenes, manuales, marcas o bases de datos no licenciadas. Tampoco atribuirse proyectos ajenos. Separar educación, divulgación, evaluación psicométrica y ejercicio profesional de psicología. Incorporar revisiones técnicas de privacidad y accesibilidad antes de publicar.
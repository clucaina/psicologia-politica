# NeuroLab Arcade

La academia amplía su contenido con ocho experiencias interactivas. El archivo `arcade-autonomo.html` funciona por sí solo e incluye todas las imágenes y el JavaScript. No solicita cuentas, ni envía datos a un servidor.

## Experiencias disponibles

1. El expediente que no encaja. Nueve piezas documentales y revisión de hipótesis.
2. Redacción en llamas. Ocho titulares sobre cifras, muestras y causalidad.
3. Una ciudad, cien consecuencias. Cuatro decisiones de política pública y presupuesto ficticio.
4. La noche que todos recuerdan distinta. Siete preguntas sobre percepción y sugestión.
5. El taller de las líneas. Dibujo interactivo, mediciones geométricas y exportación PNG.
6. Cuatro voces, una historia. Personajes ficticios y síntesis de voz del navegador.
7. La ruta que falta. Paseo ilustrado explorable con teclado o botones, barreras ficticias y tres zonas de observación.
8. La pregunta que cambia el relato. Tres entrevistas ficticias y doce elecciones sobre preguntas no sugestivas.

## Integración propuesta

En Psicología Política, la redacción y la entrevista ofrecen ejercicios de interpretación y comunicación. Para NPL y el Conflictómetro, el simulador urbano ayuda a enseñar a separar datos, medidas y efectos hipotéticos. En NeuroCity, las rutas y los diálogos ofrecen prototipos narrativos sin alterar la reconstrucción geográfica ni el motor existente. En SICNAR y Psicología Judicial, el expediente y la entrevista permiten enseñar trazabilidad y límites probatorios. En Neuropsicolucía, la memoria visual y el taller de dibujo son actividades divulgativas, nunca pruebas diagnósticas.

## Material propio, recursos públicos revisados y licencias

Las imágenes originales de ambiente y personajes incluidas en la edición han sido creadas para este módulo. No se han incluido recursos de terceros. Se han investigado alternativas para siguientes versiones.

- Kenney proporciona recursos gratuitos CC0 en https://kenney.nl/support
- DiceBear tiene código MIT, pero las imágenes varían según la licencia del estilo. Lorelei está identificado como CC0 en https://www.dicebear.com/styles/lorelei/
- Lucide ofrece iconos de uso libre bajo condiciones ISC y algunas excepciones históricas que constan en https://github.com/lucide-icons/lucide/blob/main/LICENSE
- Para voz se utiliza el motor nativo del navegador documentado en https://developer.mozilla.org/en-US/docs/Web/API/SpeechSynthesis

## Límites y comprobaciones

Los datos de los juegos y sus efectos son ficticios. Las puntuaciones no tienen validez clínica ni pericial. Las escenas del puerto mediterráneo son ilustraciones, no levantamientos verificados de Alicante.

Las pruebas automáticas locales han comprobado que las ocho pantallas pueden generarse sin excepciones de JavaScript, y que cinco caminos completos de juego alcanzan su final con la lógica esperada. El entorno no permite ejecutar pruebas visuales con Chromium porque su política bloquea las URL locales. Todavía se necesita revisión humana en navegadores y móviles, así como comprobaciones de accesibilidad siguiendo https://www.w3.org/WAI/standards-guidelines/wcag/docs/

Para memoria y entrevista, revisar National Academies, Identifying the Culprit, https://www.nationalacademies.org/read/18891/chapter/8. Para futuros sistemas automatizados, consultar el marco NIST AI RMF https://www.nist.gov/publications/artificial-intelligence-risk-management-framework-ai-rmf-10

Este trabajo se mantiene en una rama separada y una solicitud de cambios en borrador. No se ha desplegado en producción.

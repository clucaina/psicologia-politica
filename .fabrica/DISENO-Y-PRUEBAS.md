# Diseño visual y funcional — Psicología Política
Objetivo: medio editorial y laboratorio de datos públicos verificables.

## Incorporaciones
1. Inicio editorial con historia principal, secundarios y acceso directo al Conflictómetro; imágenes de alto impacto subordinadas a datos.
2. Conflictómetro con mapa sobrio, periodo y variables. Selección territorial actualiza mapa, serie, unidad y fuente sin perder contexto.
3. Comparaciones de CCAA con denominadores compatibles, intervalos y cobertura; no inferir causalidad de correlaciones.
4. Analizador de argumentos que separa citas, datos, inferencias, opiniones y errores con incertidumbre explícita.
5. Animación solo ante cambios; gráficos con alternativa tabular y modo sin WebGL.

## Pruebas y garantías
- Filtros, mapa, timeline y gráficos realmente funcionales; prueba teclado, móvil y contraste WCAG 2.2 AA.
- Toda cifra con fuente, fecha, periodo, definición y población.
- Validar permisos, editor, comentarios y protección contra XSS y CSRF.
- No desplegar a Hostinger ni migrar MariaDB.
- Recuperar y comparar la versión canónica antes de tocar fuentes incompletas.

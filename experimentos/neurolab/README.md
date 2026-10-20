# NeuroLab: Laboratorio de decisiones — prototipo original

Página estática, autónoma y gratuita para educación en análisis de evidencia, sesgos y metodología. Inspiración en patrones generales de microaprendizaje y simulación situacional, con textos, diseño, datos y código propios.

## Cómo probar

Abrir `index.html` en un navegador. Funciona sin APIs ni dependencias externas. En determinados navegadores, `localStorage` al abrir archivos `file:` puede estar deshabilitado; el simulador y las lecciones siguen funcionando aunque no se guarde el avance.

## Módulos

- 12 decisiones ficticias y retroalimentación de inferencias.
- 6 lecciones originales breves.
- Simulador de indicadores hipotéticos con ponderaciones visibles.
- Progreso solo en el navegador y exportación JSON opcional.

## Límites

No clasifica rasgos psicológicos, no hace diagnósticos y no produce inferencias clínicas, legales o poblacionales reales. Todos los escenarios y parámetros son hipotéticos. Nunca introducir datos personales de peritados.

## Archivos

- `index.html`: producto interactivo.
- `PLAN_INTEGRACION.md`: benchmark de funciones y ruta de integración por producto.
- `captura-escritorio.png`, `captura-movil.png`: comprobación visual.

## Estado técnico

Probado en Chromium mediante Playwright: navegación, respuestas, filtros, progresos, fórmula y vista móvil. Falta integrar en la web operativa: este módulo debe permanecer aislado hasta validación editorial y de producción.
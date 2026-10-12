# NeuroLab Nexo. Recuperación verificable y siguiente integración

## Trabajo desarrollado el 12 de octubre de 2026

Se ha generado y probado en contenedor un nuevo módulo autónomo para ocho líneas de proyecto. **El HTML completo de Nexo y los recursos binarios están todavía en el paquete local descargable; este documento no equivale a tenerlos desplegados ni subidos íntegramente a GitHub.** La subida a la Biblioteca de archivos no fue posible por límite de almacenamiento. No se ha borrado ningún archivo para liberar espacio.

### Ocho experiencias funcionales en el paquete local
1. Neuropsicolucía. Ocho microcasos con opciones, fundamentación, tarea y progreso.
2. NPL / Conflictómetro. Comparador de 19 territorios y ocho indicadores; 152 observaciones reales recuperadas de T19 con localizador, periodo y URL oficial.
3. SICNAR. Simulación ficticia de entrevista en seis turnos, con explicación metodológica y exportación educativa.
4. NeuroCity. Barrio **ilustrado y no georreferenciado**, reparto presupuestario ficticio, cuatro misiones de accesibilidad, movilidad y costes.
5. Psicología Judicial. Directorio de ocho **perfiles inventados** con filtros por especialidad y modalidad; pendientes verificación y consentimiento antes de usar identidades reales.
6. Psicología Política. Ocho titulares ficticios con alternativas editoriales y reescritura.
7. NPL / NeuroCity. Cuatro cruces viales ilustrados con toma de decisiones y feedback. No son examen DGT.
8. Todos. Progreso guardado localmente y exportable, sin fichas clínicas ni sistemas de diagnóstico.

### Pruebas
Chromium con Playwright y `page.set_content` (la navegación local está bloqueada): 9 botones de sección, 8 tarjetas, 19 barras y 8 métricas, cambio de territorio, ocho lecciones, entrevista, cuatro misiones, directorio 8/2 perfiles tras filtro, argumento, conducción, exportación y seguimiento. Pantallas 1366×850 y 390×844, sin errores JS ni overflow horizontal en los recorridos comprobados. No sustituye auditoría WCAG ni revisión científica.

## Recursos de trabajo recuperados

- **NPL/Conflictómetro T18**. Checkpoint documental 50.031 observaciones, 318 indicadores y 41 familias de fuente. No se importa como datos publicados.
- **T19_CCAA_BASELINE.json**. 152 observaciones de ocho series y 19 territorios. El checkpoint T19 proyecta 50.183 observaciones tras validación y deduplicación. Conservar unidad, subgrupo, periodo, dataset_code, source_url y source_locator en cada observación. Archivo recuperado desde Biblioteca; no modificar hasta cotejo.
- **Neuropsicolucía V30**. Paquetes de preintegración y entrega offline localizados en Biblioteca. Conservar límite de `CanonicalBrainEmbed` y motor de NPL; no duplicar GLB ni renderer.
- **NPL-CANONICAL**. `NPL-CANONICAL-GITHUB-READY.zip`, `NPL-PROGRESS.md`, demos de ciudad, viewer y scripts de fotogrametría en GitHub. No modificar canónico ni GEO.
- **NeuroCity**. Drive contiene carpetas `NeuroCity` y `NEUROCITY_GEO_LAB`, visor 3D y documentos de defectos. Mantener v50 y teselas G05. No confundir ilustración del Nexo con cartografía Alicante.
- **SICNAR**. Hipótesis alternativas, cronología, genealogía de fuentes, acuerdo interevaluador y futura validación; sus instrumentos siguen sin estar validados clínicamente.
- **NeuroLab Arcade**. Ocho minijuegos previos y academia ampliada están preservados en esta rama y en el paquete local previo.
- **Psicología Política**. Ramas `integracion/v76-seguridad-npl`, `integracion/v78-maestro-sin-despliegue` y `preproduccion/v16-integracion-sin-work`. No fusionar automáticamente ni tocar Hostinger, MariaDB, GTM y Blog.

## Puertas de aceptación

- Incorporar Nexo únicamente como archivo estático aislado, sin cambios al sitio en producción.
- Revisar titularidad/licencia de cada asset y atribución de datos oficiales.
- Datos T19 solo en una sección marcada PREINTEGRACIÓN hasta comprobación documental, coherencia de periodos, cobertura y deduplicación.
- No publicar expedientes ni datos reales de peritados. Las entrevistas simuladas jamás son detectores de mentira.
- Directorios reales requieren consentimiento, acreditación, políticas de publicación, rectificación y retirada.
- Revisiones humanas de ciencia, derecho, accesibilidad, privacidad y usabilidad.
- No usar el simulador de barrio para predicciones causales ni decir que representa Alicante real.
- El navegador puede persistir datos locales; prohibir añadir historiales reales al simulador de entrevistas.

## Fuentes y referencia pública

INE datos abiertos: https://www.ine.es/datosabiertos/
BOE Ley 8/2021: https://www.boe.es/diario_boe/txt.php?id=BOE-A-2021-9233
BOE Ley 44/2003: https://www.boe.es/buscar/act.php?id=BOE-A-2003-21340

**Estado:** inventario y diseño guardados en GitHub. Binarios, código completo Nexo y datos materializados disponibles como ZIP local en la conversación; su subida completa al repo queda pendiente de una vía de transferencia adecuada.
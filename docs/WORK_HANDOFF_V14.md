# Handoff a GPT Work · Psicología Política V14 · PREPRODUCCIÓN

**Única base de código:** `PSICOLOGIAPOLITICA_REPO_PATCHED_V14.zip` dentro de este paquete maestro. Partir de V14; no reconstruir versiones anteriores. No modificar producción sin autorización expresa.

## Secuencia para Work (priorizar integración y pruebas, no repetir investigación)

1. Crear repositorio GitHub privado desde V14, branch `staging`, secretos fuera de Git, staging aislado en Hostinger. Ejecutar `.github/workflows/qa.yml`; corregir cualquier divergencia entre Chromium instalado en CI y las pruebas locales. Hacer backup verificado y ensayar restauración.
2. Conectar **MariaDB real de staging** con copia anonimizadora de la estructura y contenido autorizados. Probar migraciones con dry-run, rollback, ledger, deduplicación y lotes T19–T37. No importar T36 ni otros candidatos como publicados sin revisión y trazabilidad de origen.
3. Integrar `/mapa-espana` con datos publicados reales: 19 regiones + España, colores sin agregar nacional, series, 0/null, preguntas, comparación, ranking y CSV. Revisar `npl_analytics.php` con MySQL/MariaDB real y duplicados INE/Eurostat. Mantener `noindex` hasta GO.
4. Blog con dos autores y un lector: recuperar artículo por ID, autoría/foto, guardado local, `expected_updated_at` y **`expected_content_hash`** (409), guardados parciales, revisión, imagen, Markdown, XSS y CSRF. El cliente compilado V14 ya envía la huella; revisar reconstrucción futura desde fuente React para no perderla.
5. Validar fuentes RSS/Atom reales con PHP SimpleXML, locking de ingesta y cola editorial; deduplicar DOI/título/URL. Probar T37 en flujo editorial y generar imágenes originales tras aprobación; no autopublicar.
6. Analizador V2: probar integración UI↔API, tipos de proposición, negación/cita, falsos positivos y enlaces a NPL. Nunca etiquetar verdad o intención por estilo lingüístico.
7. Completar auditoría visual/SEO/SEO social en staging, navegación, accesibilidad móvil, caché, 429, seguridad, imágenes, rendimiento y rollback. Corregir el fallback genérico de Conflictómetro **en la versión que se despliegue**. Solo quitar `noindex` y desplegar producción después de GO documentado y autorización.

## Datos y restricciones

- **522** observaciones preparadas, no equivalen a 522 publicadas; 36 derivadas. T36 añade una observación nacional nueva, sin inferir datos de CCAA. No confundir personas en hogares con hijos con niños en pobreza.
- **15** candidatas editoriales en T29/T33/T34/T35/T37, con 3 nuevas T37. No atribuir resultados no comprobados al estudio de percepción partidista; su ficha es deliberadamente cauta.
- **237** fechas de consulta faltantes, **327** poblaciones explícitas faltantes, **237** tipos de medida faltantes. Revisar en fuente o evidencia del revisor, nunca inventar.
- **19** valores AROPE coincidentes entre INE y Eurostat, solapamiento no independiente. Mantener bloqueos de comparabilidad y trazabilidad.

**NO-GO:** falta SQL de staging, pruebas autenticadas multiusuario, SimpleXML real, ejecución CI, despliegue reversible y revisión humana. No confundir tests con API simulada con integración real.

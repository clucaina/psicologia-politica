# QA release gate V12 · NO-GO

**PASS local:** sintaxis PHP/JS; JSON; pruebas de NPL, SSR y Markdown; 521 claves naturales únicas; 19 territorios; 0 ≠ null; tests de «Pregunta a España» para preguntas poblacionales incompatibles; 320 px, fuente y límites; bloqueo compartido de ingesta en código; datos sintéticos JSON-stat 2.0; navegador con API simulada.

**PENDIENTE:** PHP 8.3 + SimpleXML real para RSS/Atom; ingesta concurrente contra MySQL; test de importación/rollback con BD aislada; CI en GitHub; Blog autenticado multiusuario; React integrado; visuales reales, lector de pantalla, performance, seguridad, CSP, rate-limit, WAF, backups, staging y verificación de las 237 fechas de consulta.

**Hallazgos públicos no corregidos en producción:** SSR genérico en herramientas/contacto y Markdown escapado en Blog. Ver `database/PUBLIC_AUDIT_V12.json`.

**GO requiere:** staging estable y reversible, todas las pruebas críticas verificadas con logs, fuentes de datos publicables auditadas, aprobación editorial y autorización explícita para producción. No usar SQL destructivo, no sobrescribir `public_html` manualmente, no modificar el sitio actual desde este paquete.

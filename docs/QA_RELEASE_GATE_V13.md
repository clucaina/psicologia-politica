# Release gate V13 — NO-GO

## PASS offline

- PHP 23/23 lint; JS 51/51 sintaxis; JSON 34/34 parse; datos 521/521 claves naturales únicas, 36 derivados.
- Auditoría de porcentajes, geo_codes y derivaciones; `0` y `null` diferenciados.
- Browser mock 320/375/768/1280: selección de 19 territorios, zoom, búsqueda, comparación, series, pregunta, no overflow ni pageerror.
- `npl_reviews.evidence` incorporada al contrato de API; test unitario de precedencia de metadatos.
- `GET /mapa-espana` devuelve HTML desde PHP local sin abrir DB; `noindex` hasta GO.

## PENDIENTE / bloquea producción

- Prueba real SQL `coverage` con MariaDB/MySQL y revisión de índices/plan de consulta.
- Validación real de `pp_analytics_point` con observaciones publicadas y `npl_reviews.evidence` tras migración.
- Revisión documental humana: 237 fechas `retrieved_at`, 327 poblaciones y 237 tipos de medida ausentes en JSON de origen.
- Blog: autenticación, dos cuentas, autosave por ID, firma/foto, 409 de concurrencia, restauración y XSS.
- Feed RSS/Atom con SimpleXML + red HTTP Hostinger, cron y bloqueo concurrente.
- SPA principal, SEO SSR y contenido no genérico, accesibilidad WCAG 2.2 AA, rendimiento móvil y WAF/429.
- Backup/restore, despliegue GitHub→Hostinger, rollback y verificación de persistentes en staging.
- Aprobación editorial y autorización explícita para desplegar.

No llamar 'publicado' a lo que solo pasó mock o lint. No modificar producción desde este paquete.

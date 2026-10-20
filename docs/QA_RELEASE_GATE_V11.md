# Gate V11 · NO-GO producción

| Área | Estado | Evidencia o bloqueo |
|---|---|---|
| Sintaxis PHP/JS/JSON/YAML | PASS local | CI V8–V11, sintaxis y parsing |
| Mapa 2D responsive/teclado | PASS con mocks | Chromium 320/375/768/1280, reduced-motion, forced-colors |
| 521 observaciones / 25 indicadores | PASS estructura | 0 duplicados por clave natural; 36 derivadas; datos aún sin publicar |
| AROPE doble fuente | WARN | 19 valores coinciden; cotejo metodológico humano pendiente |
| T19/T26 fecha consulta | BLOQUEADO | 237 fechas no verificadas |
| T30/T31 y T32 | PENDIENTE | Validación editorial final, fuente y muestra |
| Blog multiusuario | PENDIENTE | Prueba autenticada, autosave, autoría, 409, CSRF |
| NPL MySQL/migraciones | PENDIENTE | Dry-run, staging, rollback real |
| SPA y APIs | PENDIENTE | Prototipo no integrado en React/Hostinger |
| RSS/DOI dedup | PASS unidad / PENDIENTE integración | Falta prueba feeds y BD real |
| SEO/SSR producción | FALLO CONOCIDO | `/conflictometro` fallback genérico; otras rutas no revalidadas |
| WAF/429 | PENDIENTE | Cabeceras PHP listas; distinguir límite de proxy |
| Accesibilidad WCAG/Lighthouse | PENDIENTE | Tests parciales no equivalen a auditoría completa |
| Backup/restore/rollback | PENDIENTE | No ensayados en Hostinger |
| Autorización producción | NO | Requiere permiso expreso |

**Decisión: NO-GO.**

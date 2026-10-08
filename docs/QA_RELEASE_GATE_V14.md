# QA release gate · V14

| Área | Estado | Evidencia / condición de salida |
|---|---|---|
| Sintaxis PHP/JS/JSON | PASS local | 25 PHP, 51 JS, 38 JSON |
| Integridad de tandas preparadas | PASS estructural | 522 claves naturales, 36 derivadas, 0 colisiones; no es verificación científica definitiva |
| Mapa 19 territorios + España | PASS con API simulada | 4 anchos, cero/null, rango y ranking regional sin agregado nacional, comparación y CSV |
| CSV y protección de fórmulas | PASS con API simulada | Descarga CSV 20 ámbitos, sin datos inventados ni fórmula ejecutable |
| Blog huella concurrente | PASS unitario/estático | SHA-256, client bundle y backend; **pendiente 2 usuarios + MySQL** |
| Pregunta a España | PASS unitario | AROPE general vs hogares con hijos; causalidad y población infantil bloqueadas |
| Noticias | PASS estático | 15 candidatas, sin DOI duplicado, 3 nuevas; RSS/Atom real pendiente |
| PHP/MySQL MariaDB staging | BLOCKED | Sin conexión, no probado |
| GitHub Actions CI | PENDIENTE | Workflow actualizado, aún no ejecutado en GitHub |
| Blog autenticado y SPA | PENDIENTE | Dos autores + lector, conflictos 409, foto/autoría y CSS real |
| Auditoría producción | PARCIAL | Fallback SSR genérico confirmado en Conflictómetro; portada y Blog no verificables |
| Hostinger reversible | PENDIENTE | Backup restaurado, staging aislado, migraciones, rollback, smoke tests |
| Producción | **NO-GO** | No se ha desplegado ni autorizado desplegar |

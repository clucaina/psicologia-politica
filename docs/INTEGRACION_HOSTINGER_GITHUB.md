# Preparación GitHub ↔ Hostinger (sin sobrescritura)

- Rama de auditoría: `preproduccion/auditoria-2026-10-08`, aislada de `main`.
- Código listo en paquete V15 revisado externamente: 283 archivos, 26 PHP, 52 JS y 42 JSON; tests de sintaxis no equivalen a pruebas de MariaDB.
- **No hacer Deploy desde Hostinger** mientras `main` solo contiene README o mientras falte integrar el código, la entrada «Decreto Mari Carmen», el GTM real, los adjuntos y la base de datos actual.
- Tras recibir el código V15 en esta rama, revisar el PR contra `main`, activar CI y montar staging con BD independiente. Mantener la rama `main` sin despliegue hasta verificar.
- Prioridades: preservar blog y autores, GTM sin duplicar, corrección Markdown no destructiva; Conflictómetro, analizador, noticias, equipo, foro, móvil, accesibilidad y admin; migraciones aditivas con rollback.
- Hostinger debe usar PHP 8.3 y MySQL/MariaDB; no Cloudflare/Workers/D1/R2/OpenAI en producción.
- Jamás comitear la base de datos completa, usuarios, contraseñas, registros de sesiones, archivos cargados, o `_private/config.php`. Solo código y migraciones públicas revisadas.
- Pedir respaldo REAL de producción actual y verificar restauración antes de cualquier cambio o despliegue.

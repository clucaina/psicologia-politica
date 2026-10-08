# GitHub → Hostinger: staging seguro, sin producción

La acción `qa.yml` valida el repositorio en cada PR y push a main/staging. La acción `staging-hostinger.yml` **solo se ejecuta manualmente** y exige `pp-staging` y un destino remoto terminado en `/staging-pp`. Por defecto hace `--dry-run`. No incluye `--delete`, ni toca `public_html` raíz, ni sube credenciales, sesiones o uploads. **No habilitar despliegue a producción con esta acción.**

Secrets a configurar exclusivamente en el entorno GitHub `pp-staging`: `HOSTINGER_SSH_HOST`, `HOSTINGER_SSH_USER`, `HOSTINGER_SSH_KEY` (clave privada exclusiva de staging), `HOSTINGER_KNOWN_HOSTS` (fingerprint previamente comprobada por el propietario), `HOSTINGER_STAGING_DIR` (ruta absoluta aislada terminada en `/staging-pp`). Nunca escribirlos en el repositorio ni en el chat.

La base de datos staging y `_private/config.php` deben configurarse manualmente **una única vez** por una persona autorizada; después, Work debe probar el flujo: backup/restore de staging, migraciones preview/apply, T19/T26/T27 preview/stage/review/rollback, Blog edición 409, 429, SSR, mapa móvil, accesibilidad y smoke tests. Ningún pipeline de producción debe activarse sin entorno protegido, rollback ensayado y aprobación explícita.

Para producción, diseñar en Work releases versionadas con checksum, backup verificable, switch atómico solo si el plan SSH de Hostinger lo soporta y rollback ensayado. Nunca sobreescribir `_private/config.php`, sesiones, uploads, ni borrar tablas en una sincronización de código. No confundir una prueba de sintaxis con una prueba de integración MySQL.

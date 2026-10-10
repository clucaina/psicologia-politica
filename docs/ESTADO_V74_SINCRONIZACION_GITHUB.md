# Integración V74 — estado real y criterios NO-GO

Fecha: 2026-10-10. **No se ha desplegado nada en Hostinger.**

## Avance remoto verificable
- Código y recursos V72: 145 rutas de texto transferidas a esta rama independiente (PHP, CSS, JS, SVG, GeoJSON, metadatos y pruebas). Tres GeoJSON grandes subidos como blobs Git con preservación de su contenido.
- Se preserva de la rama V73 el módulo de páginas; se ha reforzado con proyecciones públicas de datos, descarte de perfiles falsamente atribuidos y filtrado de miembros no públicos.
- Los recursos seleccionados de V72 han superado QA aislado. El mapa real muestra 19 NUTS2 y las correlaciones territoriales se contrastan con las observaciones aportadas.
- No se ha modificado `main`, ni producción, ni la base MySQL/MariaDB.

## Bloqueos explícitos
1. El paquete V72 contiene 1.213 **observaciones candidatas** y 84 indicadores; NO se han subido/importado como datos publicados. Sigue pendiente la revisión científica de fuentes y alertas.
2. El manifiesto `docs/RELEASE_PAYLOAD_V72.json` es histórico y requiere 8 rutas que no se han transferido íntegramente: el anterior `_private/pages.php` (sustituido por una versión de integración con privacidad reforzada) y siete imágenes binarias. Por tanto **NO es un manifiesto válido para publicar esta rama**.
3. La conciliación Hostinger restaurado ↔ GitHub sigue pendiente, incluyendo `_private/config.php` privado, imágenes originales, GTM/GA, perfiles y artículos en MariaDB.
4. El editor compilado aún requiere pruebas reales de multiusuario, recuperación de borradores, edición y autoría. Los tests con mocks no sustituyen esa integración.
5. Nunca desplegar la rama a `public_html` mediante el Git de Hostinger sin validar un nuevo manifiesto por hash, un backup actual, rollback y un staging con base de datos.
6. El repositorio está configurado como público por la propietaria; no se incluyen secretos ni datos de clientes.

## Condiciones mínimas para aprobar una futura versión
- Entrypoint y rutas completas; editor/administración operativos; autores/fotos autorizados.
- Correlaciones calculadas con 19 territorios (o cobertura advertida), fuente, unidad y periodo compatible; `null` distinto de cero.
- Auditorías NPL y revisión humana de correcciones INE, propuestas editoriales y fuentes originales.
- Frontend JS + backend PHP compilados de la misma revisión; `index.php`, `_private/assets.json` y ficheros `assets/*` coherentes.
- Pruebas de Chrome móviles/escritorio, MariaDB real de staging y controles de seguridad.
- Despliegue de pruebas no destructivo y autorización expresa.

CI en esta rama: `.github/workflows/predeploy-guard-v70.yml` realiza validación local y **NO desplegará Hostinger**.

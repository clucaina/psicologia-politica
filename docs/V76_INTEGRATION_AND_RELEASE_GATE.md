# Psicología Política — V76: integración parcial y release gate (10/10/2026)

**NO-GO PARA PUBLICACIÓN.** Hostinger conserva la web restaurada, contenido, imágenes y MariaDB. No activar auto-deploy ni publicar desde main.

## Incorporado realmente en esta rama
- Validación de URL externas antes de limpiar la entrada; rechazo de hosts privados/locales, controles y credenciales.
- Noticias científicas: publicación requiere fuente HTTPS válida, manteniendo revisión humana.
- Analizador V2: distinguir viajes contabilizados de personas únicas, IPI original frente a ajustado.
- NPL T111: 25 observaciones y 12 indicadores provisionales oficiales INE (IPI y transporte de agosto de 2026). NO importados.
- Autobús urbano: 13 CCAA publicadas; ES13, ES22, ES23, ES53, ES63, ES64 sin cifra individual por secreto estadístico, no son ceros.
- Índice editorial compacto V76: 185 fuentes y 153 huellas de títulos; publicación automática prohibida.

## QA y hashes
- qa-current-v76.yml valida URLs, noticias HTTPS, NPL, clasificador de texto y sintaxis de archivos. Sin credenciales ni despliegue.
- predeploy-guard-v70.yml (ahora auditoría diferencial V76): conserva 129 hashes originales V75 y admite exactamente 4 diferencias identificadas: VERSION.json y core.php, editorial.php, text_analysis_v2.php. NO equivale a aprobación para producción.

## Paquete maestro en la conversación
- PSICOLOGIAPOLITICA_MASTER_V76_PREPRODUCCION.zip; 20.021.394 bytes; SHA-256 3f19ffe2e7d2e0dc059990f5ece271d1bd875b83ca9e59198f10013398c558c6.
- Contiene 1.238 observaciones candidatas/96 indicadores, dossier y 133 SHA preparados para staging. Es MÁS COMPLETO que esta rama. GitHub solo tiene sincronización parcial: no afirmar paridad.

## Release gate pendiente
1. Reconciliar la copia real restaurada Hostinger con el código; no subir config.php ni uploads ni imágenes protegidas a staging público.
2. Integrar y probar frontend auténtico, Blog Editar, autoguardado, perfiles, Conflictómetro y mapa real con MariaDB de staging y varias sesiones.
3. Validar científicamente T111 y las alertas ECV/series históricas. No publicar candidatos ni noticias sin revisión.
4. Importación idempotente dry-run, backups y rollback. Pruebas móvil/desktop y seguridad.
5. Solo con aprobación expresa preparar despliegue incremental. NO-GO por defecto.
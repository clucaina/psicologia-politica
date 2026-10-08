# Estado de los datos reales y alternativas a las copias semanales

**Actualizado: 8 de octubre de 2026.** La titular confirma que el SQL instalado originalmente en Hostinger es el que ya se generó y entregó en la conversación. El panel de Hostinger solo le permite acceder a copias semanales; no insistir en pulsar una opción manual que su plan no ofrece.

## Lo que ya tenemos, sin descargas nuevas

1. SQL de arranque utilizado para la web (histórico; en el TAR del 04/10 figura un SQL de importación fechado el 03/10).
2. Código V16 completo en `preproduccion/v16-integracion-sin-work` y versión publicable sin material de desarrollo en `preproduccion/v16-runtime-limpio`.
3. Publicación «Decreto Mari Carmen» **reconstruida a nivel de registro público** desde el JSON integrado en su HTML: 32 campos, 10.425 caracteres de contenido, identificador `decreto-mari-carmen-0071e38b`, portada `971a8ade-e871-48ce-85cc-fe4df9048352.jpg`. Guardada en `respaldo/decreto-mari-carmen-2026-10-08/preservacion/2026-10-08/decreto-mari-carmen-registro-publicado.json`.
4. Otros registros públicos: **42 publicaciones con datos estructurados**, 34 noticias, 7 entradas de blog y 1 análisis, en `preservacion/2026-10-08/todas-las-publicaciones-publicas-42.json`. No contiene tokens, sesiones ni contraseñas. **No es una exportación SQL.**
5. Imagen original del artículo JPG guardada en GitHub privado, además de copias HTML y SHA-256: `respaldo/decreto-mari-carmen-2026-10-08`. La integridad de todos estos archivos fue comprobada por GitHub Actions.

## Distinción imprescindible

El SQL de arranque **no contiene** las filas insertadas/editadas después, aunque siga siendo la base inicial de la web. El artículo fue creado el 04/10/2026 10:40:22, según los campos públicos. La copia pública estructurada aporta una ruta de rescate de lo publicado, **pero no de** borradores, comentarios privados, historiales editoriales, roles, solicitudes, votos, cuentas ni cambios no publicados posteriores al SQL inicial.

## Cómo avanzar sin esperar al backup semanal

- Continuar QA en GitHub Actions con **MySQL de prueba** y datos públicos recuperados. Jamás conectar el servidor de pruebas a la base productiva.
- Seguir diseñando un despliegue en staging separado. Sin cambios en `public_html` ni operaciones `DROP/DELETE/TRUNCATE` en producción.
- Justo **antes de publicar definitivamente**, exportar la MySQL **actual** desde phpMyAdmin si es necesario; esta operación de lectura no depende de la periodicidad de Backups de Hostinger. Alternativa: formulario autenticado de `/mantenimiento`, solo si la versión desplegada lo ofrece y se dispone de rol superadmin. **No pulsar «Restaurar».**
- Es preferible guardar esta exportación confidencial cifrada fuera del repositorio; no enviarla a GitHub ni incluir contraseñas en chats.
- Verificar que la exportación incluye los cambios recientes (por ejemplo, el slug exacto) y respaldar `uploads` antes de cambiar la versión de código de producción. Comparar estructura de tablas y migraciones en un staging aislado.
- Si es inviable extraerla en el momento de publicación, **no publicar mediante sustitución masiva de la base ni un despliegue irreversible**; mantener el staging listo y esperar un respaldo adecuado.

**No pedir a la titular que vuelva a subir el mismo SQL ni que espere al sábado para continuar con la preparación técnica.**

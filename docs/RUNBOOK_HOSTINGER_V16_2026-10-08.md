# Plan de despliegue seguro a Hostinger — V16

**Estado 2026-10-08: PREPRODUCCIÓN.** El árbol de aplicación completo está en `preproduccion/v16-integracion-sin-work`; el árbol depurado, sin código de pruebas/documentación, está en `preproduccion/v16-runtime-limpio`. **El sitio en producción no ha sido modificado por esta integración.**

## 1. Copia actual de Hostinger antes de tocar Git

En la cuenta propietaria (o con acceso administrativo que muestre Backups):

- hPanel → Sitios web → psicologiapolitica.es → Panel → **Copias de seguridad**.
- Si está disponible «Crear copia de seguridad», iniciarla y esperar hasta su finalización; los planes Business o superiores pueden habilitar copias manuales.
- Verificar que dispone de **archivos** y **MySQL actual**, no solo una de las dos categorías.
- La base debe incluir el artículo reciente `decreto-mari-carmen-0071e38b`, las tablas editoriales, comentarios, revisiones, usuarios, roles, `media_assets` y las observaciones NPL.
- Los archivos deben incluir `public_html/uploads/`, la carpeta `_private` y `public_html/analytics.js`; **guardar la configuración privada fuera de Git**, nunca compartir contraseñas por chat.
- Conservar al menos una copia cifrada fuera del alojamiento y registrar hora, tamaño de los dos componentes y método de recuperación.
- Cuando exista un entorno de prueba, ensayar restauración con DB aislada y comprobar filas/imagen antes de hacer cambios en producción.
- Si no aparece el botón de backup manual, consultar la pestaña **Restaurar y descargar**, donde suelen figurar backups por fecha de archivos y bases. No pulsar «Restaurar» en el servidor vivo.

**El SQL del 04/10 no es una copia suficiente**: carece de «Decreto Mari Carmen». El JPG original está ya conservado también en la rama privada `respaldo/decreto-mari-carmen-2026-10-08` (SHA-256 `12c45091feee01a11355310cef09ae83cd6262cf4271fad718f0814acfc289d3`).

## 2. Entorno de pruebas independiente

Usar un **sitio de staging / subdominio con raíz propia y MySQL independiente**. No usar `public_html` de producción, ni una subcarpeta pública para una copia con cuentas/credenciales reales. Desactivar correos y servicios externos de staging; restringir acceso al sitio de pruebas. Copiar/anonimizar registros sensibles cuando sea necesario.

La rama destinada a ese staging es `preproduccion/v16-runtime-limpio` (207 blobs exactos del código V16); no seleccionar `main` (solo README), ni `preproduccion/v16-integracion-sin-work` (incluye tests y docs).

Para configurar Git en Hostinger: Sitios web → Panel → Avanzado → Git → Conéctate con GitHub, autorizar únicamente el repositorio privado `clucaina/psicologia-politica`, seleccionar **esa rama y la raíz del sitio staging**. Antes de pulsar Deploy, comprobar en pantalla la ruta destino, rama y cuenta.

**La primera conexión/deploy no es reversible por sí sola** y puede sobrescribir contenido del directorio elegido; mantener auto-deploy APAGADO hasta validar staging y rollback.

## 3. QA real imprescindible

- PHP 8.3 y módulos `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `zip`.
- Configuración `_private/config.php` propia de staging, fuera de Git; credenciales distintas de producción.
- Las 2 migraciones `*.schema.sql` se prueban contra la base clonada primero, inspeccionando `pp_migration_plan`. Nunca importar seeds antiguos ni ejecutar SQL `DELETE/TRUNCATE/DROP`.
- Abrir portada, blog, `/blog/decreto-mari-carmen-0071e38b`, noticias, herramienta de análisis, Conflictómetro, mapa de CCAA/provincias, registro/acceso, blog editor/autoguardado, roles de equipo, contacto y versión móvil.
- Confirmar que `/api/media/971a8ade-e871-48ce-85cc-fe4df9048352.jpg` es recuperable con los archivos de subida y la tabla media_assets **sin duplicar registros**.
- Confirmar consentimiento y actividad de Google Analytics 4 `G-D7KPSFPMM5`; el archivo fuente correcto es `/analytics.js`, sin duplicar etiquetas ni evento sintético.
- Verificar compatibilidad SQL real, integridad de artículos y comentarios, backup/restore aislado, latencia, accesibilidad y errores de consola.

## 4. Producción (pendiente de autorización expresa)

Tras QA real sin fallos y backup restaurable, aprobar la versión runtime adecuada, comparar **archivos vivos** con V16, definir política de preservación de uploads, configuración, archivos creados desde admin y dependencias. Solo entonces planificar ventana de despliegue, canarios y rollback.

**No pulsar Deploy ni fusionar a main hoy por el mero hecho de que los 207 archivos y CI pasen.**

Fuentes oficiales Hostinger:
- https://www.hostinger.com/support/2298928-how-to-create-backups-at-hostinger/
- https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/

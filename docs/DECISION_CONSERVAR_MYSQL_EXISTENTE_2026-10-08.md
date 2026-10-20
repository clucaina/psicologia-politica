# Decisión de integración de MySQL — 08/10/2026

## Aclaración confirmada por la titular

El SQL de arranque fue **el que se entregó previamente en esta conversación** y fue importado en Hostinger. Después se publicó **«Decreto Mari Carmen» desde el editor web**. No se hicieron ediciones manuales adicionales mediante phpMyAdmin.

**No volver a solicitar ni importar el SQL inicial.** Los registros creados por el editor web son cambios legítimos en la MySQL viva aunque no se haya abierto phpMyAdmin.

## Regla para el despliegue

1. **Código por separado de datos.** La rama candidata `preproduccion/v16-runtime-limpio` contiene los PHP/JS/CSS, la cartografía, assets y dos archivos de migración `*.schema.sql`, pero **no** incluye volcados SQL ni archivos privados. Desplegar el código jamás debe hacerse mediante una restauración de SQL antiguo.
2. **Si el artículo ya existe en una BD (como sucede en producción), conservar su registro exactamente como está.** No ejecutar `REPLACE`, `INSERT ... ON DUPLICATE KEY UPDATE`, `UPDATE`, `DELETE` ni sobrescribir publicaciones existentes.
3. Para un entorno de **pruebas aislado**, partir del SQL inicial y reconstruir solo el registro público que falte usando su copia estructurada de 32 campos, verificando por el `slug` y manteniendo el JPG recuperado. Esto NO se ejecuta contra producción.
4. Solo si se va a modificar código o esquema de la web viva, crear una **copia de seguridad justo antes del cambio** como medida de reversión. El botón manual del panel de Hostinger no está disponible según la titular; un export de lectura desde phpMyAdmin o la copia autenticada `/mantenimiento` (si existe y funciona en producción) es alternativa y no exige esperar al ciclo semanal. No volver a pedir el mismo SQL ya entregado.
5. La última comprobación funcional con MySQL 8.0 en GitHub restituyó 42 publicaciones públicas y las rutas HTML del sitio; las pruebas posteriores simulan 41 registros existentes + uno nuevo, con reconciliación idempotente y **cero escrituras sobre registros ya presentes**.
6. La copia pública no contiene comentarios privados, borradores ni sesiones: no asumir que equivale al estado privado de toda la BD. Conservar la BD viva, no reconstruirla.

## Activos ya protegidos

- `respaldo/decreto-mari-carmen-2026-10-08/preservacion/2026-10-08/decreto-mari-carmen-registro-publicado.json`
- `respaldo/decreto-mari-carmen-2026-10-08/preservacion/2026-10-08/todas-las-publicaciones-publicas-42.json`
- `respaldo/decreto-mari-carmen-2026-10-08/preservacion/2026-10-08/decreto-mari-carmen-portada-original.jpg`
- HTML y manifiesto de 8 páginas públicas, bajo `preservacion/2026-10-08/html-publicado`.

## Pendiente fuera de GitHub

- Contrastar los archivos de producción actuales con la versión de código a publicar y ensayar el cambio de código en staging aislado.
- Confirmar un mecanismo de rollback para ficheros y MySQL **sin modificar la base** durante el despliegue.
- Conexión Git en el hPanel propietario o publicación por un procedimiento controlado de archivos, solo después de la verificación y autorización.

**Prohibido declarar aprobada la publicación por el mero hecho de que pasen las pruebas de GitHub.**

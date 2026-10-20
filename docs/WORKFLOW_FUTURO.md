# Flujo permanente después de la migración a Git

## Qué se edita dónde

### Contenido editorial ordinario
Noticias, blog, actividades, equipo, perfiles, comentarios y contenido que ya tenga editor:
**Panel de administración de psicologiapolitica.es.**
No requiere Work ni deploy.

### Diseño, componentes, lógica, herramientas o APIs
**GPT Work -> GitHub -> Hostinger.**
Work:
1. lee/pull `main`;
2. crea cambio;
3. ejecuta pruebas;
4. commit;
5. push/merge a `main`;
6. Hostinger auto-despliega;
7. Work comprueba la URL pública.

La usuaria NO descarga archivos y NO sube nada a `public_html`.

### Cambios de base de datos
Nunca se hace un SQL manual en producción como rutina.
Work crea una migración versionada y la aplicación la aplica de forma registrada.

### Nuevas tandas de datos NPL
Se preparan fuera de producción como JSON `T20`, `T21`...
Se importan en `/admin/datos/importar`, con dry-run y rollback por batch.

### Multimedia aportada por usuarios/admin
Va a `uploads/`, persistente y fuera de Git.

## Prompt corto para futuros cambios en Work
> Trabaja sobre el repositorio GitHub de psicologiapolitica.es, parte de `main`, implementa este cambio, ejecuta las pruebas, haz commit/push y verifica la URL de producción tras el auto-deploy de Hostinger. No me entregues un ZIP ni me pidas subir archivos a `public_html`. Si requiere DB, crea/aplica una migración versionada; no uses phpMyAdmin manual.

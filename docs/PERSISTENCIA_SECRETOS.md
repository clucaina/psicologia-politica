# Persistencia y secretos

## Nunca versionar
- `_private/config.php`
- contraseñas MySQL/SMTP
- setup_key
- sesiones
- logs
- backups
- dumps SQL de producción

## Persistentes que un deploy no debe borrar
- `_private/config.php`
- `_private/installed.lock`
- `_private/sessions/`
- `uploads/`

## Sí versionar
- `config.example.php`
- código PHP
- assets de aplicación
- CSS/JS compilado de la aplicación
- SVG/imágenes editoriales que formen parte del diseño base
- documentación
- migraciones
- schemas
- seeds NPL no sensibles

## Política
Git representa código/configuración no secreta. MySQL y uploads representan estado mutable.

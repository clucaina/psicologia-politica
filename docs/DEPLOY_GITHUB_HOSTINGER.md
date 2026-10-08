# Setup único: GitHub -> Hostinger

Objetivo: hacerlo UNA vez. Después cada push a `main` actualiza producción.

1. Crear/usar repo GitHub PRIVADO `psicologiapolitica-web`.
2. Subir el contenido de `repo_seed/` como raíz del repositorio.
3. En hPanel: sitio psicologiapolitica.es -> Avanzado/Git -> conectar GitHub por OAuth.
4. Elegir repo y rama `main`.
5. Target/root: `public_html`.
6. Activar auto-deployment.
7. Antes del primer deploy: backup de public_html y MySQL.
8. Persistir fuera del control de versiones:
   - `_private/config.php`
   - `_private/installed.lock`
   - `_private/sessions/`
   - `uploads/`
9. Si hPanel rechaza el target porque no está vacío, hacer solo esta vez:
   - mover/copiar el actual public_html a backup;
   - desplegar el repo;
   - restaurar los persistentes anteriores;
   - comprobar login, assets y DB.
10. Hacer un commit de prueba mínimo (por ejemplo VERSION.json), verificar auto-deploy y revertir si fuese necesario.

No guardar secretos en GitHub. No usar GitHub Actions/SFTP salvo que la integración Git nativa de Hostinger no esté disponible en el plan.

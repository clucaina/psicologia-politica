# Database migrations

The conservative migration runner already exists in `_private/migrations.php`. Only `*.schema.sql` files are executed.

Target behavior:
1. Create `hp_schema_migrations` automatically if it does not exist.
2. Apply pending migrations in filename order.
3. Store migration id, SHA-256 checksum, applied_at, duration_ms and status.
4. Every migration must be idempotent and transactional when possible.
5. Application deploys must never require the user to open phpMyAdmin.
6. Large data batches use JSON under `database/seeds/` and are imported by a PHP migration/import service in bounded chunks.
7. A failed migration must stop the release and surface an administrator-readable error without exposing secrets.

The historical `20261004_001_T19_REFERENCE.sql` contained a `DELETE FROM npl_observations` and is deliberately excluded from GitHub and deployment. Never import it. Use reviewed, bounded batch import from JSON and do not overwrite live MySQL or editorial content.

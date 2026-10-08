# Especificación de migraciones

Crear automáticamente:

```sql
CREATE TABLE IF NOT EXISTS hp_schema_migrations (
  id VARCHAR(190) PRIMARY KEY,
  checksum CHAR(64) NOT NULL,
  status VARCHAR(20) NOT NULL,
  applied_at VARCHAR(40) NULL,
  duration_ms BIGINT NULL,
  error_message LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Reglas
- Nombre: `YYYYMMDD_NNN_descripcion`.
- Orden lexicográfico.
- SHA-256 del archivo/definición.
- Si ID aplicado y checksum cambia: bloquear y avisar; no reaplicar silenciosamente.
- Lock DB para evitar dos migraciones simultáneas.
- Transacción cuando MySQL lo permita.
- Registrar éxito/fallo.
- Las migraciones de código/esquema son pequeñas.
- Los lotes NPL grandes se procesan por chunks y quedan ligados a `batch_id`.
- No ejecutar migraciones destructivas automáticas sin backup y guard explícito.
- Panel `/admin/mantenimiento` muestra estado.

## T19
`database/seeds/T19_CCAA_BASELINE.json` contiene 152 observaciones verificadas.
El runner/importador debe incorporarlas de forma idempotente sin phpMyAdmin.

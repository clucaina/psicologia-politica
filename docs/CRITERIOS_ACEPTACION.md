# Criterios de aceptación del nuevo flujo

## Deploy
- Un cambio a `main` llega a producción sin subir ZIP a public_html.
- Existe historial de deploys/commit.
- `config.php`, login, DB y uploads sobreviven.
- Puede hacerse redeploy/rollback desde Hostinger/Git.

## Migraciones
- Una migración nueva se detecta/aplica/registre sin phpMyAdmin.
- Reejecutar deploy no duplica T19.
- Un fallo no deja el esquema en estado silenciosamente parcial.

## Herramientas
- Madrid, Andalucía y Cataluña dan valores territoriales distintos.
- Los filtros de territorio y subgrupo se pueblan desde DB.
- Timeline modifica datos reales cuando hay histórico.
- Sin dato no se representa como cero.
- Cada cifra enlaza a fuente/metodología.
- No hay botones decorativos que parezcan analíticos sin cambiar estado/datos.

## Seguridad
- repo privado sin secretos.
- páginas temporales de diagnóstico eliminadas.
- roles/admin siguen funcionando.

# Psicología Política — protocolo de recuperación e integración V70

## Estado comprobado el 10/10/2026
- Sitio público recuperado tras restaurar los archivos de Hostinger del 04/10/2026; NO se restauró MariaDB.
- La portada, el artículo Decreto Mari Carmen y las rutas principales vuelven a responder.
- El despliegue fallido usó main → public_html; main sólo contenía README.md. Auto-deploy desactivado por la propietaria.

## Limitación: NO DESPLEGAR ESTA RAMA
- Esta rama procede de V16 y NO contiene todas las modificaciones de V69.
- El paquete PSICOLOGIAPOLITICA_MASTER_V69_PREPRODUCCION.zip, disponible en la conversación ChatGPT, contiene una copia de desarrollo posterior NO-GO.
- La aplicación restaurada en Hostinger puede contener imágenes, configuración privada y contenido de MariaDB no incluidos aquí.

## Orden obligatorio para GPT Work y equipo
1. Inventariar en modo lectura el Hostinger restaurado y las versiones del repositorio, comparando hashes de los archivos.
2. Integrar V69 en una rama de staging aislada; nunca en main, nunca de forma directa sobre public_html.
3. Proteger permanentemente _private/config.php, uploads/, team-assets/, group-assets/, analytics.js, datos y secretos.
4. No restaurar MySQL antiguo. Verificar los registros actuales y la imagen del artículo Decreto Mari Carmen (respaldo en rama respaldo/decreto-mari-carmen-2026-10-08).
5. Verificar editor real con varias cuentas, autoguardado, perfiles y fotos, las rutas del Conflictómetro y las consultas de NPL.
6. Corregir SSR de las herramientas sin mezclar noticias genéricas; revisar marcado Markdown del artículo ya publicado.
7. Deduplicar y revisar fuentes del conjunto NPL V69; los candidatos y noticias no deben publicarse automáticamente.
8. Ejecutar CI, tests PHP/JS, MariaDB staging, validación móvil y pruebas de seguridad, con respaldo y rollback probado.
9. Activar el despliegue solamente desde una rama validada, por ruta incremental que preserve medios y datos.

## Controles incorporados
- scripts/predeploy_guard_v70.py: verifica entrypoint y dependencias, detecta secretos y bloquea destino público.
- scripts/staging_manifest_plan_v70.py: verifica manifiestos SHA-256 y calcula diferencias sin borrados ni cambios remotos.
- .github/workflows/predeploy-guard-v70.yml: validación CI, no publica nada.

PUBLICACIÓN: NO-GO. No activar Hostinger Git auto-deploy con main.
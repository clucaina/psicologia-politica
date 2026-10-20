# PROMPT MAESTRO PARA GPT WORK — psicologiapolitica.es

Recibes una web real ya publicada en https://psicologiapolitica.es. Tu objetivo NO es crear otra web ni devolver ZIPs para que la usuaria los suba manualmente. Tu objetivo es convertir este proyecto en un flujo de desarrollo y despliegue mantenible y, después, mejorar funcionalmente las herramientas.

## Resultado operativo obligatorio
Al terminar esta tarea debe existir una única fuente de verdad en un repositorio PRIVADO de GitHub y Hostinger debe quedar preparado para desplegar automáticamente la rama `main`. A partir de entonces:
- cambios de diseño/código/lógica: Work modifica GitHub, prueba, hace commit/push y Hostinger despliega;
- contenidos ordinarios: se editan desde el panel de administración;
- cambios de esquema/base: se entregan como migraciones versionadas que la aplicación aplica sin phpMyAdmin;
- ampliaciones masivas de NPL: se cargan mediante un importador de lotes JSON con dry-run, deduplicación, transacción, auditoría y rollback por batch.

NO vuelvas a pedir a la usuaria que descargue una web completa para subirla a `public_html` salvo recuperación de desastre.

## Restricciones no negociables
- Producción: PHP 8.3 + MariaDB/MySQL en Hostinger.
- Mantener independencia de OpenAI/ChatGPT/Cloudflare en producción.
- No introducir Worker, D1, R2, chatgpt.site ni dependencia obligatoria de API de IA.
- No convertir la web a Node/Next.
- Nunca versionar contraseñas, secretos ni `_private/config.php`.
- No borrar ni sobrescribir `uploads/`, `_private/config.php`, `_private/installed.lock` ni datos de producción durante deploy.
- Toda cifra pública debe conservar fuente, periodo, unidad y calidad/metodología.
- `0` y `sin dato` deben ser estados diferentes.
- No hardcodear datos territoriales en JavaScript.

## Archivos recibidos
- `repo_seed/`: código base autónomo de Hostinger. Éste debe convertirse en el repo.
- `repo_seed/database/seeds/T19_CCAA_BASELINE.json`: nueva tanda regional lista.
- `repo_seed/database/DATA_BATCH_SCHEMA.json`: contrato base para futuras tandas.
- `handoff_reference/`: export SQL, manifiestos y documentación de referencia. NO se sube al repositorio salvo documentación expresamente útil.

## FASE 0 — Seguridad y limpieza
1. Elimina del proyecto cualquier helper de reparación temporal si existiera (`diagnostico-pp.php`, `ARREGLAR_AHORA.php`, `IMPORTAR_DATOS.php`, `reparar-pp.php`).
2. Mantén `.gitignore`.
3. `config.php` solo existe en Hostinger; el repo contiene `config.example.php`.
4. Revisa que no haya secretos en historial/archivos antes de push.
5. En HTML/PHP usa caché corta/no-cache; en assets con hash usa caché larga. Evita que `/` quede congelado con una portada antigua.

## FASE 1 — GitHub como fuente de verdad
Si tienes conector GitHub:
1. Crea o usa repo privado `psicologiapolitica-web`.
2. Importa `repo_seed/` en la raíz.
3. Rama principal `main`.
4. Haz commit inicial `baseline: hostinger autonomous production`.
5. A partir de aquí todas tus modificaciones deben producir commits claros y quedar en GitHub.
Si no puedes crear/conectar repo por autorización, deja todo listo y pide únicamente la acción OAuth necesaria, no un flujo manual de ZIP.

## FASE 2 — Despliegue Hostinger por Git
Hostinger soporta despliegue Git de proyectos PHP/HTML mediante OAuth con GitHub. Configurar:
- repo: `psicologiapolitica-web`
- branch: `main`
- root target: `public_html`
- auto-deployment: ON

Antes del primer deploy:
- backup completo de archivos y DB;
- preservar `_private/config.php`, `_private/installed.lock` y `uploads/`;
- si hPanel exige directorio vacío, hacer el cambio una sola vez: backup/mover actual `public_html`, desplegar repo y restaurar SOLO los persistentes.
Tras esto no se vuelve a editar producción manualmente.

## FASE 3 — Migraciones automáticas
Implementa un sistema simple, auditable e idempotente:
- tabla `hp_schema_migrations`;
- migraciones ordenadas y con checksum;
- transacción y log;
- migraciones pequeñas se ejecutan automáticamente de forma segura al detectar nueva versión, con lock;
- lotes grandes se procesan en chunks;
- panel admin `/admin/mantenimiento` muestra release, commit/version, migraciones aplicadas/pending/failed y botón de reintento solo para superadmin.

Integra T19 desde `database/seeds/T19_CCAA_BASELINE.json`.
No obligues a usar phpMyAdmin.

## FASE 4 — Importador NPL desde administración
Crear `/admin/datos/importar` para que futuras tandas T20, T21... se incorporen sin Work ni phpMyAdmin:
- acepta JSON según `DATA_BATCH_SCHEMA.json`;
- dry-run: número de observaciones, indicadores nuevos, duplicados, errores, fuentes, años, territorios;
- deduplicación por clave natural;
- modo añadir / añadir+y-publicar;
- conserva flags de calidad;
- registra `npl_imports`;
- guarda batch_id en data;
- rollback de un batch;
- progreso por chunks;
- límite de tamaño y permisos superadmin;
- nunca publica datos automáticamente si el lote no lo solicita explícitamente.

## FASE 5 — Núcleo de datos común
Estabilizar endpoints locales:
- `/api/npl/indicators`
- `/api/npl/territories`
- `/api/npl/groups`
- `/api/npl/series?indicator=&geo=&from=&to=&subgroup=`
- `/api/npl/snapshot?year=&indicator=`
- `/api/npl/compare?indicators=&geos=&from=&to=`
- `/api/npl/correlate?x=&y=&year=&geo_level=&lag=`
- `/api/npl/search?q=`

Toda respuesta lleva:
`indicator_id, value, unit, period, geo_code, geography, subgroup, source_id, source_url, quality_status, methodology_flag, coverage`.

## FASE 6 — Conflictómetro 2.0
Implementa lo especificado en `docs/CONFLICTOMETRO_2_SPEC.md`.
Puntos esenciales:
- España 2D como vista principal; Mundo 3D como alternativa.
- 17 CCAA + Ceuta/Melilla con polígonos y geo_code coherente.
- Click real de Madrid -> ficha de Madrid, no datos nacionales reutilizados.
- hover, leyenda, sin-dato, series, anterior/siguiente/play, comparaciones, correlaciones.
- Pregunta a España devuelve respuesta + dato + gráfico/tabla + fuentes; si no puede responder con datos disponibles, lo reconoce.
- export CSV/PNG y URL reproducible.
- ningún botón puede limitarse a cambiar decoración sin cambiar datos/estado significativo.

## FASE 7 — Datos de España
- filtros Territorio/Subgrupo se generan desde DB;
- explorar catálogo completo con calidad VERIFIED / PROVISIONAL / ARCHIVE;
- página por indicador con KPI, serie, mapa, grupos, metodología, cobertura, fuentes y CSV;
- no limitar el explorador a los 2 indicadores publicados si existe archivo adicional: permitir verlo con etiqueta de calidad.

## FASE 8 — Analizador de texto v2
Implementa `docs/ANALIZADOR_V2_SPEC.md`.
Debe separar proposiciones y distinguir hecho/opinión/inferencia/predicción/causalidad/cita.
Debe detectar:
- afirmaciones verificables;
- falacias;
- sesgos cognitivos potencialmente activados;
- técnicas persuasivas/manipulativas sin atribuir intención;
- errores/trampas estadísticas;
- lenguaje emocional/absolutista;
- mapa argumental.
Verificación factual:
1. buscar primero en NPL local;
2. expresar respaldo como ALTO / MEDIO / BAJO / CONTRADICHO / NO VERIFICABLE, con evidencia;
3. nunca inferir verdad solo por el estilo lingüístico.
Conectar acciones:
`Comprobar en Datos` · `Ver en mapa` · `Explorar relación`.

## FASE 9 — QA
Antes de dar por terminada:
- lint PHP de todos los `.php`;
- pruebas de API y migraciones;
- pruebas de rutas públicas;
- prueba móvil;
- Madrid/Andalucía/Cataluña deben devolver valores distintos cuando los datos lo sean;
- 0 != sin dato;
- `config.php` y uploads sobreviven un deploy;
- simular un cambio pequeño, commit/push y comprobar que el despliegue Git lo publica SIN subida manual;
- comprobar rollback/redeploy en Hostinger;
- eliminar páginas de diagnóstico temporales;
- comprobar que `/` no sirve una portada cacheada antigua.

## Entrega final de Work
NO entregues otro ZIP como método operativo.
Entrega:
1. URL del repo GitHub;
2. rama desplegada;
3. commit final;
4. estado de auto-deploy Hostinger;
5. lista de migraciones aplicadas;
6. pruebas superadas/fallidas;
7. URLs públicas verificadas;
8. cualquier única acción manual que todavía sea imprescindible.

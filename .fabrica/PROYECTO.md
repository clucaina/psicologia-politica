# Fábrica independiente: Psicología Política

Esta automatización solo afecta a **clucaina/psicologia-politica**. No crea una app común ni transfiere información entre proyectos.

## Reglas
- Trabajar exclusivamente en ramas de desarrollo y mediante revisión. No fusionar, desplegar, publicar ni cambiar infraestructuras automáticamente.
- El informe de auditoría muestra archivos visibles, bloqueos y acciones propuestas; **no ejecuta las pruebas funcionales de la aplicación**.
- No incluir datos personales, clínicas, informes periciales, credenciales ni secretos.
- No habilitar llamadas a IA de pago ni usar servicios externos para datos sensibles.
- No desplegar en Hostinger ni migrar MariaDB; preservar articulos, autores, ediciones y observaciones.

## Cola de ejecución propia
1. Integrar la version canonica de aplicacion sin cambiar Hostinger.
2. Reparar home, Conflictometro, mapa de Espana y mundo, filtros, timeline y series.
3. Validar publicacion de articulos, autoria y controles de acceso.
4. Publicar fuentes de datos, incertidumbre, SEO y accesibilidad visual.

## Qué hace y qué no hace
El script y el workflow de GitHub solamente realizan una **auditoría de solo lectura**. La ejecución periódica del workflow no estará activa hasta incorporarlo deliberadamente a la rama predeterminada; por eso esta propuesta se entrega en una rama y PR de borrador. No implementa una IA que programe sola ni equivale a despliegues continuos. GitHub Actions en repositorios privados está sujeto a una cuota de ejecución.

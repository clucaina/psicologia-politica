# AGENTS.md — privacidad y seguridad antes de cualquier desarrollo

Ámbito: este repositorio y sus ramas. No modifica automáticamente otros chats, repositorios o servicios externos.

## Reglas prioritarias para todos los agentes y automatizaciones
- Este repositorio es público. Nunca introducir ni conservar datos de clientes, personas peritadas, menores, historiales clínicos, DNI, teléfonos privados, contraseñas, tokens, cookies, archivos de sesiones ni información que permita reidentificar.
- Solo pruebas con datos sintéticos. No recuperar expedientes reales de Drive, chats, correo, APJ u otros servicios como material de demostración, entrenamiento, depuración, documentación o CI.
- No enviar secretos o datos personales a incidencias, PR, artefactos, logs, servicios externos, analítica o evaluadores. No publicar valores encontrados por un escáner: únicamente categorías y recuentos agregados.
- El código que procese documentos sensibles debe ejecutarse fuera de GitHub y enteramente en local, sin transmisión ni telemetría. Generar una copia anonimizada nueva y no sobrescribir originales.
- Comprobar metadatos, documentos anidados, adjuntos, texto oculto, revisiones, gráficos y datos indirectos; jamás afirmar que un detector basado en expresiones regulares garantiza una anonimización completa.
- En caso de incertidumbre o hallazgo compatible con datos privados: no difundir, detener la publicación, preservar pruebas y solicitar una revisión humana local.
- Rotar/revocar credenciales realmente expuestas antes de depurar copias históricas. La limpieza del último commit no elimina el historial ni invalida una clave.
- Los checks son preventivos y limitados: gitignore no elimina los archivos ya versionados y las pruebas tras un push no garantizan bloqueo previo. Aplicar las protecciones de GitHub en la configuración real de la rama.
- No modificar DNS, permisos, autenticación ni producción sin identificación de la infraestructura real, copia válida y lectura posterior de la corrección.

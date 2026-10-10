# V75 · Cambios sincronizados en GitHub, NO DESPLEGAR
Fecha: 2026-10-10. Integración sobre rama `integracion/v74-sincronizacion-v72`, PR borrador #7.

## Correcciones verificables
- Blog: la consulta de equipo incluye `is_public`; sin el campo, las fotos/perfiles desaparecían aunque el autor fuese público. La asociación de identidades ambiguas sigue bloqueada.
- Servidor: respuestas HTML específicas para Inicio, Noticias, Blog, Actividades, Quiénes somos, Observatorio, Privacidad y Normas, evitando que una ruta muestre noticias ajenas. En ausencia de JavaScript, mantiene estructura y enlaces accesibles.
- Mapa 2D: el endpoint devuelve 503 si el archivo HTML esencial falta, en lugar de fingir éxito con respuesta vacía.
- Estadística: Pearson y Spearman se comprueban con pares territoriales revisados; la sensibilidad por exclusión se recalcula a partir de esos pares, con nuevos rangos para Spearman.
- Pruebas dedicadas para regresión en GitHub Actions.

## Bloqueos
La carpeta `public_html` restaurada en Hostinger es la fuente temporal de producción. Este código NO equivale a un respaldo de su base MariaDB ni incluye configuración privada, y debe pasar por staging aislado. No reactivar auto-deploy desde `main`.
El archivo `docs/RELEASE_PAYLOAD_V72.json` representa una instantánea anterior, NO es manifiesto válido para estos cambios. Hay un manifiesto local V75 verificado pendiente de reconciliación con todos los bytes remotos antes de usarse. **Release gate NO-GO**.
Las 1.213 observaciones / 84 indicadores NPL siguen siendo candidatos sin publicación. Las pruebas de navegador con mocks no sustituyen API/MariaDB real. Editorial requiere revisión humana.

## Aceptación pendiente
Conciliar Hostinger restaurado, comprobar fotografías originales, blog multiusuario, MariaDB staging y migraciones reversibles, cartografía, accesibilidad y revisión científica. Publicación solo con aprobación expresa.

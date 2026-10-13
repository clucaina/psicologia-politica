# Publicación de noticia: Votar para hacer daño

**Destino solicitado:** psicologiapolitica.es → Noticias → Investigación  
**Estado comprobado (13/10/2026):** archivo editorial guardado en GitHub. Pendiente de publicación en la web.

## Archivos
- [Artículo HTML completo, responsive, con fotos y gráficos](./index.html)
- [Metadatos estructurados y verificación de datos](./noticia.json)

## Para cargar en el editor del sitio
Título: **Votar para hacer daño**  
Categoría: **Investigación**  
Resumen: **El 37 % de una muestra estadounidense declaró más de un 50 % de probabilidad de votar a un candidato hipotético que perjudicaría al partido rival; el experimento posterior encontró un rechazo promedio a ese mensaje, moderado por el placer ante el daño ajeno.**

Portada: **Jesse Paul / Unsplash** (ver `photos[0].url` en noticia.json)  
Imagen secundaria: **Joaquin Arenas / Unsplash** (ver `photos[1].url` en noticia.json)  
Cuerpo: contenido editorial de `index.html`, sin etiquetas de documento `html`, `head` ni `body`. Antes de importar, ajustar las clases y estilos al CMS y preservar encabezados, imágenes, gráficos, enlaces y créditos.  
URL sugerida: `/noticias/votar-para-hacer-dano`

## Verificación científica
Referencia primaria: Webster, Glynn y Motta (2024), *Political Psychology*, DOI 10.1111/pops.12922. El primer estudio (2019) registra 37 % con probabilidad >50 de apoyar a un candidato perjudicial para el electorado rival y 30 % con probabilidad >60. No se trata de voto efectivo. El experimento de 2022 compara cuatro condiciones aleatorizadas: control, perjudicar a rivales, ayudar a los propios y perjudicar a rivales, y ayudar a ambos. En promedio, la promesa de perjudicar al rival reduce en 21,2 puntos la intención declarada frente al control; la relación es menos negativa y llega a ser positiva entre puntuaciones extremadamente altas de schadenfreude.

## Bloqueador de publicación automatizada
El repositorio `clucaina/psicologia-politica` solo contenía inicialmente `README.md` y no el código fuente, base de datos o configuración de despliegue del dominio. Por tanto **crear un commit no modifica psicologiapolitica.es**. Las integraciones de Hostinger AI Builder Agentic no listan este sitio y no se ha confirmado una sesión CMS autenticada para publicar. Es necesario acceso al editor real de la web o enlazar el proyecto canónico y su pipeline de despliegue. No introducir credenciales en GitHub.

## Comprobaciones postpublicación
1. Comprobar que aparece en Noticias → Investigación y que la URL devuelve HTTP 200.
2. Verificar imagen principal, segunda fotografía, créditos y visualizaciones, también en móvil.
3. Verificar que el enlace DOI funciona y que los porcentajes corresponden a la fase observacional.
4. Confirmar fecha, categoría, autoría editorial y metadatos Open Graph sin `noindex` (el HTML independiente lleva `noindex` para no competir con la URL canónica).
5. Verificar que el contenido sea visible para usuarios no autenticados y que se mantenga el diseño del sitio.

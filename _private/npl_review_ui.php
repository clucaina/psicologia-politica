<?php
declare(strict_types=1);
/** Admin-only, progressive NPL review console. Source files are intentionally
 * maintained outside the minified React bundles; all writes use the existing
 * role/CSRF/optimistic-lock/audit API. This page never publishes on load. */
function pp_npl_review_console(): never {
    pp_require_role(['superadmin','admin_editor']);
    pp_session();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    $csrf=pp_escape($_SESSION['csrf']);
    echo <<<'HTML'
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Revisión científica NPL · Psicología Política</title>
<link rel="stylesheet" href="/assets/npl-review-console.css"></head><body>
<a class="skip" href="#main">Saltar al contenido</a>
<header class="top"><div class="shell"><p class="eyebrow">PSICOLOGÍA POLÍTICA · ACCESO RESTRINGIDO</p>
<h1>Revisión científica de observaciones NPL</h1><p>El archivo no equivale a datos publicados. Toda decisión exige documentación y queda auditada.</p>
<nav aria-label="Administración"><a href="/cuenta">Mi cuenta</a><a href="/herramientas/datos-espana">Metodología pública</a></nav></div></header>
<main id="main" class="shell">
<section class="panel" aria-labelledby="selection-heading"><h2 id="selection-heading">1 · Seleccionar una observación</h2>
<p>Solo se muestran registros al equipo autorizado. Los filtros no modifican la base.</p>
<div class="grid"><label>Indicador <select id="indicator"><option value="">Cargando catálogo…</option></select></label>
<label>Estado <select id="review-filter"><option value="">Todos</option><option value="review">En revisión</option><option value="published">Publicados</option><option value="rejected">Rechazados</option></select></label></div>
<div class="toolbar"><button id="load" type="button">Consultar registros</button><button id="previous" type="button" disabled>Anterior</button><button id="next" type="button" disabled>Siguiente</button><span id="paging" aria-live="polite"></span></div>
<p id="list-status" role="status" aria-live="polite">Esperando catálogo.</p>
<div id="records" class="records" aria-label="Observaciones encontradas"></div></section>
<section id="review-section" class="panel" aria-labelledby="review-heading" hidden>
<h2 id="review-heading">2 · Documentar evidencia y decidir</h2>
<div id="selected" class="selected"></div>
<form id="review-form" autocomplete="off">
<fieldset><legend>Fuente y método</legend><div class="grid">
<label>URL de fuente primaria HTTPS <input name="source_url" type="url" maxlength="2000" placeholder="https://…"></label>
<label>Tabla, página o localizador <input name="locator" maxlength="2000"></label>
<label>Fecha real de consulta <input name="retrieved_at" type="date"></label>
<label>Tipo de medición <select name="measurement_type"><option value="">Sin documentar</option><option value="survey_screening">Cribado en encuesta</option><option value="survey">Encuesta</option><option value="administrative_record">Registro administrativo</option><option value="estimate">Estimación</option><option value="derived">Dato derivado</option></select></label>
<label>Población o universo <textarea name="population" rows="2" maxlength="2000"></textarea></label>
<label>Denominador exacto y universo de referencia <textarea name="denominator" rows="2" maxlength="1000" placeholder="Documentar desde fuente; nunca inferir"></textarea></label>
<label>Frecuencia real <select name="frequency"><option value="">No documentada</option><option value="annual">Anual</option><option value="yearly">Anual (etiqueta histórica)</option><option value="quarterly">Trimestral</option><option value="monthly">Mensual</option></select></label>
<label>Versión de definición <input name="definition_version" maxlength="200"></label>
<label>Inicio del periodo medido <input name="measurement_period_start" type="date"></label>
<label>Final del periodo medido <input name="measurement_period_end" type="date"></label>
<label>Clave de serie comparable <input name="series_key" maxlength="2000"></label>
<label>Cita de la fuente <textarea name="citation" rows="2" maxlength="2000"></textarea></label>
<label>Límites metodológicos y discontinuidades <textarea name="limits" rows="3" maxlength="2000"></textarea></label>
</div></fieldset>
<fieldset id="ecv-revision" hidden><legend>ECV 2025 · comprobación de la revisión y su alcance</legend>
<p>Una corrección general no demuestra que cada celda haya cambiado. Documenta la comparación exacta.</p><p id="ecv-scope-warning" hidden><strong>Módulo especial:</strong> comprueba primero si el aviso del INE afecta a esta tabla. No presupongas que se modificó.</p>
<label>Resultado del cotejo <select name="revision_review_result"><option value="">Sin cotejar</option><option value="unchanged_after_table_comparison">Sin cambios tras cotejo de tabla</option><option value="corrected_value_reconciled">Valor corregido y conciliado</option><option value="notice_scope_checked_not_affected">Fuera del alcance tras comprobar aviso</option></select></label>
<label>URL oficial INE <input name="revision_reference_url" type="url" maxlength="2000"></label>
<label>Justificación detallada (mínimo 60 caracteres) <textarea name="revision_review_note" rows="3" maxlength="2000"></textarea></label>
<label class="check"><input name="source_revision_checked" type="checkbox"> He comprobado el aviso oficial de 01/10/2026, su alcance y la tabla aplicable.</label></fieldset>
<fieldset id="conflict-resolution" hidden><legend>Discrepancia entre fuentes</legend>
<label>Conciliación científica (mínimo 60 caracteres) <textarea name="source_conflict_resolution" rows="3" maxlength="2000"></textarea></label>
<label>URL primaria que justifica la conciliación <input name="source_conflict_resolution_url" type="url" maxlength="2000"></label></fieldset>
<fieldset><legend>Decisión humana</legend>
<label>Motivo, evidencia y justificación (mínimo 10 caracteres) <textarea name="reason" rows="3" maxlength="1000" required minlength="10"></textarea></label>
<label class="check"><input name="source_verification_confirmed" type="checkbox"> He cotejado personalmente valor, población, territorio, periodo, unidad y método con la fuente primaria.</label>
<p>Guardar revisión <strong>no publica</strong>. Publicar requiere una acción distinta y confirmación expresa. Los campos vacíos no se completan automáticamente.</p></fieldset>
<div class="toolbar"><button id="save-review" type="submit">Guardar revisión sin publicar</button><button id="reject-review" type="button" class="muted">Rechazar observación</button><button id="publish-review" type="button" class="danger">Publicar tras verificar</button></div>
<p id="review-status" role="status" aria-live="polite"></p>
</form></section>
<footer><p>Herramienta interna · control de acceso por rol, CSRF, revisión concurrente y registro de auditoría en el servidor.</p></footer>
</main>
HTML;
    echo '<input type="hidden" id="pp-csrf" value="'.$csrf.'"><script src="/assets/npl-review-console.js?v='.substr(hash_file('sha256',__DIR__.'/../assets/npl-review-console.js'),0,12).'" defer></script></body></html>';
    exit;
}

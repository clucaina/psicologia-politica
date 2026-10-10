<?php
declare(strict_types=1);
/** Public catalog pagination consistency. Tokens detect most publication changes between pages;
 * they are not a scientific data checksum and are never an authorization credential. */
function pp_catalog_page_size(mixed $raw,int $legacy): int {
    if ($raw===null) return $legacy;
    if (!is_string($raw) && !is_int($raw)) throw new PPError(400,'Tamaño de página inválido.');
    if (!in_array((string)$raw,[(string)$legacy,'500'],true))
        throw new PPError(400,'Tamaño de página no permitido.');
    return (int)$raw;
}
function pp_catalog_token(array $stats): string {
    return hash('sha256',pp_json([
        (string)($stats['row_count']??'0'),
        (string)($stats['max_review']??''),
        (string)($stats['id_sum']??'0'),
        (string)($stats['row_checksum']??'0'),
    ]));
}
function pp_catalog_check_snapshot(mixed $requested,string $actual,int $offset): void {
    if ($offset===0 && ($requested===null || $requested==='')) return;
    if (!is_string($requested) || !preg_match('/^[a-f0-9]{64}$/D',$requested))
        throw new PPError(400,'Falta la huella de catálogo para continuar la paginación.');
    if (!hash_equals($actual,$requested))
        throw new PPError(409,'El catálogo revisado cambió durante la consulta. Recarga para evitar cobertura incompleta.');
}
/** Each response reads its change stamp and its page from the same MariaDB snapshot.
 * Cross-request changes are detected by the stamp on the next page. */
function pp_catalog_consistent(callable $reader): mixed {
    $pdo=pp_db();
    if ($pdo->inTransaction()) throw new PPError(503,'Consulta de catálogo no aislada.');
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    try { $result=$reader();$pdo->commit();return $result; }
    catch (Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

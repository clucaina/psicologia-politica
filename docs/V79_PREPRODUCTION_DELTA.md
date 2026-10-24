# V79 isolated preproduction — NO DEPLOY

Local source package has advanced from V78 to V79; this GitHub branch remains **incomplete** and must NOT be merged or deployed. No production changes.

## Verified local improvements (2026-10-10)

1. Fixed a real source-identity collision in `_private/news_ingest.php`: PHP `parse_str` changed query parameter dots to underscores and collapsed repeated keys, potentially dropping distinct scientific sources as duplicates. New conservative canonicalizer preserves original query components and repeated keys, removes only recognized tracking parameters, and sorts components. Nine regression cases pass, along with historic DOI tests.
2. Added read-only semantic NPL audit for 1,213 candidate rows: valid geographies, temporal frequencies, future periods, finite values, signed derived percentages, date precision, source chronology. **0 flags in this narrow audit**, not a verification of source values. 1 genuine zero preserved. Existing human verification gate still blocks all 1,213 candidates.
3. Created V79 SHA256 release manifest: 133 staging runtime files, secrets/uploads/proposals excluded; NO-GO. Local validation: 196 PHP + 99 JS/MJS + 407 JSON syntax checks with 0 errors; 30 selected tests pass (one historic V78 hash test explicitly skipped in V79 and replaced by V79 hash test).

## Integration blocker

Complete V79 source lives in a separate container and cannot be accessed directly by the GitHub connector. Do not treat this documentation as a source sync. Transfer the full package to a separate branch, reconcile with Hostinger, and run authenticated browser + MariaDB staging tests. Preserve `main`, Hostinger data, uploads, `analytics.js`, and secrets. No automatic deployment.

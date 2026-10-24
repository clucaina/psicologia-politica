# V78 · GitHub branch snapshot · 10 October 2026

**PREPRODUCTION ONLY / NO-GO**. Source of truth remains the local master V78 archive. This branch was created from integracion/v76-seguridad-npl; do not merge into main or enable Hostinger automatic deployment.

V78 payload includes 133 runtime paths, but their correspondence with the active production public_html and MariaDB has not been verified. For final integration check SHA-256 files in docs/RELEASE_PAYLOAD_V78.json in the V78 master package, perform isolated MariaDB E2E (author editing, simultaneous documents, NPL, map), and ensure restore/rollback.

Protect analytics.js, uploads, production-owned images, configs and articles including Decreto Mari Carmen.

This branch intentionally omits any claim that staging is live or that QA against production has passed.

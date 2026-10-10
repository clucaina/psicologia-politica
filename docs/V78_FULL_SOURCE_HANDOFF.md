# V78 complete source transfer — NO-GO

The local V78 master was verified to contain 1,358 tracked files. A self-contained Git bundle of the complete source snapshot is available from the ChatGPT handoff as PSICOLOGIAPOLITICA_V78_GIT_COMPLETO.bundle, accompanied by a PowerShell transfer script.

**This branch is NOT the complete V78 source.** Do not merge PR #9 or deploy it. The full source must first be pushed to a **new** branch integracion/v78-paquete-completo, never to main, then inspected via a separate draft PR. This is necessary because GitHub connector cannot read local container files.

Do not enable Hostinger auto-deploy; do not overwrite analytics.js, uploads, private production configuration or MariaDB. Only proceed after integrated QA and a backup/rollback rehearsal.

# NPL staging

This directory is an isolated workbench. No production deployment. Candidate observations require provenance checks and deduplication against the live database before import.

Use `engine.mjs` to distinguish zero from missing data, reject conflicting duplicates, and avoid interpreting correlation as causation.

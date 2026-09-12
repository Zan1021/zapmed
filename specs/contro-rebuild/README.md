# Contro Rebuild — planning deliverables

Planning docs for building Craig his **owned** ZapMed system and importing his live **Contro** data
(Contro = source of truth). Produced 2026-09-12 from the reverse-engineering of Mark/TMC-77's
`Zapmed_CRM` TS backend + a direct read of our Laravel app.

| Doc | What it is | Read it when |
|---|---|---|
| [01-system-design-dossier.md](01-system-design-dossier.md) | Consolidated reference for Mark's TS CRM backend (21 modules, order state machine, clinical model, RLS, events, integrations). | You need to understand *their* system. |
| [02-parity-gap-analysis.md](02-parity-gap-analysis.md) | Their model vs our Laravel app → the must-build-before-import backlog (ranked). | You need to know what we're missing. |
| [03-contro-import-blueprint.md](03-contro-import-blueprint.md) | How we import Contro's 7 entity sets: field mapping, staging-first ELT, idempotent reconcile, no side-effects, cutover. | You're building the import. |
| [04-strategic-options-for-craig.md](04-strategic-options-for-craig.md) | Options A/B/C (Laravel-to-parity vs adopt TS backend vs hybrid), recommendation, and the blocking questions for Craig. | You're deciding direction. |
| [05-cutover-runbook.md](05-cutover-runbook.md) | Operational runbook for running the import: prerequisites, pull → reconcile → verify, cutover phases, rollback, safety properties. Reflects the code as built (Tasks 1–10). | You're running the migration. |

**Status:** planning complete. **Ownership confirmed — Craig owns everything (our Laravel app + Mark's
`Zapmed_CRM`); no licensing constraint.** Contro is the source of truth. **DB dump expected in a few days**
(the only source of doctor/clinical notes). One blocker left before build: confirm the payment gateway
(PayFast vs peach).
**Recommended first build step (safe under any option):** `upstream_sync` staging + a read-only Contro
backfill — see doc 03 §3/§6.

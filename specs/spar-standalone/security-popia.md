# SPAR Security & POPIA — hardening record (Phase 10)

Patient chronic-medication data is PHI under POPIA. This records what is implemented in code, what
is configured, and what remains a Captain-Zan real-world decision before real patient data goes to
staging/production.

## Implemented (code)

**Access & auth (10.1)**
- Staff login rate-limited (`throttle:20,1`) to blunt brute-force / credential stuffing.
- Scoped authorisation enforced at query + policy level for all 4 tiers (Phase 7) — not UI-only.
- Session timeout via `spar.timeout` middleware; passwords hashed (bcrypt), `password:hashed` cast.
- ⏳ **MFA — NOT yet implemented. Captain Zan decision needed:** TOTP (authenticator app), SMS-OTP
  (reuse BulkSMS), or email. Recommended: TOTP for admins. Flagged as a go-live blocker for privileged tiers.

**Data protection (10.2)**
- Field-level encryption (`EncryptsSensitiveFields`) on `profile_code`, `cellphone`, `email`.
- Cleaned up dead `medical_aid_number` encrypted-field entry (no such column). If medical aid numbers
  are ever stored, add the column AND the encrypted-fields entry together.
- No PHI in stats views (aggregates only); audit logs reference patients by id, not decrypted PHI.
- TLS/HSTS enforced by `SparSecurityHeaders` when the request is HTTPS.

**Attack surface (10.3)**
- `SparSecurityHeaders` middleware on every web response: CSP, X-Frame-Options SAMEORIGIN,
  X-Content-Type-Options nosniff, Referrer-Policy, Permissions-Policy, HSTS (https only).
- Public patient tracker + OTP routes rate-limited (`throttle:30,1`).
- Signed + expiring tracker links (no login); CSRF on all forms (Laravel default — the earlier "419"
  was CSRF working as intended).
- CSV import already rejects injection patterns + size-caps + deletes the PHI file after processing.

**Audit & breach (10.4)**
- `spar_audit` channel records patient access, order changes, imports, consent changes, group/pharmacy/
  staff management, and PHI erasure — the POPIA "who did what, when, from where" trail.
- ⏳ **Tamper-evident storage + breach-detection alerting + incident/breach-notification process =
  Captain Zan owns.** POPIA requires notifying the Information Regulator + affected data subjects on a
  breach. Document the process + owner before go-live.

**Data lifecycle (10.5)**
- `spar:data-retention` command: `--erase=<id>` (right-to-erasure, anonymises one patient's PHI,
  keeps the audit trail) and a retention sweep that anonymises opted-out patients older than
  `config('spar.retention.opted_out_days', 365)`. `--dry-run` supported. Schedule the sweep in cron.

## Verification (10.6)
- Automated security tests: scope-isolation (Phase 7), stats-scoping (Phase 8) — a group/pharmacy actor
  provably cannot read another's data (fail-closed).
- ⏳ **`composer audit` + dependency pinning review** — run in CI before deploy.
- ⏳ **Manual penetration test / security review BEFORE real patient data on staging** — Captain Zan to
  choose a vendor. This is the final go-live gate.

## Captain-Zan decisions still open (real-world, not code)
1. **MFA method** for admin tiers (TOTP recommended).
2. **Breach-notification process owner + runbook** (POPIA accountability).
3. **Pen-test vendor** + sign-off before production data.
4. **Compliance officer sign-off** on consent wording/version + adult-dependant visibility (carried over).

## Pre-staging env checklist (revert dev-preview settings)
The local preview temporarily set `APP_URL=http://127.0.0.1:8090` and `SPAR_LINK_REQUIRE_OTP=false`.
For staging/production:
- `APP_URL=https://<domain>`, `APP_DEBUG=false`, `APP_ENV=staging|production`.
- `SPAR_LINK_REQUIRE_OTP=true` (patient link OTP re-verify ON).
- `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN=.<domain>`.
- Real mail (Brevo) + real SMS/WhatsApp providers wired (currently log-only stubs).

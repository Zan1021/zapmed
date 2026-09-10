# SPAR in ZapMed — Integration Notes

How the shared `zapmed/spar-core` package is wired into ZapMed (integrated host mode). Companion to
`spec.md` / `design.md` / `tasks.md`. The standalone host is documented in
`../../apps/spar-standalone/README.md`.

## Composer wiring

ZapMed's root `composer.json` requires the two packages via path repositories:

```json
"repositories": [
  { "type": "path", "url": "packages/platform-support", "options": { "symlink": true } },
  { "type": "path", "url": "packages/spar-core",        "options": { "symlink": true } }
],
"require": {
  "zapmed/platform-support": "@dev",
  "zapmed/spar-core": "@dev"
}
```

Path packages resolve as `dev-master`; the `@dev` constraint is required (a `*` constraint fails
`minimum-stability: stable`). `composer update zapmed/spar-core` may exit non-zero purely on the
vulnerability-advisory footer — check the actual output, not just the exit code.

## Host bindings — `App\Providers\SparServiceProvider`

Integrated mode (`config('spar.host_mode') === 'integrated'`) binds:

| Contract | ZapMed binding |
|---|---|
| `SparIdentityProvider` | `App\Services\Spar\Identity\UserSparIdentityProvider` (own fields → `User` fallback) |
| `TelehealthBridge` | `App\Services\Spar\Telehealth\ZapmedTelehealthBridge` (online consult + handoff + return) |
| `AuditLogger` | `App\Services\Spar\Audit\ChannelAuditLogger` (`spar_audit` channel) |
| `OtpSender` | `App\Services\Spar\ZapmedOtpSender` (wraps ZapMed `SmsService`) |
| `MessagingChannel` sms | `App\Services\Spar\Channels\SmsChannel` (via `spar.channel_factories`) |

`config/spar.php` sets `user_model = \App\Models\User::class`, so `SparPatient::linkedUser()` resolves
the ZapMed user without the package hard-referencing it (spec AC-3).

## Models — thin subclass shims

`app/Models/Spar*.php` are **thin subclasses** of the package base models. They re-add the
telehealth-only relations (`user()`, `staff()`, `zapmedPrescription()`, `importedBy()`) that the
package deliberately omits. ~40+ `App\Models\Spar*` references across ZapMed keep working unchanged.

> ⚠️ **Type-hint gotcha:** a subclass instance is **not** the parent type. Anything type-hinting a
> SPAR model (contracts, services, Livewire, and especially test doubles like a `MessagingChannel`)
> must hint the **package** class `Zapmed\SparCore\Models\Spar*`, not the `App\Models\Spar*` shim.

## Middleware & routes

- Middleware aliases (`spar.scope`, `spar.timeout`, patient-session guard) are registered in
  `bootstrap/app.php`, pointing at the package middleware.
- SPAR routes load from `packages/spar-core/routes/spar.php`; route names are unchanged. The host
  supplies middleware groups via `config('spar.route_middleware.*')`.

## Migrations

The 10 `spar_*` migrations live in the package with idempotent guards
(`Schema::hasTable` / `hasColumn`). They keep their original filenames, so ZapMed's `migrations` table
still marks them as run — no re-run, no duplicate-name conflict after the host originals were deleted.
Host FKs to `users` / `prescriptions` are dropped (plain nullable columns) for portability.

## Verification (as of 2026-09-04)

- Full ZapMed suite: **101 pass / 1 pre-existing unrelated fail** (`AuthenticationTest::navigation_menu_can_be_rendered`, dashboard 302 — not SPAR-related).
- SPAR subset (`php artisan test --filter=Spar`): **40 pass**.
- Standalone (`apps/spar-standalone`): **6 pass** + `spar:verify-schema` AC-1/AC-4.
- Acceptance matrix AC-1…AC-10: **10/10 PASS** — see `tasks.md` §5.1.

## Local dev

Local DB is SQLite; staging/prod is PostgreSQL 16. Encrypted columns must be `TEXT` (ciphertext is
longer than plaintext). `EncryptsSensitiveFields` fields cannot be queried with `where()` — filter in
PHP by the decrypted value.

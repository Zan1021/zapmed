# zapmed/spar-core

The **SPAR chronic-medication domain**, packaged once and consumed by two hosts:

- **ZapMed** (integrated mode) — binds the telehealth-aware implementations.
- **SPAR Standalone** (`apps/spar-standalone`) — binds null/standalone implementations, no telehealth.

This keeps a single implementation of SPAR logic (spec NFR-2: no drift). The package carries the
domain (models, services, Livewire, middleware, migrations, routes, views) plus **portable** channels
(in-app, email). Every host injects its own concrete identity provider, telehealth bridge, audit
logger, SMS/WhatsApp channel, and OTP sender.

## Contracts (the host seams)

`Zapmed\SparCore\Contracts\*`:

- **`SparIdentityProvider`** — resolves the current actor (staff pharmacy scope, patient identity).
  No direct `User` reads anywhere in the package (spec AC-3).
- **`TelehealthBridge`** — `offersOnlineConsult()`, `renewalOptions()`, `handoffContext()`,
  `returnPrescription()`. `NullTelehealthBridge` (shipped here) is the standalone default.
- **`MessagingChannel`** — one delivery channel (`key`, `canReach`, `send`). Dispatched by
  `MessagingDispatcher` in `config('spar.channels')` priority order. Host channels are registered via
  `config('spar.channel_factories')`.
- **`AuditLogger`** — POPIA audit seam; hosts write to the `spar_audit` log channel.
- **`OtpSender`** — patient link re-verification OTP, kept off the consent-gated messaging dispatcher.

## What ships in the package

```
src/
  Contracts/    SparIdentity, SparIdentityProvider, MessagingChannel, TelehealthBridge, AuditLogger, OtpSender
  Concerns/     LogsSparActivity            (spar_audit activity trait)
  Models/       SparPatient, SparPharmacy, SparPrescriptionJourney, SparDispenseRecord,
                SparOrder, SparConsent, SparImportBatch, SparImportLog
  Services/     MessagingDispatcher, SparPatientSession, SparImportService, SparReminderService,
                SparOrderService, Channels/{InApp,Email}, Telehealth/NullTelehealthBridge
  Livewire/     Spar\* + Admin\Spar*        (pharmacy dashboard, portal, admin screens)
  Http/Middleware/  EnsureSparPharmacyScope, SparSessionTimeout, EnsureSparPatientSession
  SparCoreServiceProvider.php
config/spar.php · routes/spar.php · resources/views/livewire (spar:: namespace) · database/migrations (idempotent)
```

Sensitive fields (`profile_code`, `cellphone`, `email`) are encrypted at rest via
`Zapmed\PlatformSupport\Concerns\EncryptsSensitiveFields`.

## Installation (path repo)

Both hosts require the package as a path repository:

```json
"repositories": [
  { "type": "path", "url": "../../packages/spar-core", "options": { "symlink": true } }
],
"require": { "zapmed/spar-core": "@dev" }
```

`SparCoreServiceProvider` is auto-discovered and defensively registers config/migrations/views/routes/
middleware only when present.

## Host binding responsibilities

The package binds **nothing host-specific**. Each host's service provider must bind:
`SparIdentityProvider`, `TelehealthBridge`, `AuditLogger`, `OtpSender`, and at least one SMS
`MessagingChannel` factory. See `App\Providers\SparServiceProvider` (ZapMed) and
`App\Providers\SparStandaloneServiceProvider` (standalone) for the two reference bindings.

## Notes / known cleanup

- `SparPatient::$encryptedFields` currently lists `medical_aid_number`, which has no column on
  `spar_patients` (the table has `medical_aid_name` / `medical_aid_option`). Harmless dead entry —
  flagged for removal.

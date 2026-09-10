<?php

namespace App\Traits;

use Zapmed\PlatformSupport\Concerns\EncryptsSensitiveFields as PackageEncryptsSensitiveFields;

/**
 * Backwards-compatible shim.
 *
 * The real implementation now lives in the shared `zapmed/platform-support`
 * package (SPAR standalone extraction, Phase 3.1). Existing ZapMed models keep
 * using `App\Traits\EncryptsSensitiveFields` unchanged; the standalone SPAR app
 * uses the package trait directly.
 */
trait EncryptsSensitiveFields
{
    use PackageEncryptsSensitiveFields;
}

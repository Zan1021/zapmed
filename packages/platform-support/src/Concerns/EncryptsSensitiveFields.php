<?php

namespace Zapmed\PlatformSupport\Concerns;

use Illuminate\Support\Facades\Crypt;

/**
 * Automatically encrypt/decrypt sensitive fields on an Eloquent model.
 *
 * Shared across ZapMed products (telehealth + SPAR standalone) so field-level
 * POPIA encryption has ONE implementation (spec FR-5.3, NFR-2).
 *
 * Usage: add the trait and define $encryptedFields.
 *   protected array $encryptedFields = ['id_number', 'cellphone'];
 *
 * Encrypted columns must be TEXT (ciphertext is longer than plaintext).
 */
trait EncryptsSensitiveFields
{
    public static function bootEncryptsSensitiveFields(): void
    {
        static::saving(function ($model) {
            foreach ($model->getEncryptedFields() as $field) {
                if (!empty($model->$field) && !$model->isEncrypted($model->$field)) {
                    $model->$field = Crypt::encryptString($model->$field);
                }
            }
        });
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (in_array($key, $this->getEncryptedFields(), true) && !empty($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                // Already decrypted or not encrypted — return as-is.
                return $value;
            }
        }

        return $value;
    }

    public function getEncryptedFields(): array
    {
        return $this->encryptedFields ?? [];
    }

    private function isEncrypted(string $value): bool
    {
        // Laravel encrypted strings are base64 JSON, starting with 'eyJ'.
        return str_starts_with($value, 'eyJ');
    }
}

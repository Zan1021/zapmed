<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

/**
 * Automatically encrypt/decrypt sensitive fields on a model.
 *
 * Usage: Add to model, define $encryptedFields array.
 *
 * class Consultation extends Model {
 *     use EncryptsSensitiveFields;
 *     protected array $encryptedFields = ['presenting_complaint', 'diagnosis', 'doctor_notes'];
 * }
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

        if (in_array($key, $this->getEncryptedFields()) && !empty($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                // Already decrypted or not encrypted — return as-is
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
        // Laravel encrypted strings start with 'eyJ' (base64 JSON)
        return str_starts_with($value, 'eyJ');
    }
}

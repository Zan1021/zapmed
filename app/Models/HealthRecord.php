<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A document on a patient's Health Stats tab: a record they uploaded, or a result a treating doctor uploaded for them. */
class HealthRecord extends Model
{
    public const RECORD = 'record';
    public const RESULT = 'result';

    protected $fillable = ['patient_id', 'uploader_id', 'uploader_role', 'category', 'title', 'path', 'original_name', 'size_bytes'];

    protected function casts(): array
    {
        return ['title' => 'encrypted'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}

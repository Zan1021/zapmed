<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** kind = custom (patient-added) or hidden_default (a default symptom the patient hid). */
class CustomSymptom extends Model
{
    public const CUSTOM = 'custom';
    public const HIDDEN_DEFAULT = 'hidden_default';

    protected $fillable = ['user_id', 'name', 'kind'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
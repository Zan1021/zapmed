<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientAllergy extends Model
{
    protected $fillable = [
        'patient_profile_id',
        'allergen',
        'severity',
        'reaction',
    ];

    public function patientProfile(): BelongsTo
    {
        return $this->belongsTo(PatientProfile::class);
    }
}

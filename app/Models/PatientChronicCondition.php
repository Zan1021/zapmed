<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientChronicCondition extends Model
{
    protected $fillable = [
        'patient_profile_id',
        'condition_name',
        'diagnosed_date',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'diagnosed_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function patientProfile(): BelongsTo
    {
        return $this->belongsTo(PatientProfile::class);
    }
}

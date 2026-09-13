<?php

namespace App\Models;

use App\Enums\RetentionAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per data-class retention rule (retain_days + action + legal basis). Seeded POPIA/HPCSA-aligned.
 */
class RetentionPolicy extends Model
{
    protected $table = 'compliance_retention_policies';

    protected $fillable = [
        'data_class', 'description', 'retain_days', 'action', 'legal_basis', 'is_active',
    ];

    protected $attributes = [
        'action' => 'erase',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'retain_days' => 'integer',
            'action' => RetentionAction::class,
            'is_active' => 'boolean',
        ];
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(RetentionScheduleItem::class, 'policy_id');
    }
}

<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use Illuminate\Database\Eloquent\Model;

/**
 * Alert definition — a configurable rule template (parity with alerts_definition). Detectors reference
 * these by their stable `code`; threshold_config is editable at runtime.
 */
class AlertDefinition extends Model
{
    protected $table = 'alerts_definitions';

    protected $fillable = [
        'code', 'name', 'description', 'default_severity', 'source_module',
        'threshold_config', 'is_active', 'auto_resolve',
    ];

    protected $attributes = [
        'default_severity' => 'warning',
        'is_active' => true,
        'auto_resolve' => true,
    ];

    protected function casts(): array
    {
        return [
            'default_severity' => AlertSeverity::class,
            'threshold_config' => 'array',
            'is_active' => 'boolean',
            'auto_resolve' => 'boolean',
        ];
    }

    /** Read a threshold value from the config, with a default. */
    public function threshold(string $key, mixed $default = null): mixed
    {
        return data_get($this->threshold_config, $key, $default);
    }
}

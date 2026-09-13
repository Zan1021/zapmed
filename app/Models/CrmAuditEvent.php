<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * An append-only audit event for the CRM domains. Insert-only: updates and deletes are refused at the
 * model layer (see booted()), and each row carries a hash-chain link (prev_hash → hash) so tampering
 * with history is detectable. Written exclusively via the AuditTrail service.
 */
class CrmAuditEvent extends Model
{
    protected $table = 'crm_audit_events';

    public $timestamps = false;

    protected $fillable = [
        'domain', 'action', 'subject_type', 'subject_id', 'actor_id', 'actor_label',
        'before', 'after', 'ip_address', 'user_agent', 'prev_hash', 'hash', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Append-only: once written, a row can never be mutated or removed.
        static::updating(fn () => throw new RuntimeException('crm_audit_events is append-only; updates are not permitted.'));
        static::deleting(fn () => throw new RuntimeException('crm_audit_events is append-only; deletes are not permitted.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @param  Builder<self>  $query */
    public function scopeDomain(Builder $query, string $domain): Builder
    {
        return $query->where('domain', $domain);
    }
}

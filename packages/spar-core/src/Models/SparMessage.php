<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Zapmed\PlatformSupport\Concerns\EncryptsSensitiveFields;

/**
 * Health Coach v1 (spec spar-health-coach FR-2). A single message in a
 * conversation. `body` is ENCRYPTED at rest — it may contain health context.
 * Because encrypted columns cannot be queried with where(), NEVER filter on
 * body; always scope by spar_conversation_id.
 *
 * Staff authorship is a plain id + snapshot name/role (package purity — no host
 * User class, NFR-1 / AC-10).
 */
class SparMessage extends Model
{
    use EncryptsSensitiveFields;

    protected $table = 'spar_messages';

    protected array $encryptedFields = [
        'body',
    ];

    protected $fillable = [
        'spar_conversation_id',
        'direction',
        'kind',
        'body',
        'author_id',
        'author_name',
        'author_role',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SparConversation::class, 'spar_conversation_id');
    }

    public function productSuggestion(): HasOne
    {
        return $this->hasOne(SparProductSuggestion::class, 'spar_message_id');
    }

    public function scopeChronological($query)
    {
        return $query->orderBy('id');
    }

    public function isFromPatient(): bool
    {
        return $this->direction === 'from_patient';
    }

    public function isFromStaff(): bool
    {
        return $this->direction === 'from_staff';
    }

    public function isSystem(): bool
    {
        return $this->direction === 'system'
            || in_array($this->kind, ['system', 'order_event'], true);
    }

    public function isProductSuggestion(): bool
    {
        return $this->kind === 'product_suggestion';
    }

    /**
     * An order-lifecycle system line (e.g. a coach suggestion attached to the
     * patient's order). Distinct `kind` so the "My Orders" filter + count can
     * scope by column without touching the encrypted body.
     */
    public function isOrderEvent(): bool
    {
        return $this->kind === 'order_event';
    }
}

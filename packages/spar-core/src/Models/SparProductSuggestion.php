<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Health Coach v1 (spec spar-health-coach §2.3). A product the coach suggests
 * in-thread. On accept, links to the order + line item it created.
 */
class SparProductSuggestion extends Model
{
    protected $table = 'spar_product_suggestions';

    protected $fillable = [
        'spar_message_id',
        'spar_conversation_id',
        'product_name',
        'price_cents',
        'note',
        'status',
        'accepted_at',
        'declined_at',
        'spar_order_id',
        'spar_order_item_id',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SparMessage::class, 'spar_message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SparConversation::class, 'spar_conversation_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SparOrder::class, 'spar_order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SparOrderItem::class, 'spar_order_item_id');
    }

    public function isOffered(): bool
    {
        return $this->status === 'offered';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function markAccepted(SparOrder $order, SparOrderItem $item): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'spar_order_id' => $order->id,
            'spar_order_item_id' => $item->id,
        ]);
    }

    public function markDeclined(): void
    {
        $this->update([
            'status' => 'declined',
            'declined_at' => now(),
        ]);
    }
}

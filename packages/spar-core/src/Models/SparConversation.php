<?php

namespace Zapmed\SparCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Health Coach v1 (spec spar-health-coach FR-1). A conversation between a
 * profile's PRIMARY member and one pharmacy. Coach = pharmacy staff.
 *
 * Scope-gated to the current actor exactly like SparPatient (pharmacy/group/
 * super, national-aware), so a staffer can only see conversations for patients
 * they may see.
 */
class SparConversation extends Model
{
    protected $table = 'spar_conversations';

    protected $fillable = [
        'spar_patient_id',
        'spar_pharmacy_id',
        'status',
        'last_message_at',
        'patient_unread_count',
        'staff_unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'patient_unread_count' => 'integer',
            'staff_unread_count' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(SparPatient::class, 'spar_patient_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(SparPharmacy::class, 'spar_pharmacy_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SparMessage::class, 'spar_conversation_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeForPharmacy($query, int $pharmacyId)
    {
        return $query->where('spar_pharmacy_id', $pharmacyId);
    }

    public function touchLastMessage(): void
    {
        $this->forceFill(['last_message_at' => now()])->save();
    }

    /**
     * Bump the unread badge for the given SIDE ('patient' | 'staff').
     */
    public function bumpUnread(string $side): void
    {
        $column = $side === 'patient' ? 'patient_unread_count' : 'staff_unread_count';
        $this->increment($column);
    }

    public function clearUnread(string $side): void
    {
        $column = $side === 'patient' ? 'patient_unread_count' : 'staff_unread_count';
        if ($this->{$column} !== 0) {
            $this->forceFill([$column => 0])->save();
        }
    }

    /**
     * Restrict to conversations the CURRENT actor may see — mirrors
     * SparPatient::scopeVisibleToCurrentActor (national-aware). Host-agnostic.
     */
    public function scopeVisibleToCurrentActor($query)
    {
        $identity = app(\Zapmed\SparCore\Contracts\SparIdentityProvider::class);

        if ($identity->isSuperAdmin()) {
            return $query;
        }

        $pharmacyId = $identity->currentPharmacyId();
        if ($pharmacyId !== null) {
            return $query->where('spar_pharmacy_id', $pharmacyId);
        }

        $groupId = $identity->currentGroupId();
        if ($groupId !== null) {
            $pharmacyIds = SparPharmacy::where('group_id', $groupId)->pluck('id')->all();

            return $query->whereIn('spar_pharmacy_id', $pharmacyIds);
        }

        return $query->whereRaw('1 = 0');
    }
}

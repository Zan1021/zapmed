<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What Operations keep about a staff member: registration, address, booking links, signature. */
class StaffProfile extends Model
{
    protected $fillable = [
        'user_id', 'registration_number', 'board', 'practice_address', 'registration_status', 'country',
        'booking_url', 'authorisation_booking_url', 'signature_path', 'updated_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

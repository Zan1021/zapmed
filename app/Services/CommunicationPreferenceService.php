<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\User;

/**
 * POPIA direct-marketing rule: a patient may opt OUT of marketing while still
 * receiving transactional / clinical-safety messages (e.g. "your prescription
 * is ready", OTP, appointment reminders). This service is the single decision
 * point for "may we send this to this person?", so no channel can accidentally
 * suppress a safety message or sneak marketing past an opt-out.
 */
class CommunicationPreferenceService
{
    public const MARKETING = 'marketing';
    public const TRANSACTIONAL = 'transactional';

    /**
     * May we send a message of the given category to this user?
     *
     * - Transactional / clinical messages are ALWAYS allowed (never blocked by
     *   a marketing opt-out; suppressing these could be a safety issue).
     * - Marketing is allowed only if the user has NOT opted out.
     */
    public function canReceive(User $user, string $category): bool
    {
        if ($category === self::TRANSACTIONAL) {
            return true;
        }

        if ($category === self::MARKETING) {
            return !$this->hasOptedOutOfMarketing($user);
        }

        // Unknown category: fail safe — treat as marketing (most restrictive).
        return !$this->hasOptedOutOfMarketing($user);
    }

    /**
     * True if the user has an active marketing opt-out.
     * Latest marketing consent record with granted=false and not revoked.
     */
    public function hasOptedOutOfMarketing(User $user): bool
    {
        $latest = ConsentRecord::where('user_id', $user->id)
            ->where('consent_type', self::MARKETING)
            ->latest('granted_at')
            ->latest('id')
            ->first();

        // No record => assume not opted in to marketing yet, but we do NOT
        // block here; explicit opt-out is what blocks. Default allow only when
        // there is no explicit opt-out.
        return $latest !== null && $latest->granted === false;
    }

    /**
     * Record a marketing opt-out for the user.
     */
    public function optOutOfMarketing(User $user): void
    {
        ConsentRecord::create([
            'user_id' => $user->id,
            'consent_type' => self::MARKETING,
            'version' => 1,
            'granted' => false,
            'ip_address' => request()->ip(),
            'granted_at' => now(),
            'revoked_at' => now(),
        ]);
    }

    /**
     * Record a marketing opt-in for the user.
     */
    public function optInToMarketing(User $user): void
    {
        ConsentRecord::create([
            'user_id' => $user->id,
            'consent_type' => self::MARKETING,
            'version' => 1,
            'granted' => true,
            'ip_address' => request()->ip(),
            'granted_at' => now(),
        ]);
    }
}

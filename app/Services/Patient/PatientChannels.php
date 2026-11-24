<?php

namespace App\Services\Patient;

use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Validation\ValidationException;

/**
 * How a patient wants to hear from us (Craig's guide 3.3): WhatsApp or email, with consent recorded.
 *
 *  - Email is the account channel and always allowed for service messages. WhatsApp needs a recorded "yes" and a phone
 *    number. The consent is a ConsentRecord (type "whatsapp_service"): the latest record decides, and withdrawing it is a
 *    new record, never an edit.
 *  - The chosen channel is tried first. If it cannot be used (no consent, no number, not set up) or the delivery fails, the
 *    other channel is tried (see PatientMessenger).
 */
class PatientChannels
{
    public const WHATSAPP_CONSENT = 'whatsapp_service';
    public const CHANNELS = ['whatsapp', 'email'];

    public function __construct(private readonly NotificationDispatcher $dispatcher)
    {
    }

    /** The channel the patient chose. Falls back to email when nothing valid is stored. */
    public function preferred(User $user): string
    {
        return in_array($user->preferred_contact, self::CHANNELS, true) ? $user->preferred_contact : 'email';
    }

    public function whatsappConsent(User $user): bool
    {
        $latest = ConsentRecord::where('user_id', $user->id)->where('consent_type', self::WHATSAPP_CONSENT)
            ->latest('granted_at')->latest('id')->first();

        return $latest !== null && $latest->granted && $latest->revoked_at === null;
    }

    /**
     * Save the choice. WhatsApp needs a phone number and a consent tick. Every change of consent writes a new record.
     */
    public function save(User $user, string $preferred, bool $whatsappConsent): void
    {
        if (! in_array($preferred, self::CHANNELS, true)) {
            throw ValidationException::withMessages(['preferred' => 'Choose WhatsApp or email.']);
        }
        if ($preferred === 'whatsapp') {
            if (! $whatsappConsent) {
                throw ValidationException::withMessages(['consent' => 'Tick the box to say we may message you on WhatsApp.']);
            }
            if (trim((string) $user->phone) === '') {
                throw ValidationException::withMessages(['preferred' => 'Add your cell phone number first so we can reach you on WhatsApp.']);
            }
        }

        if ($whatsappConsent !== $this->whatsappConsent($user)) {
            ConsentRecord::create([
                'user_id' => $user->id,
                'consent_type' => self::WHATSAPP_CONSENT,
                'version' => (int) config('patient.notifications.consent_version', 1),
                'wording' => config('patient.notifications.whatsapp_wording'),
                'granted' => $whatsappConsent,
                'ip_address' => request()->ip(),
                'granted_at' => now(),
                'revoked_at' => $whatsappConsent ? null : now(),
            ]);
        }

        $user->forceFill(['preferred_contact' => $preferred])->save();
    }

    /** Is this channel something we can use for this patient right now? */
    public function usable(User $user, string $channel): bool
    {
        return match ($channel) {
            'email' => filled($user->email),
            'whatsapp' => $this->whatsappConsent($user) && filled($user->phone) && $this->dispatcher->isConfigured('whatsapp'),
            default => false,
        };
    }

    /**
     * Channels to try, in order: the chosen one first, then the other as a fallback. Only usable ones are listed, and an
     * unusable preferred channel is skipped without trying it.
     *
     * @return string[]
     */
    public function order(User $user): array
    {
        $first = $this->preferred($user);
        $second = $first === 'whatsapp' ? 'email' : 'whatsapp';

        return array_values(array_filter([$first, $second], fn ($c) => $this->usable($user, $c)));
    }
}

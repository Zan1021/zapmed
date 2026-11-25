<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\BookingLink;
use App\Models\FollowUpRequest;
use App\Models\User;
use App\Services\Compliance\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Booking links (Craig's guide 6.2, 8.2).
 *
 *  - Operations make a link for one patient: how many times it can be opened (default 3) and for how long (default 14 days).
 *  - The link is returned ONCE, when it is made. Only its SHA-256 hash is stored.
 *  - Opening it counts a use. It never signs anybody in: the patient must already be signed in as that patient, otherwise
 *    nothing is used up. A wrong, expired, switched-off or used-up link opens nothing.
 *  - "Resend" = make a new link and switch the old one off, so there is never a pile of working links.
 */
class BookingLinks
{
    public function __construct(private readonly AuditTrail $audit)
    {
    }

    /** @return array{link: BookingLink, url: string} the url is shown once */
    public function make(User $ops, User $patient, ?int $uses = null, ?int $days = null, ?FollowUpRequest $followUp = null): array
    {
        abort_unless($ops->role === UserRole::Admin, 403);

        if ($patient->role !== UserRole::Patient) {
            throw ValidationException::withMessages(['patient' => 'A booking link is for a patient.']);
        }
        $cfg = config('operations.booking_links');
        $uses ??= $cfg['default_uses'];
        $days ??= $cfg['default_days'];
        if ($uses < 1 || $uses > $cfg['max_uses']) {
            throw ValidationException::withMessages(['uses' => 'A link can be used 1 to '.$cfg['max_uses'].' times.']);
        }
        if ($days < 1 || $days > $cfg['max_days']) {
            throw ValidationException::withMessages(['days' => 'A link can last 1 to '.$cfg['max_days'].' days.']);
        }
        if ($followUp && $followUp->patient_id !== $patient->id) {
            throw ValidationException::withMessages(['follow_up' => 'That follow-up belongs to another patient.']);
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $link = DB::transaction(function () use ($ops, $patient, $uses, $days, $followUp, $token) {
            $link = BookingLink::create([
                'token_hash' => $this->hash($token), 'patient_id' => $patient->id, 'follow_up_request_id' => $followUp?->id,
                'max_uses' => $uses, 'uses' => 0, 'expires_at' => now()->addDays($days), 'created_by' => $ops->id,
            ]);
            $this->audit->record('booking_link', 'created', 'user', (string) $patient->id, null, ['link_id' => $link->id, 'max_uses' => $uses, 'days' => $days], $ops->id);

            return $link;
        });

        return ['link' => $link, 'url' => route('booking-link.open', $token)];
    }

    /**
     * Send a link that was just made to its patient, on the channel they chose (and the other one if that fails). The link is only known
     * at the moment it is made (only its hash is kept), so this takes the address that was shown to Operations. No health detail in the words.
     *
     * @return bool true when a message was handed over; false when sending is switched off or Operations switched the words off
     */
    public function send(User $ops, BookingLink $link, string $url): bool
    {
        abort_unless($ops->role === UserRole::Admin, 403);

        if (! config('operations.booking_links.notify_enabled') || ! $link->isUsable() || $this->hash(basename(parse_url($url, PHP_URL_PATH) ?: '')) !== $link->token_hash) {
            return false;
        }

        $patient = $link->patient;
        $t = app(MessageTemplates::class)->render('ops.booking_link', [
            'link' => $url, 'until' => $link->expires_at->setTimezone(config('doctor.time.timezone'))->format('j M Y'),
        ]);
        if ($patient === null || $t === null) {
            return false;
        }

        app(\App\Services\Patient\PatientMessenger::class)->send(new \App\Services\Notifications\OutboundMessage(
            templateKey: 'ops.booking_link', category: 'transactional', user: $patient, phone: $patient->phone,
            subject: $t['subject'], body: $t['body'], meta: ['booking_link_id' => $link->id, 'template_version' => $t['version']],
        ));
        $this->audit->record('booking_link', 'sent', 'user', (string) $link->patient_id, null, ['link_id' => $link->id], $ops->id);

        return true;
    }

    /** A fresh link in place of the old one, which is switched off. */
    public function resend(User $ops, BookingLink $old): array
    {
        abort_unless($ops->role === UserRole::Admin, 403);

        $new = $this->make(
            $ops, $old->patient, $old->max_uses, max(1, (int) ceil(now()->diffInDays($old->expires_at, false)) ?: config('operations.booking_links.default_days')),
            $old->follow_up_request_id ? FollowUpRequest::find($old->follow_up_request_id) : null,
        );
        $this->revoke($ops, $old);

        return $new;
    }

    public function revoke(User $ops, BookingLink $link): BookingLink
    {
        abort_unless($ops->role === UserRole::Admin, 403);

        if ($link->revoked_at === null) {
            $link->update(['revoked_at' => now()]);
            $this->audit->record('booking_link', 'revoked', 'user', (string) $link->patient_id, null, ['link_id' => $link->id], $ops->id);
        }

        return $link->fresh();
    }

    /**
     * Open a link as the signed-in user. Returns the link when it may be used (and counts the use), or throws with the reason.
     * A user who is not the link's patient uses nothing up.
     */
    public function open(string $token, User $user): BookingLink
    {
        $link = BookingLink::where('token_hash', $this->hash($token))->first();
        if (! $link) {
            throw ValidationException::withMessages(['link' => 'This link is not valid.']);
        }
        if ($link->patient_id !== $user->id) {
            throw ValidationException::withMessages(['link' => 'This link is for somebody else. Please sign in with your own account.']);
        }

        return DB::transaction(function () use ($link) {
            // lock the row so two clicks at the same moment cannot both take the last use
            $fresh = BookingLink::whereKey($link->id)->lockForUpdate()->first();
            if ($problem = $fresh->problem()) {
                throw ValidationException::withMessages(['link' => $problem]);
            }
            $fresh->update(['uses' => $fresh->uses + 1, 'last_used_at' => now()]);

            return $fresh->fresh();
        });
    }

    /** Who the link belongs to (so the visitor can be sent to sign in), without using it up. Null for an unknown link. */
    public function patientIdFor(string $token): ?int
    {
        return BookingLink::where('token_hash', $this->hash($token))->value('patient_id');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}

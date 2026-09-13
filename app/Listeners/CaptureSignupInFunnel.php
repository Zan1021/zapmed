<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Crm\JourneyCapture;
use Illuminate\Auth\Events\Registered;

/**
 * Task 6 (CRM capture): when a patient registers, create their CRM lead and record the sign-up funnel
 * event. Auto-discovered by Laravel 11 (typed Registered argument). Patients only — doctors/admins are
 * created via other flows and are not CRM leads. JourneyCapture is internally guarded, so this listener
 * can never break registration.
 */
class CaptureSignupInFunnel
{
    public function __construct(private readonly JourneyCapture $journey)
    {
    }

    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        // The user may arrive fresh from User::create() before the DB-default role is hydrated onto
        // the in-memory instance, so role can be null here. Reload it to get the persisted role.
        if ($user->role === null) {
            $user->refresh();
        }

        // Self-registration only ever creates patients; capture patients (or an as-yet-unresolved
        // role, which self-registration implies is a patient). Skip explicit doctors/admins/staff.
        if ($user->role !== null && $user->role !== UserRole::Patient) {
            return;
        }

        $this->journey->signedUp($user);
    }
}

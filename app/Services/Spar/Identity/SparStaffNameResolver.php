<?php

namespace App\Services\Spar\Identity;

use App\Models\User;

/**
 * Resolves a SPAR `captured_by_id` (host User id) to a display name for the
 * staff patient-detail view.
 *
 * WHY A CLASS (not a config closure): the package consumer
 * (Zapmed\SparCore\Livewire\PatientDetail) accepts `spar.staff_name_resolver`
 * as EITHER a callable OR a class-string exposing a `name($id)` method. We use
 * the class-string form so the value stored in config stays a plain string and
 * `php artisan config:cache` can serialize it. A closure in config breaks
 * config:cache with "Call to undefined method Closure::__set_state()" on every
 * ZapMed host deploy — do NOT put a closure back into config.
 */
class SparStaffNameResolver
{
    public function name(int|string $id): string
    {
        return User::whereKey($id)->value('name') ?? "Staff #{$id}";
    }
}

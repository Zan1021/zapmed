<?php

namespace App\Services\Spar;

use App\Models\PharmacyUser;

/**
 * Standalone resolver: SPAR `captured_by_id` (PharmacyUser id) -> display name
 * for the staff patient-detail view.
 *
 * Class-string (not a closure) so `config:cache` can serialize the config value.
 * A closure in `spar.staff_name_resolver` breaks config:cache with
 * "Call to undefined method Closure::__set_state()". The package consumer
 * (Zapmed\SparCore\Livewire\PatientDetail) resolves a class-string via
 * app()->make()->name($id).
 */
class SparStaffNameResolver
{
    public function name(int|string $id): string
    {
        return PharmacyUser::whereKey($id)->value('name') ?? "Staff #{$id}";
    }
}

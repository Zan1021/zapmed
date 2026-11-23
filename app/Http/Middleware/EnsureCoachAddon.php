<?php

namespace App\Http\Middleware;

use App\Services\Coach\CoachAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only patients with the coach add-on (active coaching assignment or the coach-plan subscription) get in.
 * Everyone else is sent to the weight-loss page, where the membership is explained.
 */
class EnsureCoachAddon
{
    public function __construct(private readonly CoachAccess $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->access->patientHasAddon($user)) {
            return redirect('/weight-loss')->with('status', 'The coach app is part of the Zapmed weight-loss membership.');
        }

        return $next($request);
    }
}

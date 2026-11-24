<?php

namespace App\Services\Patient;

use App\Models\BloodTestRequest;
use App\Models\FollowUpRequest;
use App\Models\OrderAuthorisation;
use App\Models\PatientNote;
use App\Models\User;
use App\Services\Doctor\AuthorisationService;
use Illuminate\Support\Collection;

/**
 * "From your care team" on the Notifications tab (Craig's guide 3.1): what the patient has been asked to do or can read.
 * Patient-visible only: follow-up requests, blood-test requests, check-ins that are due and new summaries. Never a
 * confidential clinical note or a staff note. Plain words, no medicine names, no test names, no results.
 *
 * @phpstan-type Item array{key:string, text:string, at:\Illuminate\Support\Carbon, route:string}
 */
class PatientInbox
{
    public function __construct(private readonly AuthorisationService $authorisations)
    {
    }

    /** @return Collection<int,array{key:string,text:string,at:\Illuminate\Support\Carbon,route:string}> newest first */
    public function items(User $patient): Collection
    {
        $items = collect();

        FollowUpRequest::with('doctor')->where('patient_id', $patient->id)->where('status', FollowUpRequest::REQUESTED)->get()->each(
            fn (FollowUpRequest $f) => $items->push([
                'key' => 'follow-up-'.$f->id,
                'text' => 'Your doctor asked for a follow-up. Please book it by '.$f->due_at->setTimezone(config('doctor.time.timezone'))->format('j M Y').'.',
                'at' => $f->created_at,
                'route' => 'patient.follow-up',
            ])
        );

        BloodTestRequest::where('patient_id', $patient->id)->where('status', BloodTestRequest::REQUESTED)->get()->each(
            fn (BloodTestRequest $b) => $items->push([
                'key' => 'blood-test-'.$b->id,
                'text' => 'Your doctor asked for some tests. Open Health Stats to see what to do.',
                'at' => $b->created_at,
                'route' => 'patient.health',
            ])
        );

        OrderAuthorisation::with('order')->where('patient_id', $patient->id)->whereIn('status', ['active', 'expired'])->get()
            ->filter(fn (OrderAuthorisation $a) => $this->authorisations->isDueForReview($a)
                && ! FollowUpRequest::open()->where('order_id', $a->order_id)->exists())
            ->each(fn (OrderAuthorisation $a) => $items->push([
                'key' => 'renewal-'.$a->id,
                'text' => 'Your treatment approval ends on '.$a->expires_on->format('j M Y').'. Book a short check-in with your doctor to renew it.',
                'at' => $a->updated_at ?? $a->created_at,
                'route' => 'patient.follow-up',
            ]));

        PatientNote::where('patient_id', $patient->id)->where('type', PatientNote::PATIENT_SUMMARY)->orderByDesc('id')->limit(5)->get()->each(
            fn (PatientNote $n) => $items->push([
                'key' => 'summary-'.$n->id,
                'text' => 'Your doctor added a summary for you. Open Health Stats to read it.',
                'at' => $n->created_at,
                'route' => 'patient.health',
            ])
        );

        return $items->sortByDesc('at')->values();
    }
}

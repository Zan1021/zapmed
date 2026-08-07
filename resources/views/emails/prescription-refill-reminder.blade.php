@component('mail::message')
# Time to Reorder Your Medication

Hi {{ $prescription->patient->first_name }},

Your chronic medication is running low — you have approximately **{{ $daysRemaining }} days** remaining.

**Prescription:** {{ $prescription->reference }}
**Medications:**
@foreach($prescription->items as $item)
- {{ $item->medication_name }} {{ $item->strength }} ({{ $item->dosage }}, {{ $item->frequency }})
@endforeach

**Total:** {{ $prescription->formatted_total }}

Reorder now and have your medication delivered to your door — no consultation needed for repeats.

@component('mail::button', ['url' => route('patient.prescriptions')])
Reorder My Medication
@endcomponent

@if($prescription->repeats > 0)
*You have {{ $prescription->repeats - $prescription->repeats_used }} repeat(s) remaining on this prescription.*
@endif

If your condition has changed or you need a new prescription, please book a consultation with your doctor.

Thanks,<br>
The Zapmed Team
@endcomponent

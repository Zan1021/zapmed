@props([
    'action' => 'respondToReminder', // Livewire method on the host component
    'subjectType',                   // FQCN of the actionable subject
    'subjectId',                     // its key
    'show' => ['yes_collect', 'yes_deliver', 'remind_next_cycle', 'remind_in_days', 'stop_reminders'],
    'compact' => false,              // tighter layout for list rows
])

{{--
    Reusable patient response widget (spar-close-the-loop FR-B3). Rendered on
    reminder cards, renewal cards and order prompts. Each button calls the host
    Livewire component's $action method with (signal, subjectType, subjectId,
    payload). The host wires it to SparActionService::recordPatientResponse().

    NEVER / REMIND / OPT-IN copy (FR-A3) lives on the reminder card itself; this
    widget is the actionable choices. Signals map to SparPatientSignalType.
--}}
@php
    $sparPrimary = config('spar.branding.primary_color', '#006B3F');

    $labels = [
        'yes_collect'       => 'Pack for collection',
        'yes_deliver'       => 'Deliver to me',
        'remind_next_cycle' => 'Remind me next month',
        'remind_in_days'    => 'Remind me in a week',
        'stop_reminders'    => 'Stop reminders',
        'ignore_month'      => 'Not this month',
        'ignore_future'     => 'Never for this',
    ];

    // "Remind me in a week" carries a days payload; others carry none.
    $payloads = [
        'remind_in_days' => ['days' => 7],
    ];

    $primaryChoices = ['yes_collect', 'yes_deliver'];
    $wrap = $compact ? 'gap-2' : 'gap-3';
@endphp

<div {{ $attributes->merge(['class' => "flex flex-wrap items-center {$wrap}"]) }}
     role="group" aria-label="Respond to this reminder">
    @foreach($show as $signal)
        @php
            $isPrimary = in_array($signal, $primaryChoices, true);
            $payload = $payloads[$signal] ?? [];
            $isMuted = in_array($signal, ['stop_reminders', 'ignore_month', 'ignore_future'], true);
        @endphp

        <button type="button"
                wire:click="{{ $action }}('{{ $signal }}', @js($subjectType), {{ (int) $subjectId }}, @js($payload))"
                wire:loading.attr="disabled"
                @class([
                    'rounded-xl font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-1',
                    'px-4 py-2 text-sm' => ! $compact,
                    'px-3 py-1.5 text-xs' => $compact,
                    'text-white shadow-sm' => $isPrimary,
                    'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' => ! $isPrimary && ! $isMuted,
                    'bg-white border border-gray-200 text-gray-400 hover:text-gray-600 hover:bg-gray-50' => $isMuted,
                ])
                @if($isPrimary) style="background-color: {{ $sparPrimary }};" @endif
                aria-label="{{ $labels[$signal] ?? $signal }}">
            {{ $labels[$signal] ?? $signal }}
        </button>
    @endforeach
</div>

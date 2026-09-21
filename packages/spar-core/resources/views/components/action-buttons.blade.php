@props([
    'subjectType',                        // FQCN of the actionable subject (dispense / journey)
    'subjectId',                          // its key
    'nudgeAction'   => 'nudgeItem',       // Livewire methods on the host component
    'snoozeAction'  => 'snoozeItem',
    'messageAction' => 'messageItem',
    'snoozeDays'    => 7,                  // default staff "follow up next week"
    'show'          => ['nudge', 'snooze', 'message'],
    'compact'       => true,              // list rows are tight by default
])

{{--
    Reusable STAFF action widget (spar-close-the-loop FR-B6). Rendered on the
    Exceptions rows (overdue dispenses / missing renewals / unresponsive) so the
    close-the-loop engine (SparActionService) is reachable from the UI.

    Each button calls the host Livewire component method with
    (subjectType, subjectId[, days]). The host wires those to:
      nudge   → SparActionService::nudge()            (consent-gated outbound)
      snooze  → SparActionService::snooze($days)      (staff deferral, no message)
      message → opens a compose modal → personalMessage()

    Consent-gating + auditing live in SparActionService — this widget only
    surfaces the choices. Mirrors <x-spar::response-actions> (the patient half).
--}}
@php
    $sparPrimary = config('spar.branding.primary_color', '#006B3F');
    $wrap = $compact ? 'gap-2' : 'gap-3';
    $sizeClasses = $compact ? 'px-3 py-1.5 text-xs' : 'px-4 py-2 text-sm';
@endphp

<div {{ $attributes->merge(['class' => "flex flex-wrap items-center {$wrap}"]) }}
     role="group" aria-label="Follow-up actions">

    @if(in_array('nudge', $show, true))
        <button type="button"
                wire:click="{{ $nudgeAction }}(@js($subjectType), {{ (int) $subjectId }})"
                wire:loading.attr="disabled"
                class="{{ $sizeClasses }} rounded-xl font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-1"
                style="background-color: {{ $sparPrimary }};"
                aria-label="Send a reminder nudge">
            Nudge
        </button>
    @endif

    @if(in_array('snooze', $show, true))
        <button type="button"
                wire:click="{{ $snoozeAction }}(@js($subjectType), {{ (int) $subjectId }}, {{ (int) $snoozeDays }})"
                wire:loading.attr="disabled"
                class="{{ $sizeClasses }} rounded-xl font-semibold bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition focus:outline-none focus:ring-2 focus:ring-offset-1"
                aria-label="Snooze this item for {{ (int) $snoozeDays }} days">
            Snooze {{ (int) $snoozeDays }}d
        </button>
    @endif

    @if(in_array('message', $show, true))
        <button type="button"
                wire:click="{{ $messageAction }}(@js($subjectType), {{ (int) $subjectId }})"
                wire:loading.attr="disabled"
                class="{{ $sizeClasses }} rounded-xl font-semibold bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition focus:outline-none focus:ring-2 focus:ring-offset-1"
                aria-label="Send a personal message">
            Message
        </button>
    @endif
</div>

<div>
    <x-patient-tabs active="health" />

    <h1 class="mb-4 text-2xl font-bold text-gray-900">Health stats</h1>

    @if($summary)
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-5" data-section="summary">
            <p class="text-xs font-semibold uppercase text-green-800">From your doctor, {{ $summary->created_at->format('j M Y') }}</p>
            <p class="mt-2 whitespace-pre-line text-sm text-green-900">{{ $summary->body }}</p>
        </div>
    @endif

    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5" data-section="weight">
        <h2 class="mb-3 text-sm font-semibold uppercase text-gray-500">Weight</h2>
        @if($stats->current !== null)
            <p class="text-sm text-gray-800">Now <strong>{{ number_format($stats->current, 1) }} kg</strong>
                @if($stats->start !== null) &middot; started at {{ number_format($stats->start, 1) }} kg @endif
                @if($stats->goal !== null) &middot; goal {{ number_format($stats->goal, 1) }} kg @endif
                @if($stats->bmi !== null) &middot; BMI {{ number_format($stats->bmi, 1) }} <span class="text-xs text-gray-400">(a general guide only)</span> @endif
            </p>
        @else
            <p class="text-sm text-gray-500">No weight logged yet.</p>
        @endif

        @if($chart['hasData'])
            <svg viewBox="0 0 {{ \App\Services\Coach\WeightChart::W }} {{ \App\Services\Coach\WeightChart::H }}" class="mt-3 w-full max-w-xl" data-chart role="img" aria-label="Weight over the last 90 days">
                @if($chart['goalY'] !== null)<line x1="0" x2="{{ \App\Services\Coach\WeightChart::W }}" y1="{{ $chart['goalY'] }}" y2="{{ $chart['goalY'] }}" stroke="#16a34a" stroke-dasharray="4 4" stroke-width="1" />@endif
                <polyline fill="none" stroke="#0d9488" stroke-width="2" points="{{ collect($chart['points'])->map(fn ($p) => $p['x'].','.$p['y'])->join(' ') }}" />
                @foreach($chart['points'] as $p)<circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="2.5" fill="#0d9488" />@endforeach
            </svg>
        @endif

        <div class="mt-3 flex flex-wrap items-end gap-3">
            <div><label class="mb-1 block text-xs text-gray-500">Today's weight (kg)</label><input type="text" inputmode="decimal" wire:model="weight" class="w-28 rounded-lg border-gray-300 text-sm"></div>
            <button type="button" wire:click="logWeight" data-action="log-weight" class="rounded-lg bg-zapmed-600 px-4 py-2 text-sm text-white">Save</button>
            <div class="ml-4"><label class="mb-1 block text-xs text-gray-500">Height (cm)</label><input type="text" inputmode="decimal" wire:model="height" class="w-24 rounded-lg border-gray-300 text-sm"></div>
            <button type="button" wire:click="saveHeight" data-action="save-height" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700">Update</button>
        </div>
        @foreach(['weight', 'height'] as $f) @error($f) <p class="mt-1 text-xs text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach
    </div>

    @if($weightLoss)
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5" data-section="side-effects">
            <h2 class="mb-1 text-sm font-semibold uppercase text-gray-500">How are you feeling?</h2>
            <p class="mb-3 text-xs text-gray-500">Score anything you notice from 0 (none) to 10 (very bad). This is only a record for your care team. It is never advice to change your treatment.</p>
            @if(session('health-flagged'))<p class="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900" data-flagged>{{ session('health-flagged') }}</p>@endif
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                @foreach($symptoms as $name)
                    <label class="text-sm text-gray-700">{{ $name }}
                        <select wire:model="scores.{{ $name }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                            <option value="">-</option>
                            @for($i = 1; $i <= 10; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                        </select>
                    </label>
                @endforeach
            </div>
            @error('scores') <p class="mt-2 text-xs text-red-600" data-error="scores">{{ $message }}</p> @enderror
            <button type="button" wire:click="saveSideEffects" data-action="save-side-effects" class="mt-3 rounded-lg bg-zapmed-600 px-4 py-2 text-sm text-white">Save</button>

            @if($recent->isNotEmpty())
                <ul class="mt-4 space-y-1 text-sm text-gray-700" data-recent>
                    @foreach($recent as $e)<li>{{ $e->logged_on->format('j M') }}: {{ $e->symptom }} {{ $e->score }}/10</li>@endforeach
                </ul>
            @endif
        </div>
    @endif

    @if($tests->isNotEmpty())
        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5" data-section="tests">
            <h2 class="mb-3 text-sm font-semibold uppercase text-gray-500">Tests your doctor asked for</h2>
            @foreach($tests as $t)
                <div class="mb-3 border-b border-gray-100 pb-3" data-test="{{ $t->id }}">
                    <p class="text-sm text-gray-800">Asked {{ $t->created_at->format('j M Y') }} &middot;
                        {{ ['requested' => 'Waiting for your results', 'results_received' => 'Results received, your doctor will review them', 'reviewed' => 'Reviewed by your doctor', 'cancelled' => 'Cancelled'][$t->status] ?? $t->status }}</p>
                    @if($t->lab_form_path)<p class="text-sm"><a class="text-zapmed-700 hover:underline" href="{{ route('bloodtests.form', $t) }}">Download your lab form</a></p>@endif
                    @foreach($t->results as $res)<p class="text-xs text-gray-500"><a class="text-zapmed-700 hover:underline" href="{{ route('bloodtests.result', [$t, $res]) }}">{{ $res->original_name }}</a></p>@endforeach
                    @if($t->isOpen())
                        <div class="mt-2 flex items-center gap-2">
                            <input type="file" wire:model="testUploads.{{ $t->id }}" class="text-xs">
                            <button type="button" wire:click="uploadTestResult({{ $t->id }})" data-action="upload-test" class="rounded-lg border border-gray-300 px-3 py-1 text-xs">Send my results</button>
                        </div>
                        @error('test_'.$t->id) <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        @error('file') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-5" data-section="records">
        <h2 class="mb-3 text-sm font-semibold uppercase text-gray-500">Your medical records and results</h2>
        @forelse($records as $r)
            <p class="text-sm text-gray-800" data-record="{{ $r->id }}">
                <a class="text-zapmed-700 hover:underline" href="{{ route('health-records.show', $r) }}">{{ $r->title }}</a>
                <span class="text-xs text-gray-400">{{ $r->category === 'result' ? 'from your doctor' : 'uploaded by you' }} &middot; {{ $r->created_at->format('j M Y') }}</span>
            </p>
        @empty
            <p class="text-sm text-gray-500">Nothing uploaded yet.</p>
        @endforelse

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <div><label class="mb-1 block text-xs text-gray-500">Name of the document</label><input type="text" wire:model="recordTitle" maxlength="120" class="rounded-lg border-gray-300 text-sm"></div>
            <input type="file" wire:model="recordFile" class="text-xs">
            <button type="button" wire:click="uploadRecord" data-action="upload-record" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Upload</button>
        </div>
        @foreach(['title', 'file', 'recordFile'] as $f) @error($f) <p class="mt-1 text-xs text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach
    </div>
</div>

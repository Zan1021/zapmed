<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Operations overview</h1>
        <p class="mt-1 text-sm text-gray-500">Counts and totals only. No patient health details. More figures are on the <a class="text-indigo-600 hover:underline" href="{{ route('admin.stats') }}">Stats</a> and <a class="text-indigo-600 hover:underline" href="{{ route('admin.reports') }}">Reports</a> pages.</p>
    </div>

    <div class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 text-sm">
        <label>Period
            <select wire:model.live="useMonth" class="mt-1 block rounded-lg border-gray-300 text-sm"><option value="1">A month</option><option value="0">Between two dates</option></select>
        </label>
        @if($useMonth === '1')
            <label>Month <input type="month" wire:model.live="month" class="mt-1 block rounded-lg border-gray-300 text-sm"></label>
        @else
            <label>From <input type="date" wire:model.live="from" class="mt-1 block rounded-lg border-gray-300 text-sm"></label>
            <label>To <input type="date" wire:model.live="to" class="mt-1 block rounded-lg border-gray-300 text-sm"></label>
        @endif
    </div>
    @foreach(['month', 'from', 'to'] as $f) @error($f) <p class="mb-2 text-sm text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach

    @if($d)
        @php($rand = fn ($cents) => 'R'.number_format($cents / 100, 2))
        <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4" data-section="headline">
            <div class="rounded-xl border border-gray-200 bg-white p-4"><p class="text-xs text-gray-500">Orders today</p><p class="text-2xl font-semibold" data-figure="orders-today">{{ $d['orders']['today'] }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4"><p class="text-xs text-gray-500">Orders this month</p><p class="text-2xl font-semibold" data-figure="orders-month">{{ $d['orders']['this_month'] }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4"><p class="text-xs text-gray-500">Orders in this period</p><p class="text-2xl font-semibold" data-figure="orders-range">{{ $d['orders']['in_range'] }}</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4"><p class="text-xs text-gray-500">Active doctors</p><p class="text-2xl font-semibold" data-figure="doctors">{{ $d['doctors'] }}</p></div>
        </div>

        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4" data-section="daily">
            <h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Orders each day</h2>
            @php($max = max(1, max($d['orders']['per_day'] ?: [0])))
            @php($palette = ['bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-rose-500', 'bg-sky-500', 'bg-violet-500', 'bg-teal-500', 'bg-orange-500'])
            @php($colour = fn ($cat) => $palette[array_search($cat, $d['orders']['chart_categories']) % count($palette)])
            <div class="flex h-28 items-end gap-px" role="img" aria-label="Orders each day, split by service">
                @foreach($d['orders']['per_day'] as $day => $n)
                    <div class="flex flex-1 flex-col-reverse" style="height: {{ $n ? max(2, (int) round($n / $max * 100)) : 2 }}%" title="{{ $day }}: {{ $n }}" data-day="{{ $day }}" data-count="{{ $n }}">
                        @forelse($d['orders']['per_day_by_category'][$day] ?? [] as $cat => $cn)
                            <div class="{{ $colour($cat) }}" style="height: {{ (int) round($cn / $n * 100) }}%" data-cat="{{ $cat }}" data-cat-count="{{ $cn }}"></div>
                        @empty
                            <div class="h-full bg-gray-200"></div>
                        @endforelse
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-600" data-legend>
                @foreach($d['orders']['chart_categories'] as $cat)<span><span class="mr-1 inline-block h-2 w-2 rounded-sm {{ $colour($cat) }}"></span>{{ $cat }}</span>@endforeach
            </div>
        </div>

        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4" data-section="categories">
                <h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Orders by service</h2>
                @forelse($d['orders']['by_category'] as $cat => $n)<p class="text-sm text-gray-800">{{ $cat }}: <strong>{{ $n }}</strong></p>@empty<p class="text-sm text-gray-400">No orders in this period.</p>@endforelse
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4" data-section="revenue">
                <h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Money</h2>
                <p class="text-sm text-gray-800">Received: <strong data-figure="received">{{ $rand($d['revenue']['received_cents']) }}</strong> ({{ $d['revenue']['received_count'] }} payments)</p>
                <p class="text-sm text-gray-800">Waiting to be paid: <strong data-figure="pending">{{ $rand($d['revenue']['pending_cents']) }}</strong> ({{ $d['revenue']['pending_count'] }} orders)</p>
                <p class="text-sm text-gray-800">Payments that failed: <strong>{{ $d['revenue']['failed_count'] }}</strong></p>
                <div class="mt-2 border-t border-gray-100 pt-2" data-section="revenue-split">
                    <p class="text-xs text-gray-500">Received, by type</p>
                    <p class="text-sm text-gray-800">First orders: <strong data-figure="first">{{ $rand($d['revenue']['first_cents']) }}</strong>, repeats: <strong data-figure="repeat">{{ $rand($d['revenue']['repeat_cents']) }}</strong></p>
                    <p class="mt-1 text-xs text-gray-500">Received, by service</p>
                    @forelse($d['revenue']['by_category_cents'] as $cat => $cents)<p class="text-sm text-gray-800" data-revenue-cat="{{ $cat }}">{{ $cat }}: <strong>{{ $rand($cents) }}</strong></p>@empty<p class="text-sm text-gray-400">Nothing received in this period.</p>@endforelse
                </div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4" data-section="signups">
                <h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Sign-ups</h2>
                @foreach(['all_time' => 'All time', 'in_range' => 'This period'] as $k => $label)
                    @php($s = $d['signups'][$k])
                    <p class="text-sm text-gray-800">{{ $label }}: <strong data-figure="signups-{{ $k }}">{{ $s['total'] }}</strong>
                        <span class="text-xs text-gray-500">({{ $s['active'] }} active, {{ $s['inactive'] }} inactive, {{ $s['subscription'] }} on repeats, {{ $s['one_off'] }} one-off)</span></p>
                @endforeach
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4" data-section="abandoned">
                <h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Unfinished orders</h2>
                <p class="text-sm text-gray-800">Waiting for payment now: <strong data-figure="unpaid">{{ $d['abandoned']['unpaid_now'] }}</strong> ({{ $rand($d['abandoned']['unpaid_value_cents']) }})</p>
                <p class="text-sm text-gray-800">Older than a day: <strong>{{ $d['abandoned']['older_than_a_day'] }}</strong></p>
                <p class="text-sm text-gray-800">Waiting for a reminder: <strong>{{ $d['abandoned']['waiting_for_a_reminder'] }}</strong> <span class="text-xs text-gray-500">{{ $d['abandoned']['reminders_on'] ? '(reminders are on)' : '(reminders are switched off)' }}</span></p>
            </div>
        </div>

        <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4" data-section="cancelled">
            <h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Cancelled</h2>
            <p class="text-sm text-gray-800">Orders: <strong>{{ $d['cancelled']['orders'] }}</strong>, repeat orders: <strong>{{ $d['cancelled']['repeat_orders'] }}</strong></p>
            @foreach($d['cancelled']['reasons'] as $reason => $r)<p class="text-sm text-gray-700" data-reason="{{ $reason }}">{{ ucfirst($reason) }}: {{ $r['count'] }} ({{ $r['percent'] }}%)</p>@endforeach
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4" data-section="coaching">
            <h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Coaching and doctor follow-up</h2>
            @php($c = $d['coaching'])
            <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm text-gray-800 md:grid-cols-3">
                <p>Coached patients: <strong data-figure="coached">{{ $c['active_coached_patients'] }}</strong></p>
                <p>Messages from coaches: <strong>{{ $c['outreach_messages'] }}</strong></p>
                <p>Patients reached: <strong>{{ $c['patients_reached'] }}</strong>, replied: <strong>{{ $c['patients_replied'] }}</strong> ({{ $c['reply_percent'] }}%)</p>
                <p>Safety flags in this period: <strong>{{ $c['safety_flags_in_range'] }}</strong> ({{ $c['safety_flags_open'] }} still open)</p>
                <p>Follow-ups open: <strong>{{ $c['follow_ups_open'] }}</strong> ({{ $c['follow_ups_overdue'] }} overdue)</p>
                <p>Doctor requests open: <strong>{{ $c['doctor_requests_open'] }}</strong></p>
                <p>Orders paused: <strong>{{ $c['pauses_in_range'] }}</strong></p>
                <p>Average weight lost: <strong data-figure="weight">{{ $c['weight']['average_kg_lost'] !== null ? $c['weight']['average_kg_lost'].' kg' : 'not shown (fewer than 3 patients)' }}</strong> <span class="text-xs text-gray-500">({{ $c['weight']['patients'] }} patients logged)</span></p>
            </div>
        </div>
    @endif
</div>

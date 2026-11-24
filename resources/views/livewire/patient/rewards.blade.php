<div>
    <x-patient-tabs active="rewards" />

    <h1 class="mb-4 text-2xl font-bold text-gray-900">Rewards</h1>

    @forelse($coupons as $c)
        <div class="mb-3 flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5" data-coupon="{{ $c['code'] }}">
            <div>
                <p class="font-mono text-lg font-semibold text-gray-900">{{ $c['code'] }}</p>
                <p class="text-sm text-gray-700">{{ $c['description'] }}</p>
            </div>
            <div class="text-right text-xs text-gray-500">
                @if($c['expires'])Use by {{ $c['expires']->format('j M Y') }}<br>@endif
                @if($c['uses_left'] !== null){{ $c['uses_left'] }} {{ \Illuminate\Support\Str::plural('use', $c['uses_left']) }} left @endif
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center text-sm text-gray-500" data-empty="coupons">
            You have no rewards right now. When we have a discount for you, it will show here.
        </div>
    @endforelse
    <p class="mt-3 text-xs text-gray-400">Mention a code when you contact us and we will apply it to your order.</p>
</div>

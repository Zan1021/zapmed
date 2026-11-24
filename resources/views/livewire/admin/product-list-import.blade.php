<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Product list: import and export</h1>
        <p class="mt-1 text-sm text-gray-500">Download the catalogue, change it in a spreadsheet and load it back. The file is checked first and nothing changes until every row is fine. A blank cell leaves the product as it is.</p>
    </div>

    <div class="mb-4 rounded-xl border border-gray-200 bg-white p-5">
        <h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Download</h2>
        <a href="{{ route('admin.product-list.export') }}" data-action="export" class="mr-3 rounded-lg border border-gray-300 px-3 py-2 text-sm">Whole catalogue (CSV)</a>
        <a href="{{ route('admin.product-list.export', ['active' => 1]) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">Active products only</a>
        <p class="mt-3 text-xs text-gray-500">Columns: {{ implode(', ', $columns) }}. Price is in rand (299.50). Yes or no for is_subscription and is_active. A product is matched by NAPPI code, or by name + strength + form.</p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5" data-section="upload">
        <h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Load a file</h2>
        <input type="file" wire:model="file" accept=".csv,text/csv" class="text-sm">
        <button type="button" wire:click="check" data-action="check" class="ml-2 rounded-lg border border-gray-300 px-3 py-2 text-sm">Check the file</button>
        @foreach(['file'] as $f) @error($f) <p class="mt-2 text-sm text-red-600" data-error="{{ $f }}">{{ $message }}</p> @enderror @endforeach

        @if($preview)
            <div class="mt-4" data-section="preview">
                <p class="text-sm text-gray-800">
                    <strong>{{ $preview['created'] }}</strong> new, <strong>{{ $preview['updated'] }}</strong> changed, <strong>{{ $preview['unchanged'] }}</strong> the same.
                </p>
                @if($preview['ignored_columns'])<p class="mt-1 text-xs text-gray-500">These columns are not used: {{ implode(', ', $preview['ignored_columns']) }}.</p>@endif

                @if($preview['errors'])
                    <p class="mt-3 text-sm font-medium text-red-700" data-has-errors>{{ count($preview['errors']) }} {{ \Illuminate\Support\Str::plural('problem', count($preview['errors'])) }}. Nothing was changed. Fix the file and check it again.</p>
                    <ul class="mt-1 max-h-64 overflow-y-auto text-sm text-red-700">
                        @foreach(array_slice($preview['errors'], 0, 50) as $e)<li>Row {{ $e['row'] }}: {{ $e['message'] }}</li>@endforeach
                    </ul>
                @else
                    <button type="button" wire:click="apply" data-action="apply" class="mt-3 rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white">Load it into the catalogue</button>
                @endif
            </div>
        @endif

        @if($done)
            <p class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-800" data-done>Done: {{ $done['created'] }} added, {{ $done['updated'] }} changed, {{ $done['unchanged'] }} left as they were.</p>
        @endif
    </div>
</div>

<div>
    <x-slot name="header">SPAR Data Imports</x-slot>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-green-800">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-red-800">{{ session('error') }}</p>
        </div>
    @endif

    <!-- Upload Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Import SPAR Files</h3>
        <p class="text-sm text-gray-500 mb-4">
            Upload the <strong>Sales Extract</strong> (dispense transactions, pipe- or comma-delimited CSV) and,
            optionally, the <strong>Drug Usage report</strong> (patient names &amp; contact details, .xlsx).
            When both are supplied they are linked on <strong>Profile Code + Dependent Code</strong>, so patients
            are created with full contact details. Sales extract alone still imports dispense history.
        </p>

        <form wire:submit="import" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sales Extract (CSV) <span class="text-red-500">*</span></label>
                    <input type="file" wire:model="csvFile" accept=".csv,.txt" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-green-50 file:text-green-700 hover:file:bg-green-100" />
                    @error('csvFile') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Drug Usage report (XLSX) <span class="text-gray-400 text-xs">— optional</span></label>
                    <input type="file" wire:model="drugUsageFile" accept=".xlsx,.xls" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                    @error('drugUsageFile') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="import" class="px-6 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 transition">
                    <span wire:loading.remove wire:target="import,csvFile,drugUsageFile">Import</span>
                    <span wire:loading wire:target="import">Processing...</span>
                    <span wire:loading wire:target="csvFile,drugUsageFile">Uploading...</span>
                </button>
                <span class="text-xs text-gray-400" wire:loading.remove wire:target="csvFile,drugUsageFile">
                    Files containing patient data are deleted from the server immediately after processing.
                </span>
            </div>
        </form>
    </div>

    {{-- TEST-ONLY: reset all patients so the two import files can be re-run.
         Rendered ONLY for super-admins on non-production environments. --}}
    @if($this->canReset)
        <div class="bg-red-50 border border-red-200 rounded-xl p-5 mb-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-red-800 flex items-center gap-2">
                        <span class="inline-block px-2 py-0.5 rounded bg-red-600 text-white text-[11px] font-bold">TEST TOOL</span>
                        Reset all patients
                    </h3>
                    <p class="text-xs text-red-700 mt-1 max-w-2xl">
                        Deletes <strong>every</strong> imported patient plus their journeys, dispense records, orders,
                        consents and the import history — so you can re-upload the two files and watch patients load
                        under their pharmacies from scratch. Pharmacies, groups and staff logins are kept.
                        This is disabled in production.
                    </p>
                </div>
                <button type="button" wire:click="openResetModal"
                        class="shrink-0 px-4 py-2 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition">
                    Delete all patients
                </button>
            </div>
        </div>
    @endif

    <!-- Import History -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="p-5 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">Import History</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-3 font-medium text-gray-600">File</th>
                        <th class="text-left p-3 font-medium text-gray-600">Source</th>
                        <th class="text-center p-3 font-medium text-gray-600">Records</th>
                        <th class="text-center p-3 font-medium text-gray-600">Created</th>
                        <th class="text-center p-3 font-medium text-gray-600">Failed</th>
                        <th class="text-center p-3 font-medium text-gray-600">Status</th>
                        <th class="text-left p-3 font-medium text-gray-600">Date</th>
                        <th class="text-center p-3 font-medium text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($batches as $batch)
                        <tr class="hover:bg-gray-50">
                            <td class="p-3 font-medium text-gray-900">{{ $batch->filename }}</td>
                            <td class="p-3 text-gray-500">{{ ucfirst($batch->source) }}</td>
                            <td class="p-3 text-center">{{ $batch->records_total }}</td>
                            <td class="p-3 text-center text-green-600">{{ $batch->records_created }}</td>
                            <td class="p-3 text-center">
                                @if($batch->records_failed > 0)
                                    <span class="text-red-600">{{ $batch->records_failed }}</span>
                                @else
                                    <span class="text-gray-400">0</span>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    @if($batch->status === 'completed') bg-green-100 text-green-800
                                    @elseif($batch->status === 'failed') bg-red-100 text-red-800
                                    @elseif($batch->status === 'processing') bg-amber-100 text-amber-800
                                    @else bg-gray-100 text-gray-600
                                    @endif">
                                    {{ ucfirst($batch->status) }}
                                </span>
                            </td>
                            <td class="p-3 text-gray-500">{{ $batch->completed_at?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="p-3 text-center">
                                <button wire:click="viewBatch({{ $batch->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">View</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-8 text-center text-gray-500">No imports yet. Upload your first CSV above.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">
            {{ $batches->links() }}
        </div>
    </div>

    <!-- Batch Detail Modal -->
    @if($this->viewingBatch)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="closeBatch">
            <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full max-h-[80vh] overflow-y-auto">
                <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Import: {{ $this->viewingBatch->filename }}</h3>
                    <button wire:click="closeBatch" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-4 gap-4 mb-6">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-900">{{ $this->viewingBatch->records_total }}</p>
                            <p class="text-xs text-gray-500">Total</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-green-600">{{ $this->viewingBatch->records_created }}</p>
                            <p class="text-xs text-gray-500">Created</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-blue-600">{{ $this->viewingBatch->records_updated }}</p>
                            <p class="text-xs text-gray-500">Updated</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-red-600">{{ $this->viewingBatch->records_failed }}</p>
                            <p class="text-xs text-gray-500">Failed</p>
                        </div>
                    </div>

                    @if($this->viewingBatch->logs->where('status', 'failed')->count() > 0)
                        <h4 class="font-medium text-gray-900 mb-2">Errors</h4>
                        <div class="bg-red-50 rounded-lg p-3 space-y-2">
                            @foreach($this->viewingBatch->logs->where('status', 'failed')->take(20) as $log)
                                <div class="text-sm">
                                    <span class="text-red-700 font-medium">Row {{ $log->row_number }}:</span>
                                    <span class="text-red-600">{{ $log->error_message }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- TEST reset confirmation modal (typed "DELETE" gate). --}}
    @if($showResetModal && $this->canReset)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="closeResetModal">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
                <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-red-700">Delete ALL patients?</h3>
                    <button type="button" wire:click="closeResetModal" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>
                <div class="p-5 space-y-3">
                    <p class="text-sm text-gray-700">
                        This permanently removes every patient, journey, dispense record, order, consent and
                        import batch on this environment. It cannot be undone. Pharmacies, groups and staff are kept.
                    </p>
                    <label class="block text-sm font-medium text-gray-700">Type <span class="font-mono font-bold">DELETE</span> to confirm</label>
                    <input type="text" wire:model.live="resetConfirm" autocomplete="off"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-red-500 focus:border-red-500"
                           placeholder="DELETE" />
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeResetModal"
                                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancel</button>
                        <button type="button" wire:click="resetAllPatients"
                                wire:loading.attr="disabled" wire:target="resetAllPatients"
                                @disabled($resetConfirm !== 'DELETE')
                                class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            <span wire:loading.remove wire:target="resetAllPatients">Delete everything</span>
                            <span wire:loading wire:target="resetAllPatients">Deleting…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

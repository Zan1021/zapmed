<div>
    <x-slot name="header">Audit Log</x-slot>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
        <div class="text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Audit Log</h3>
            <p class="text-sm text-gray-500 max-w-md mx-auto">
                The audit log system tracks all significant actions on the platform — user logins, role changes, appointment modifications, and more.
            </p>
            <div class="mt-6 p-4 bg-amber-50 rounded-lg border border-amber-200 max-w-md mx-auto">
                <p class="text-sm text-amber-700">
                    <span class="font-medium">Coming soon.</span> Audit logging will be implemented with the security hardening phase before launch.
                </p>
            </div>
        </div>

        <!-- Preview of what audit log entries will look like -->
        <div class="mt-8 border-t border-gray-100 pt-6">
            <h4 class="text-sm font-medium text-gray-700 mb-4">Preview — entry format:</h4>
            <div class="space-y-3 opacity-60">
                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                    <div class="w-2 h-2 mt-2 bg-green-500 rounded-full flex-shrink-0"></div>
                    <div>
                        <p class="text-sm text-gray-700"><span class="font-medium">admin@zapmed.co.za</span> changed role of user #12 from Patient to Doctor</p>
                        <p class="text-xs text-gray-400">2 minutes ago &middot; IP 102.x.x.x</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                    <div class="w-2 h-2 mt-2 bg-blue-500 rounded-full flex-shrink-0"></div>
                    <div>
                        <p class="text-sm text-gray-700"><span class="font-medium">dr.smith@zapmed.co.za</span> completed consultation #ZAP-ABC123</p>
                        <p class="text-xs text-gray-400">15 minutes ago &middot; IP 102.x.x.x</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
                    <div class="w-2 h-2 mt-2 bg-amber-500 rounded-full flex-shrink-0"></div>
                    <div>
                        <p class="text-sm text-gray-700"><span class="font-medium">patient@gmail.com</span> failed login attempt (2FA expired)</p>
                        <p class="text-xs text-gray-400">1 hour ago &middot; IP 41.x.x.x</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

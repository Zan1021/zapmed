<div x-data="{ open: null }">
    <div class="space-y-3">
        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 1 ? null : 1" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">What is GLP-1 medication?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 1 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 1" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">GLP-1 receptor agonists (like semaglutide) are medications that mimic a natural hormone to reduce appetite, slow stomach emptying, and improve blood sugar regulation. They are clinically proven to help with significant weight loss when combined with lifestyle changes.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 2 ? null : 2" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">What does the Health Coach do?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 2 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 2" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Your Health Coach is a registered dietitian who provides ongoing support via WhatsApp. They help you with nutrition planning, lifestyle adjustments, accountability, and managing any side effects. This support is key to achieving lasting results.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 3 ? null : 3" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">Is the medication cost included in R450/month?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 3 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 3" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">No. The R450/month covers your doctor consultation, dietitian coaching, and programme management. Medication is billed separately based on what is prescribed and your current dosage. This allows us to keep the programme fee affordable while offering the right medication for your needs.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 4 ? null : 4" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">Can I cancel my subscription?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 4 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 4" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Yes, you can cancel at any time. There are no lock-in contracts. We recommend discussing with your doctor before stopping treatment to ensure a safe transition.</p>
            </div>
        </div>
    </div>
</div>

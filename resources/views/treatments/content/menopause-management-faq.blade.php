<div x-data="{ open: null }">
    <div class="space-y-3">
        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 1 ? null : 1" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">When does menopause start?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 1 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 1" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Most women reach menopause between ages 45-55, with the average being 51. Perimenopause (the transition phase) can start several years earlier with symptoms like irregular periods, hot flushes, and mood changes.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 2 ? null : 2" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">Is HRT safe?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 2 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 2" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">For most women under 60 or within 10 years of menopause, HRT is safe and effective. Our doctors assess your individual risk factors (family history, medical conditions) before prescribing. Modern HRT preparations have an excellent safety profile when used appropriately.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 3 ? null : 3" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">What if I can't use HRT?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 3 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 3" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Non-hormonal options are available for women who cannot or prefer not to use HRT. These include certain antidepressants (SSRIs/SNRIs) for hot flushes, gabapentin, cognitive behavioural therapy, and lifestyle interventions. Our doctors will discuss all options with you.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 4 ? null : 4" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">How soon will I feel better?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 4 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 4" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Most women notice improvement within 2-4 weeks of starting HRT, with full benefits at around 3 months. Your doctor will schedule a follow-up to assess progress and adjust treatment if needed.</p>
            </div>
        </div>
    </div>
</div>

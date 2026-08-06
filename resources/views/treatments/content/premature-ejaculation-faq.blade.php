<div x-data="{ open: null }">
    <div class="space-y-3">
        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 1 ? null : 1" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">What is premature ejaculation?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 1 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 1" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Premature ejaculation (PE) is when ejaculation happens sooner than you or your partner would like during sex. It's one of the most common sexual dysfunctions, affecting up to 1 in 3 men at some point.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 2 ? null : 2" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">How is PE treated?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 2 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 2" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">PE is typically treated with topical numbing creams that reduce sensitivity, and in some cases PDE5 inhibitors. Your doctor will assess your situation and recommend the most appropriate treatment plan.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 3 ? null : 3" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">Is treatment discreet?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 3 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 3" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Absolutely. Your entire consultation is private and confidential. Medication is delivered in unbranded packaging with no indication of the contents.</p>
            </div>
        </div>
    </div>
</div>

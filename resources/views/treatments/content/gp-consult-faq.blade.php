<div x-data="{ open: null }">
    <div class="space-y-3">
        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 1 ? null : 1" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">What can I consult about?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 1 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 1" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Our virtual GP consultations cover everyday health concerns including flu, allergies, skin issues, headaches, stomach problems, and more. If the doctor determines you need in-person care, they'll refer you appropriately.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 2 ? null : 2" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">How do I get my prescription?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 2 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 2" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">After your consultation, the doctor emails your e-prescription directly to you. You can fill it at any pharmacy, or opt for our delivery service to have medication sent to your door.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 3 ? null : 3" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">Can I get a sick note?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 3 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 3" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Yes, our doctors can issue sick notes and medical certificates as part of your consultation. These are legally valid and emailed to you after your appointment.</p>
            </div>
        </div>
    </div>
</div>

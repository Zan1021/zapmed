<div x-data="{ open: null }">
    <div class="space-y-3">
        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 1 ? null : 1" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">What is erectile dysfunction?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 1 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 1" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Erectile dysfunction (ED) is the inability to get or maintain an erection firm enough for sexual intercourse. It's a very common condition that affects men of all ages, though it becomes more prevalent with age. ED can be caused by physical factors (blood flow, hormones, nerve damage) or psychological factors (stress, anxiety, depression).</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 2 ? null : 2" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">What causes erectile dysfunction?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 2 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 2" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">ED can be caused by a range of factors including cardiovascular disease, diabetes, high blood pressure, high cholesterol, obesity, hormonal imbalances, certain medications, smoking, alcohol use, stress, anxiety, and depression. Often it's a combination of physical and psychological factors.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 3 ? null : 3" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">What are PDE5 inhibitors?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 3 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 3" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">PDE5 inhibitors are medications that work by increasing blood flow to the penis. They block the enzyme phosphodiesterase type 5, allowing the smooth muscle in blood vessels to relax. Common PDE5 inhibitors include sildenafil (Viagra), tadalafil (Cialis), and vardenafil (Levitra). They are the first-line treatment for ED and are highly effective.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 4 ? null : 4" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">What's the difference between event-based and chronic treatment?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 4 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 4" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Event-based treatment means you take a tablet before planned sexual activity (typically 30-60 minutes before). Chronic treatment involves taking a low-dose tablet daily, which means you're always ready for spontaneous activity. Your doctor will recommend the best approach based on your lifestyle and needs.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 5 ? null : 5" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">What's the difference between once-off and subscription?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 5 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 5" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Once-off orders are for patients who want a single order of medication. Subscription plans deliver your medication monthly with ongoing doctor support, automatic refills, and a lower consultation cost. Subscriptions can be cancelled at any time.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 6 ? null : 6" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">Can I claim from medical aid?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 6 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 6" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">Yes! If you have a medical aid, your consultation and medication may be covered depending on your plan and available benefits. We provide all the necessary documentation for you to submit a claim. Medical aid pricing starts from R450 for once-off and R220/month for subscriptions.</p>
            </div>
        </div>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <button @click="open = open === 7 ? null : 7" class="w-full flex items-center justify-between p-5 text-left">
                <span class="text-sm font-semibold text-gray-900">How is my medication delivered?</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 7 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open === 7" x-collapse class="px-5 pb-5">
                <p class="text-sm text-gray-600">All medication is delivered in discreet, unbranded packaging directly to your door. There's no indication of the contents on the outside. Delivery typically takes 2-5 working days depending on your location. Subscription patients enjoy free delivery nationwide.</p>
            </div>
        </div>
    </div>
</div>

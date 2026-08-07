<!-- AI Health Assistant Floating Widget -->
<div x-data="aiChatWidget()" class="fixed bottom-6 right-6 z-50">
    <!-- Chat Panel (slides up from bottom-right) -->
    <div x-show="open" x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
         class="mb-4 w-[380px] max-w-[calc(100vw-3rem)] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden"
         @click.outside="open = false">

        <!-- Header -->
        <div class="bg-gradient-to-r from-zapmed-600 to-zapmed-700 p-4 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg overflow-hidden">
                    <img src="/images/ai-doctor-avatar.svg" alt="AI Doctor" class="w-8 h-8">
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">AI Health Assistant</p>
                    <p class="text-xs text-zapmed-100">Ask me about treatments</p>
                </div>
            </div>
            <button @click="open = false" class="text-white/70 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Messages Area -->
        <div class="h-[320px] overflow-y-auto p-4 space-y-3" id="chat-messages">
            <!-- Welcome message -->
            <div class="flex items-start space-x-2">
                <div class="w-7 h-7 rounded-full flex-shrink-0 overflow-hidden">
                    <img src="/images/ai-doctor-avatar.svg" alt="AI" class="w-7 h-7">
                </div>
                <div class="bg-gray-50 rounded-xl rounded-tl-sm px-3 py-2 max-w-[85%]">
                    <p class="text-sm text-gray-700">Hi! I'm Zapmed's AI assistant. I can help you find the right treatment. What's on your mind?</p>
                </div>
            </div>

            <!-- Dynamic messages -->
            <template x-for="(msg, i) in messages" :key="i">
                <div>
                    <!-- User message -->
                    <template x-if="msg.role === 'user'">
                        <div class="flex justify-end">
                            <div class="bg-zapmed-600 text-white rounded-xl rounded-tr-sm px-3 py-2 max-w-[85%]">
                                <p class="text-sm" x-text="msg.text"></p>
                            </div>
                        </div>
                    </template>

                    <!-- AI response -->
                    <template x-if="msg.role === 'ai'">
                        <div class="flex items-start space-x-2">
                            <div class="w-7 h-7 rounded-full flex-shrink-0 overflow-hidden">
                                <img src="/images/ai-doctor-avatar.svg" alt="AI" class="w-7 h-7">
                            </div>
                            <div class="max-w-[85%]">
                                <div class="bg-gray-50 rounded-xl rounded-tl-sm px-3 py-2">
                                    <p class="text-sm text-gray-700 whitespace-pre-line" x-text="msg.text"></p>
                                </div>
                                <template x-if="msg.treatmentUrl">
                                    <a :href="msg.treatmentUrl" class="mt-2 inline-flex items-center px-3 py-1.5 bg-zapmed-600 hover:bg-zapmed-700 text-white text-xs font-medium rounded-lg transition-colors">
                                        <span x-text="'View ' + msg.treatmentName + ' →'"></span>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Loading -->
            <div x-show="loading" class="flex items-start space-x-2">
                <div class="w-7 h-7 rounded-full flex-shrink-0 overflow-hidden">
                    <img src="/images/ai-doctor-avatar.svg" alt="AI" class="w-7 h-7">
                </div>
                <div class="bg-gray-50 rounded-xl rounded-tl-sm px-4 py-3">
                    <div class="flex space-x-1">
                        <div class="w-2 h-2 bg-gray-300 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                        <div class="w-2 h-2 bg-gray-300 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                        <div class="w-2 h-2 bg-gray-300 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input -->
        <form @submit.prevent="sendMessage" class="p-3 border-t border-gray-100 flex items-center space-x-2">
            <input x-model="message" type="text"
                class="flex-1 rounded-xl border-gray-200 text-sm focus:border-zapmed-500 focus:ring-zapmed-500 py-2.5"
                placeholder="Type your health question..."
                :disabled="loading" maxlength="500" x-ref="chatInput">
            <button type="submit" :disabled="loading || !message.trim()"
                class="flex-shrink-0 w-9 h-9 bg-zapmed-600 hover:bg-zapmed-700 disabled:bg-gray-300 text-white rounded-xl flex items-center justify-center transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </button>
        </form>

        <p class="px-4 pb-2 text-[10px] text-gray-400 text-center">Not a substitute for medical advice</p>
    </div>

    <!-- Floating Button -->
    <div class="flex items-center space-x-3">
        <span x-show="!open" x-transition class="bg-white text-gray-700 text-sm font-medium px-4 py-2 rounded-full shadow-lg border border-gray-100 hidden sm:block">
            How can we assist?
        </span>
        <button @click="open = !open"
            class="w-14 h-14 bg-zapmed-600 hover:bg-zapmed-700 text-white rounded-full shadow-lg hover:shadow-xl flex items-center justify-center transition-all duration-200 hover:scale-105"
            :class="open ? '' : 'animate-bounce-subtle'">
            <svg x-show="!open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <svg x-show="open" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>

<script>
function aiChatWidget() {
    return {
        open: false,
        message: '',
        messages: [],
        loading: false,

        async sendMessage() {
            if (!this.message.trim() || this.loading) return;

            const userMsg = this.message.trim();
            this.messages.push({ role: 'user', text: userMsg });
            this.message = '';
            this.loading = true;

            this.$nextTick(() => this.scrollToBottom());

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const res = await fetch('/api/ai-assistant', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || '',
                    },
                    body: JSON.stringify({ message: userMsg }),
                });

                const data = await res.json();
                this.messages.push({
                    role: 'ai',
                    text: data.response,
                    treatmentUrl: data.treatment_url || null,
                    treatmentName: data.treatment_name || '',
                });
            } catch (err) {
                this.messages.push({
                    role: 'ai',
                    text: 'Sorry, something went wrong. Please try again.',
                    treatmentUrl: null,
                    treatmentName: '',
                });
            } finally {
                this.loading = false;
                this.$nextTick(() => {
                    this.scrollToBottom();
                    this.$refs.chatInput?.focus();
                });
            }
        },

        scrollToBottom() {
            const el = document.getElementById('chat-messages');
            if (el) el.scrollTop = el.scrollHeight;
        }
    };
}
</script>

<style>
@keyframes bounce-subtle {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-4px); }
}
.animate-bounce-subtle {
    animation: bounce-subtle 2s ease-in-out infinite;
}
</style>

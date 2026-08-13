<!-- POPIA Cookie Consent Banner -->
<div x-data="consentBanner()" x-show="show" x-transition x-cloak
     class="fixed bottom-0 inset-x-0 z-50 p-4 sm:p-0">
    <div class="max-w-4xl mx-auto sm:mb-4">
        <div class="bg-white rounded-xl shadow-2xl border border-gray-200 p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <div class="flex-1">
                    <p class="text-sm font-semibold text-gray-900 mb-1">Your Privacy Matters</p>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        We use cookies and process personal data in accordance with the Protection of Personal Information Act (POPIA).
                        By continuing, you consent to our use of cookies for essential functionality, analytics, and personalised care.
                        <a href="/privacy-policy" class="text-zapmed-600 hover:text-zapmed-700 underline">Privacy Policy</a> ·
                        <a href="/cookie-policy" class="text-zapmed-600 hover:text-zapmed-700 underline">Cookie Policy</a>
                    </p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button @click="acceptAll()" class="px-4 py-2 bg-zapmed-600 hover:bg-zapmed-700 text-white text-xs font-semibold rounded-lg transition-colors">
                        Accept All
                    </button>
                    <button @click="acceptEssential()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition-colors">
                        Essential Only
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function consentBanner() {
    return {
        show: false,
        init() {
            // Only show if no consent cookie exists
            this.show = !this.getCookie('zapmed_consent');
        },
        acceptAll() {
            this.setCookie('zapmed_consent', 'all', 365);
            this.show = false;
            // Enable analytics, marketing cookies here
        },
        acceptEssential() {
            this.setCookie('zapmed_consent', 'essential', 365);
            this.show = false;
            // Only essential cookies (session, CSRF)
        },
        setCookie(name, value, days) {
            const d = new Date();
            d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + '=' + value + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax;Secure';
        },
        getCookie(name) {
            const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
            return v ? v[2] : null;
        }
    }
}
</script>

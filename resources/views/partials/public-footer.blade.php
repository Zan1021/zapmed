<!-- Footer -->
<footer class="border-t border-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <!-- Newsletter Signup -->
        <div class="mb-10 p-6 bg-zapmed-50 rounded-xl border border-zapmed-100 flex flex-col md:flex-row items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-gray-900">Stay in the loop</h3>
                <p class="text-xs text-gray-500 mt-0.5">Health tips, new treatments, and exclusive offers — straight to your inbox.</p>
            </div>
            <div class="w-full md:w-auto md:min-w-[320px]">
                <livewire:newsletter-subscribe />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
                <a href="/" class="text-xl font-bold text-gray-900 tracking-tight">zapmed<span class="text-zapmed-500">.</span></a>
                <p class="mt-3 text-sm text-gray-500">Online doctor-guided medical treatments. Prescribed by licensed SA doctors.</p>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Services</h4>
                <ul class="space-y-2 text-sm text-gray-500">
                    <li><a href="{{ route('treatments.show', 'weight-loss') }}" class="hover:text-gray-900 transition-colors">Weight Loss</a></li>
                    <li><a href="{{ route('treatments.show', 'acne-treatment') }}" class="hover:text-gray-900 transition-colors">Skincare</a></li>
                    <li><a href="{{ route('treatments.show', 'erectile-dysfunction-treatment') }}" class="hover:text-gray-900 transition-colors">Sexual Health</a></li>
                    <li><a href="{{ route('treatments.show', 'gp-consult') }}" class="hover:text-gray-900 transition-colors">General Health</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Company</h4>
                <ul class="space-y-2 text-sm text-gray-500">
                    <li><a href="{{ route('faq') }}" class="hover:text-gray-900 transition-colors">FAQ / Help Centre</a></li>
                    <li><a href="/#doctors" class="hover:text-gray-900 transition-colors">Our Doctors</a></li>
                    <li><a href="/#how-it-works" class="hover:text-gray-900 transition-colors">How It Works</a></li>
                    <li><a href="{{ route('doctors.apply') }}" class="hover:text-gray-900 transition-colors">Join as a Doctor</a></li>
                    <li><a href="mailto:support@zapmed.co.za" class="hover:text-gray-900 transition-colors">Contact</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Legal</h4>
                <ul class="space-y-2 text-sm text-gray-500">
                    <li><a href="/terms" class="hover:text-gray-900 transition-colors">Terms of Service</a></li>
                    <li><a href="/privacy-policy" class="hover:text-gray-900 transition-colors">Privacy Policy</a></li>
                    <li><a href="/cookie-policy" class="hover:text-gray-900 transition-colors">Cookie Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-8 pt-8 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm text-gray-400">&copy; {{ date('Y') }} Zapmed (Pty) Ltd. All rights reserved. Prescribed by HPCSA-registered doctors. Dispensed by regulated pharmacy partners.</p>
            <div class="flex items-center gap-4">
                <!-- Social Links -->
                <div class="flex items-center gap-3">
                    <a href="https://www.facebook.com/zapmedofficial/" target="_blank" rel="noopener" class="text-gray-400 hover:text-zapmed-600 transition-colors" title="Facebook">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="https://www.instagram.com/zapmed_sa/" target="_blank" rel="noopener" class="text-gray-400 hover:text-zapmed-600 transition-colors" title="Instagram">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C16.67.014 16.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                    <a href="https://www.linkedin.com/company/zapmed/" target="_blank" rel="noopener" class="text-gray-400 hover:text-zapmed-600 transition-colors" title="LinkedIn">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    </a>
                </div>
                <!-- App Store Buttons -->
                <div class="flex items-center gap-2">
                <a href="#" class="inline-flex items-center bg-gray-900 hover:bg-gray-800 text-white px-3 py-2 rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
                    <span class="text-xs font-medium">App Store</span>
                </a>
                <a href="#" class="inline-flex items-center bg-gray-900 hover:bg-gray-800 text-white px-3 py-2 rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="currentColor"><path d="M3.609 1.814L13.792 12 3.61 22.186a.996.996 0 01-.61-.92V2.734a1 1 0 01.609-.92zm10.89 10.893l2.302 2.302-10.937 6.333 8.635-8.635zm3.199-3.198l2.807 1.626a1 1 0 010 1.73l-2.808 1.626L15.206 12l2.492-2.491zM5.864 2.658L16.8 8.99l-2.302 2.302-8.634-8.634z"/></svg>
                    <span class="text-xs font-medium">Google Play</span>
                </a>
                </div>
            </div>
        </div>
    </div>
</footer>

@include('partials.consent-banner')
@include('partials.ai-chat-widget')

<!-- Footer -->
<footer class="border-t border-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
                <a href="/" class="text-xl font-bold text-gray-900 tracking-tight">zapmed<span class="text-zapmed-500">.</span></a>
                <p class="mt-3 text-sm text-gray-500">Online doctor-guided medical treatments. Prescribed by licensed SA doctors.</p>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Services</h4>
                <ul class="space-y-2 text-sm text-gray-500">
                    <li><a href="{{ route('treatments.show', 'weight-loss') }}" class="hover:text-gray-900 transition-colors">Weight Loss</a></li>
                    <li><a href="{{ route('treatments.show', 'acne') }}" class="hover:text-gray-900 transition-colors">Skincare</a></li>
                    <li><a href="{{ route('treatments.show', 'erectile-dysfunction') }}" class="hover:text-gray-900 transition-colors">Sexual Health</a></li>
                    <li><a href="{{ route('treatments.show', 'gp-consult') }}" class="hover:text-gray-900 transition-colors">General Health</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Company</h4>
                <ul class="space-y-2 text-sm text-gray-500">
                    <li><a href="#" class="hover:text-gray-900 transition-colors">About Us</a></li>
                    <li><a href="/#doctors" class="hover:text-gray-900 transition-colors">Our Doctors</a></li>
                    <li><a href="#" class="hover:text-gray-900 transition-colors">Blog</a></li>
                    <li><a href="#" class="hover:text-gray-900 transition-colors">Contact</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Legal</h4>
                <ul class="space-y-2 text-sm text-gray-500">
                    <li><a href="#" class="hover:text-gray-900 transition-colors">Terms of Service</a></li>
                    <li><a href="#" class="hover:text-gray-900 transition-colors">Privacy Policy</a></li>
                    <li><a href="#" class="hover:text-gray-900 transition-colors">POPIA Compliance</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-8 pt-8 border-t border-gray-100 text-center">
            <p class="text-sm text-gray-400">&copy; {{ date('Y') }} Zapmed (Pty) Ltd. All rights reserved. Prescribed by HPCSA-registered doctors. Dispensed by regulated pharmacy partners.</p>
        </div>
    </div>
</footer>

<div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="/" class="inline-block mb-4">
                <span class="text-3xl font-bold text-gray-900 tracking-tight">zapmed<span class="text-zapmed-500">.</span></span>
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Join Zapmed as a Doctor</h1>
            <p class="mt-2 text-gray-600">Apply to provide telehealth consultations on our platform. We'll review your application within 48 hours.</p>
        </div>

        @if($submitted)
            <!-- Success State -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                <div class="w-16 h-16 bg-zapmed-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-zapmed-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Application Submitted!</h2>
                <p class="text-gray-600 mb-6">Thank you, Dr. {{ $first_name }}. We've received your application and will review it within 48 hours. You'll receive an email at <strong>{{ $email }}</strong> once we've made a decision.</p>
                <a href="/" class="inline-flex items-center px-6 py-3 bg-zapmed-600 hover:bg-zapmed-700 text-white font-semibold rounded-lg transition-colors">
                    Return to Homepage
                </a>
            </div>
        @else
            <!-- Application Form -->
            <form wire:submit="submit" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 space-y-6">
                <!-- Personal Details -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-100">Personal Details</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                            <input type="text" wire:model="first_name" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            @error('first_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                            <input type="text" wire:model="last_name" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            @error('last_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" wire:model="email" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            @error('email') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                            <input type="tel" wire:model="phone" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            @error('phone') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- Professional Details -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-100">Professional Details</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">HPCSA Practice Number *</label>
                            <input type="text" wire:model="hpcsa_number" placeholder="MP 0XXXXXX" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            @error('hpcsa_number') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Speciality *</label>
                            <select wire:model="speciality" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                                <option value="General Practitioner">General Practitioner</option>
                                <option value="Dermatologist">Dermatologist</option>
                                <option value="Psychiatrist">Psychiatrist</option>
                                <option value="Gynaecologist">Gynaecologist</option>
                                <option value="Urologist">Urologist</option>
                                <option value="Endocrinologist">Endocrinologist</option>
                                <option value="Internal Medicine">Internal Medicine</option>
                                <option value="Other">Other</option>
                            </select>
                            @error('speciality') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Qualification *</label>
                            <input type="text" wire:model="qualification" placeholder="e.g. MBChB (UCT)" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            @error('qualification') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Years of Experience *</label>
                            <input type="number" wire:model="years_experience" min="0" max="60" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm">
                            @error('years_experience') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- Engagement Type -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-100">How would you like to work?</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="relative flex items-start p-4 border rounded-xl cursor-pointer transition-colors" :class="$wire.doctor_type === 'full_time' ? 'border-zapmed-500 bg-zapmed-50' : 'border-gray-200 hover:border-gray-300'">
                            <input type="radio" wire:model="doctor_type" value="full_time" class="mt-0.5 text-zapmed-600 focus:ring-zapmed-500">
                            <div class="ml-3">
                                <p class="text-sm font-semibold text-gray-900">Full-Time</p>
                                <p class="text-xs text-gray-500 mt-0.5">Dedicated schedule, consistent hours</p>
                            </div>
                        </label>
                        <label class="relative flex items-start p-4 border rounded-xl cursor-pointer transition-colors" :class="$wire.doctor_type === 'locum' ? 'border-zapmed-500 bg-zapmed-50' : 'border-gray-200 hover:border-gray-300'">
                            <input type="radio" wire:model="doctor_type" value="locum" class="mt-0.5 text-zapmed-600 focus:ring-zapmed-500">
                            <div class="ml-3">
                                <p class="text-sm font-semibold text-gray-900">Part-Time (Locum)</p>
                                <p class="text-xs text-gray-500 mt-0.5">Flexible hours, set your own availability</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Motivation -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Why do you want to join Zapmed? (optional)</label>
                    <textarea wire:model="motivation" rows="3" class="w-full rounded-lg border-gray-300 focus:border-zapmed-500 focus:ring-zapmed-500 text-sm" placeholder="Tell us about your experience with telehealth, availability, or anything else..."></textarea>
                    @error('motivation') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Document Uploads -->
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-100">Documents</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">HPCSA Certificate *</label>
                            <input type="file" wire:model="hpcsa_certificate" accept=".pdf,.jpg,.jpeg,.png,.webp" class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-zapmed-50 file:text-zapmed-700 hover:file:bg-zapmed-100">
                            <p class="text-xs text-gray-400 mt-1">PDF, JPG, PNG — max 5MB</p>
                            @error('hpcsa_certificate') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ID Document *</label>
                            <input type="file" wire:model="id_document" accept=".pdf,.jpg,.jpeg,.png,.webp" class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-zapmed-50 file:text-zapmed-700 hover:file:bg-zapmed-100">
                            <p class="text-xs text-gray-400 mt-1">PDF, JPG, PNG — max 5MB</p>
                            @error('id_document') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <!-- Terms -->
                <div class="flex items-start">
                    <input type="checkbox" wire:model="agree_terms" class="mt-1 rounded text-zapmed-600 focus:ring-zapmed-500">
                    <label class="ml-2 text-sm text-gray-600">I confirm that the information provided is accurate and I agree to Zapmed's <a href="/terms" class="text-zapmed-600 hover:underline">Terms of Service</a> and <a href="/privacy-policy" class="text-zapmed-600 hover:underline">Privacy Policy</a>.</label>
                </div>
                @error('agree_terms') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

                <!-- Submit -->
                <div class="pt-4">
                    <button type="submit" wire:loading.attr="disabled" class="w-full bg-zapmed-600 hover:bg-zapmed-700 disabled:bg-gray-300 text-white py-3 px-6 rounded-xl font-semibold transition-colors shadow-sm">
                        <span wire:loading.remove>Submit Application</span>
                        <span wire:loading>Submitting...</span>
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>

<div>
    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input wire:model="email" id="email" type="email" autocomplete="username" required
                class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input wire:model="password" id="password" type="password" autocomplete="current-password" required
                class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-green-600 focus:ring-green-600">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-600">
            <input wire:model="remember" type="checkbox" class="rounded border-gray-300"> Remember me
        </label>

        <button type="submit"
            class="w-full rounded px-4 py-2 font-medium text-white"
            style="background: {{ config('spar.branding.primary_color', '#006B3F') }};">
            Sign in
        </button>
    </form>
</div>

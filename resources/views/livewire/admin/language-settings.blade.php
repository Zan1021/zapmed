<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Language Settings</h1>
        <p class="text-sm text-gray-500 mt-1">Enable or disable languages. Enabled languages appear in the language picker for patients.</p>
    </div>

    @if(session('message'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('message') }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase">Language</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Native Name</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Translation File</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-5 py-3 text-center text-xs font-medium text-gray-500 uppercase">Default</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($this->availableLanguages as $code => $lang)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <span class="text-lg">{{ $lang['flag'] }}</span>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $lang['name'] }}</p>
                                    <p class="text-xs text-gray-400">{{ $code }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-center text-sm text-gray-700">{{ $lang['native'] }}</td>
                        <td class="px-5 py-4 text-center">
                            @if($this->hasTranslationFile($code))
                                <span class="inline-flex items-center text-xs text-green-600 font-medium">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    Ready
                                </span>
                            @else
                                <span class="text-xs text-gray-400">Not created</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-center">
                            @if(in_array($code, $enabledLanguages))
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Enabled</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Disabled</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-center">
                            @if($defaultLanguage === $code)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-zapmed-100 text-zapmed-700">Default</span>
                            @elseif(in_array($code, $enabledLanguages))
                                <button wire:click="setDefault('{{ $code }}')" class="text-xs text-gray-400 hover:text-zapmed-600">Set as default</button>
                            @else
                                <span class="text-xs text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right">
                            @if($code === 'en')
                                <span class="text-xs text-gray-400">Always on</span>
                            @else
                                <button wire:click="toggleLanguage('{{ $code }}')"
                                    class="text-xs font-medium px-3 py-1.5 rounded-lg transition-colors {{ in_array($code, $enabledLanguages) ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-green-50 text-green-600 hover:bg-green-100' }}">
                                    {{ in_array($code, $enabledLanguages) ? 'Disable' : 'Enable' }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6 bg-blue-50 border border-blue-100 rounded-xl p-5">
        <h3 class="text-sm font-semibold text-blue-900 mb-2">Adding a New Language</h3>
        <ol class="text-sm text-blue-700 space-y-1 list-decimal list-inside">
            <li>Add the language to <code class="bg-blue-100 px-1 rounded">config/languages.php</code></li>
            <li>Create a translation file at <code class="bg-blue-100 px-1 rounded">lang/{code}.json</code></li>
            <li>Enable it here in Language Settings</li>
            <li>The language picker will automatically show it to users</li>
        </ol>
    </div>
</div>

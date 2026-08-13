<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use Livewire\Component;

class LanguageSettings extends Component
{
    public array $enabledLanguages = [];
    public string $defaultLanguage = 'en';

    public function mount(): void
    {
        $this->enabledLanguages = Setting::enabledLanguages();
        $this->defaultLanguage = Setting::get('default_language', 'en');
    }

    /**
     * Toggle a language on/off.
     */
    public function toggleLanguage(string $code): void
    {
        if ($code === 'en') {
            // English cannot be disabled (it's the fallback)
            return;
        }

        if (in_array($code, $this->enabledLanguages)) {
            $this->enabledLanguages = array_values(array_diff($this->enabledLanguages, [$code]));

            // If disabling the current default, reset to English
            if ($this->defaultLanguage === $code) {
                $this->defaultLanguage = 'en';
                Setting::set('default_language', 'en');
            }
        } else {
            $this->enabledLanguages[] = $code;
        }

        Setting::set('enabled_languages', $this->enabledLanguages);
        session()->flash('message', 'Language settings updated.');
    }

    /**
     * Set the default language.
     */
    public function setDefault(string $code): void
    {
        if (!in_array($code, $this->enabledLanguages)) {
            return;
        }

        $this->defaultLanguage = $code;
        Setting::set('default_language', $code);
        session()->flash('message', "Default language set to {$code}.");
    }

    public function getAvailableLanguagesProperty(): array
    {
        return config('languages.available', []);
    }

    /**
     * Check if a translation file exists for a language.
     */
    public function hasTranslationFile(string $code): bool
    {
        return file_exists(lang_path("{$code}.json"));
    }

    public function render()
    {
        return view('livewire.admin.language-settings')
            ->layout('layouts.app');
    }
}

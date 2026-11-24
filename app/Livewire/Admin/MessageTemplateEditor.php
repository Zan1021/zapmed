<?php

namespace App\Livewire\Admin;

use App\Models\MessageTemplate;
use App\Services\Operations\MessageTemplates;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/** Message wording and on/off switches (Craig's guide 6.4): reword a message, switch it off, go back to the built-in text. */
class MessageTemplateEditor extends Component
{
    public ?string $editing = null;
    public string $subject = '';
    public string $body = '';
    public bool $enabled = true;
    public bool $saved = false;

    public function edit(string $key, MessageTemplates $templates): void
    {
        $def = $templates->registry()[$key] ?? abort(404);
        $row = MessageTemplate::where('key', $key)->first();

        $this->resetErrorBag();
        $this->saved = false;
        $this->editing = $key;
        $this->subject = $row?->subject ?? $def['subject'];
        $this->body = $row?->body ?? $def['body'];
        $this->enabled = $row?->is_enabled ?? true;
    }

    public function close(): void
    {
        $this->reset(['editing', 'subject', 'body', 'saved']);
        $this->enabled = true;
        $this->resetErrorBag();
    }

    public function save(MessageTemplates $templates): void
    {
        $this->resetErrorBag();
        abort_unless($this->editing, 422);

        $templates->save(Auth::user(), $this->editing, $this->subject, $this->body, $this->enabled);
        $this->saved = true;
    }

    public function reset_(MessageTemplates $templates): void
    {
        $this->resetErrorBag();
        abort_unless($this->editing, 422);

        $templates->reset(Auth::user(), $this->editing);
        $this->edit($this->editing, $templates);
        $this->saved = true;
    }

    public function render(MessageTemplates $templates)
    {
        $custom = MessageTemplate::pluck('is_enabled', 'key');

        return view('livewire.admin.message-template-editor', [
            'registry' => $templates->registry(),
            'custom' => $custom,
            'def' => $this->editing ? $templates->registry()[$this->editing] : null,
            'history' => $this->editing
                ? DB::table('message_template_versions')->where('template_key', $this->editing)->orderByDesc('version')->limit(10)->get()
                : collect(),
        ])->layout('layouts.app');
    }
}

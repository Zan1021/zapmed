<?php

namespace App\Livewire\Admin;

use App\Models\CrmAuditEvent;
use App\Services\Compliance\AuditTrail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The Audit Log (Operations only). Two lists:
 *  - Platform events: what people changed (coupons, orders, consents, flags ...). Tamper-evident: each row is chained to the one before.
 *  - Record access: who opened medical records (consultations, prescriptions) and from which address.
 * Read only. Times are shown in South African time.
 */
class AuditLog extends Component
{
    use WithPagination;

    public string $source = 'platform';   // platform | access
    public string $domain = '';
    public string $search = '';
    public string $from = '';
    public string $to = '';
    public ?int $open = null;             // the platform event whose details are showing
    public ?string $chainResult = null;

    public function updated(string $name): void
    {
        if (in_array($name, ['source', 'domain', 'search', 'from', 'to'], true)) {
            $this->resetPage();
            $this->open = null;
        }
    }

    public function toggle(int $id): void
    {
        $this->open = $this->open === $id ? null : $id;
    }

    /** Check that nobody changed the platform history after it was written. */
    public function verifyChain(AuditTrail $trail): void
    {
        $broken = $trail->verifyChain();
        $this->chainResult = $broken === null ? 'The platform history is intact: no row has been changed.' : 'Problem: event #'.$broken.' does not match the one before it. Tell the developer.';
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} the first and last moment of the chosen days, in UTC */
    private function range(): array
    {
        $tz = config('doctor.time.timezone');
        $parse = function (string $v, bool $end) use ($tz) {
            try {
                $d = CarbonImmutable::parse($v, $tz);

                return ($end ? $d->endOfDay() : $d->startOfDay())->utc();
            } catch (\Throwable) {
                return null;
            }
        };

        return [$this->from !== '' ? $parse($this->from, false) : null, $this->to !== '' ? $parse($this->to, true) : null];
    }

    public function render()
    {
        [$from, $to] = $this->range();
        $term = '%'.mb_strtolower(trim($this->search)).'%';

        if ($this->source === 'access') {
            $rows = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
                ->select('audit_logs.*', 'users.first_name', 'users.last_name', 'users.email')
                ->when($from, fn ($q) => $q->where('audit_logs.created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('audit_logs.created_at', '<=', $to))
                ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->whereRaw('lower(audit_logs.description) like ?', [$term])->orWhereRaw('lower(audit_logs.action) like ?', [$term])
                    ->orWhereRaw('lower(users.email) like ?', [$term])))
                ->orderByDesc('audit_logs.id')->paginate(25);
            $events = null;
        } else {
            $events = CrmAuditEvent::with('actor:id,first_name,last_name,email')
                ->when($this->domain !== '', fn ($q) => $q->where('domain', $this->domain))
                ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('occurred_at', '<=', $to))
                ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->whereRaw('lower(action) like ?', [$term])->orWhereRaw('lower(subject_id) like ?', [$term])->orWhereRaw('lower(coalesce(actor_label, \'\')) like ?', [$term])))
                ->orderByDesc('id')->paginate(25);
            $rows = null;
        }

        return view('livewire.admin.audit-log', [
            'events' => $events, 'rows' => $rows,
            'domains' => CrmAuditEvent::query()->distinct()->orderBy('domain')->pluck('domain'),
            'tz' => config('doctor.time.timezone'),
        ])->layout('layouts.app');
    }
}
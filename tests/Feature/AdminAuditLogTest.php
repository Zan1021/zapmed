<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\AuditLog;
use App\Models\CrmAuditEvent;
use App\Models\User;
use App\Services\Compliance\AuditTrail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** The Audit Log screen: read only, Operations only, two lists with filters. */
class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-25 11:00', 'Africa/Johannesburg'));
        $this->ops = User::factory()->create(['role' => UserRole::Admin, 'first_name' => 'Olga', 'last_name' => 'Ops']);
    }

    private function event(string $domain, string $action, string $subject = '1', ?array $after = null, ?CarbonImmutable $at = null): void
    {
        app(AuditTrail::class)->record($domain, $action, 'coupon', $subject, null, $after, $this->ops->id);
        if ($at) {
            DB::table('crm_audit_events')->where('id', DB::table('crm_audit_events')->max('id'))->update(['occurred_at' => $at->utc()]);
        }
    }

    private function access(string $description, ?int $userId = null, ?CarbonImmutable $at = null): void
    {
        DB::table('audit_logs')->insert(['user_id' => $userId, 'action' => 'medical_data_access', 'description' => $description, 'ip_address' => '10.0.0.5', 'created_at' => ($at ?? now())->utc(), 'updated_at' => now()]);
    }

    public function test_only_operations_can_open_it(): void
    {
        $this->actingAs($this->ops)->get(route('admin.audit-log'))->assertOk()->assertSee('Audit Log');
        foreach ([UserRole::Doctor, UserRole::Patient, UserRole::HealthCoach] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.audit-log'))->assertForbidden();
        }
    }

    public function test_platform_events_are_listed_newest_first_with_who_and_when_in_south_african_time(): void
    {
        $this->event('coupon', 'created', 'A', ['code' => 'ONE']);
        $this->event('order', 'refund', 'B', null);
        $this->actingAs($this->ops);

        $html = Livewire::test(AuditLog::class)->assertSeeHtml('data-list="platform"')->assertSee('Olga Ops')->assertSee('25 Nov 2026 11:00')->html();

        $this->assertLessThan(strpos($html, 'created'), strpos($html, 'refund'), 'Newest first.');
    }

    public function test_filter_by_area_search_and_dates(): void
    {
        $this->event('coupon', 'created', 'A1', null, CarbonImmutable::parse('2026-11-10 10:00', 'Africa/Johannesburg'));
        $this->event('order', 'refund', 'B2', null, CarbonImmutable::parse('2026-11-20 10:00', 'Africa/Johannesburg'));
        $this->event('coupon', 'cancelled', 'C3', null, CarbonImmutable::parse('2026-11-24 10:00', 'Africa/Johannesburg'));
        $this->actingAs($this->ops);

        Livewire::test(AuditLog::class)->set('domain', 'coupon')->assertSee('created')->assertSee('cancelled')->assertDontSee('refund');
        Livewire::test(AuditLog::class)->set('search', 'REFUND')->assertSee('refund')->assertDontSee('cancelled');
        Livewire::test(AuditLog::class)->set('search', 'c3')->assertSee('cancelled')->assertDontSee('refund');
        Livewire::test(AuditLog::class)->set('from', '2026-11-15')->set('to', '2026-11-21')->assertSee('refund')->assertDontSee('cancelled')->assertDontSee('created');
        Livewire::test(AuditLog::class)->set('search', 'nothing like this')->assertSeeHtml('data-empty="audit"');
    }

    public function test_details_open_and_close_and_are_shortened(): void
    {
        $this->event('coupon', 'created', 'A', ['code' => 'DETAIL7', 'filler' => str_repeat('x', 4000)]);
        $id = CrmAuditEvent::firstOrFail()->id;
        $this->actingAs($this->ops);

        $c = Livewire::test(AuditLog::class)->assertDontSeeHtml('data-details')->call('toggle', $id)->assertSeeHtml('data-details="'.$id.'"')->assertSee('DETAIL7');
        $this->assertStringNotContainsString(str_repeat('x', 1600), $c->html(), 'The page does not carry the whole 4000 character value.');
        $c->call('toggle', $id)->assertDontSeeHtml('data-details');
    }

    public function test_the_access_list_shows_who_opened_records(): void
    {
        $doctor = User::factory()->create(['role' => UserRole::Doctor, 'first_name' => 'Dina', 'last_name' => 'Doc']);
        $this->access('doctor accessed doctor/consultation/5', $doctor->id);
        $this->access('guest page', null);
        $this->actingAs($this->ops);

        Livewire::test(AuditLog::class)->set('source', 'access')->assertSeeHtml('data-list="access"')
            ->assertSee('Dina Doc')->assertSee('doctor accessed doctor/consultation/5')->assertSee('10.0.0.5')->assertSee('Nobody signed in')
            ->set('search', 'consultation')->assertSee('Dina Doc')->assertDontSee('guest page');
    }

    public function test_the_history_check_says_intact_and_then_names_a_changed_row(): void
    {
        $this->event('coupon', 'created', 'A', ['code' => 'ONE']);
        $this->event('coupon', 'cancelled', 'A', null);
        $this->actingAs($this->ops);

        Livewire::test(AuditLog::class)->call('verifyChain')->assertSee('intact');

        DB::table('crm_audit_events')->where('action', 'created')->update(['after' => json_encode(['code' => 'CHANGED'])]);
        Livewire::test(AuditLog::class)->call('verifyChain')->assertSee('does not match');
    }

    public function test_the_page_has_no_buttons_that_change_anything(): void
    {
        $this->event('coupon', 'created', 'A', null);
        $this->actingAs($this->ops);

        $html = Livewire::test(AuditLog::class)->html();

        foreach (['delete', 'remove', 'edit'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase('wire:click="'.$word, $html);
        }
        $this->assertSame(1, CrmAuditEvent::count());
    }
}
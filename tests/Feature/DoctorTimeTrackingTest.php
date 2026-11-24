<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Doctor\TimeSheet;
use App\Livewire\Doctor\TimerButton;
use App\Models\Appointment;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Doctor\DoctorRequestService;
use App\Services\Doctor\TimeTracker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 2.2 time tracking (Craig's guide 4.2, 8.1): timers, manual entries, edits with a reason and an audit
 * trail, the monthly invoice summary and who may read it.
 */
class DoctorTimeTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00', 'Africa/Johannesburg'));
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function t(): TimeTracker
    {
        return app(TimeTracker::class);
    }

    private function patientOf(User $doctor): User
    {
        $p = $this->user(UserRole::Patient);
        Appointment::create([
            'patient_id' => $p->id, 'doctor_id' => $doctor->id, 'type' => 'general', 'status' => 'completed',
            'appointment_date' => now()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:45', 'fee_amount' => 70000,
        ]);

        return $p;
    }

    private function forbidden(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Expected 403');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_a_timer_counts_whole_minutes_rounded_up(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $p = $this->patientOf($doctor);

        $e = $this->t()->start($doctor, $p->id);
        $this->assertTrue($e->isRunning());
        $this->travel(7)->minutes();
        $this->travel(20)->seconds();

        $done = $this->t()->stop($doctor, $e);
        $this->assertFalse($done->isRunning());
        $this->assertSame(8, $done->minutes);
        $this->assertFalse($done->capped);
    }

    public function test_a_very_short_timer_counts_one_minute(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $e = $this->t()->start($doctor, $this->patientOf($doctor)->id);

        $this->assertSame(1, $this->t()->stop($doctor, $e)->minutes);
    }

    public function test_a_forgotten_timer_is_cut_at_the_maximum_and_flagged(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $e = $this->t()->start($doctor, $this->patientOf($doctor)->id);
        $this->travel(9)->hours();

        $done = $this->t()->stop($doctor, $e);
        $this->assertSame(240, $done->minutes);
        $this->assertTrue($done->capped);
    }

    public function test_starting_a_second_timer_stops_the_first_and_the_same_target_is_not_doubled(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $a = $this->patientOf($doctor);
        $b = $this->patientOf($doctor);

        $first = $this->t()->start($doctor, $a->id);
        $this->assertSame($first->id, $this->t()->start($doctor, $a->id)->id, 'Same target keeps the one timer.');

        $this->travel(5)->minutes();
        $second = $this->t()->start($doctor, $b->id);

        $this->assertNotSame($first->id, $second->id);
        $this->assertFalse($first->fresh()->isRunning());
        $this->assertSame(5, $first->fresh()->minutes);
        $this->assertSame(1, TimeEntry::running()->count());
    }

    public function test_only_doctors_log_time_and_only_on_their_own_patients_and_requests(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $other = $this->user(UserRole::Doctor);
        $mine = $this->patientOf($doctor);
        $notMine = $this->user(UserRole::Patient);

        $this->forbidden(fn () => $this->t()->start($this->user(UserRole::Admin), $mine->id));
        $this->forbidden(fn () => $this->t()->start($this->user(UserRole::HealthCoach), $mine->id));

        foreach ([fn () => $this->t()->start($doctor, $notMine->id), fn () => $this->t()->start($other, $mine->id), fn () => $this->t()->start($doctor)] as $try) {
            try {
                $try();
                $this->fail('Should be refused');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('target', $e->errors());
            }
        }

        $ops = $this->user(UserRole::Admin);
        $req = app(DoctorRequestService::class)->create($ops, ['title' => 'T', 'assignee_ids' => [$doctor->id]]);
        $this->assertSame($req->id, $this->t()->start($doctor, null, $req->id)->doctor_request_id);

        $this->expectException(ValidationException::class);
        $this->t()->start($other, null, $req->id);
    }

    public function test_manual_entries_are_checked(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $p = $this->patientOf($doctor);

        $e = $this->t()->addManual($doctor, 30, '2026-11-20', $p->id, null, 'Notes');
        $this->assertSame(30, $e->minutes);
        $this->assertSame('manual', $e->source);

        foreach ([[0, '2026-11-20', 'minutes'], [721, '2026-11-20', 'minutes'], [10, '2026-11-25', 'date'], [10, '2026-01-01', 'date'], [10, 'not a date', 'date']] as [$min, $date, $field]) {
            try {
                $this->t()->addManual($doctor, $min, $date, $p->id);
                $this->fail("Should refuse $min / $date");
            } catch (ValidationException $ex) {
                $this->assertArrayHasKey($field, $ex->errors());
            }
        }
        $this->assertSame(1, TimeEntry::count());
    }

    public function test_an_edit_needs_a_reason_keeps_the_old_value_and_only_the_owner_can_edit(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $p = $this->patientOf($doctor);
        $e = $this->t()->addManual($doctor, 30, '2026-11-20', $p->id);

        try {
            $this->t()->adjust($doctor, $e, 45, '  ');
            $this->fail('Reason required');
        } catch (ValidationException $ex) {
            $this->assertArrayHasKey('reason', $ex->errors());
        }
        $this->assertSame(30, $e->fresh()->minutes);

        $this->forbidden(fn () => $this->t()->adjust($this->user(UserRole::Doctor), $e, 45, 'x'));
        $this->forbidden(fn () => $this->t()->adjust($this->user(UserRole::Admin), $e, 45, 'x'));

        $new = $this->t()->adjust($doctor, $e, 45, 'Phone call afterwards');
        $this->assertSame(45, $new->minutes);
        $this->assertDatabaseHas('time_entry_adjustments', [
            'time_entry_id' => $e->id, 'user_id' => $doctor->id, 'old_minutes' => 30, 'new_minutes' => 45, 'reason' => 'Phone call afterwards',
        ]);

        $this->t()->adjust($doctor, $e->fresh(), 40, 'Rounded down');
        $this->assertSame(2, $e->adjustments()->count(), 'Every change is kept.');
    }

    public function test_a_running_timer_cannot_be_edited_and_same_minutes_is_refused(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $running = $this->t()->start($doctor, $this->patientOf($doctor)->id);

        $this->expectException(ValidationException::class);
        $this->t()->adjust($doctor, $running, 10, 'x');
    }

    public function test_the_monthly_summary_adds_up_and_ignores_other_months_and_other_doctors(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $other = $this->user(UserRole::Doctor);
        $a = $this->patientOf($doctor);
        $b = $this->patientOf($doctor);
        $ops = $this->user(UserRole::Admin);
        $req = app(DoctorRequestService::class)->create($ops, ['title' => 'Stock query', 'assignee_ids' => [$doctor->id]]);

        $this->t()->addManual($doctor, 30, '2026-11-02', $a->id);
        $this->t()->addManual($doctor, 15, '2026-11-02', $b->id);
        $e = $this->t()->addManual($doctor, 60, '2026-11-10', null, $req->id);
        $this->t()->adjust($doctor, $e, 45, 'Overestimated');
        $this->t()->addManual($doctor, 20, '2026-10-30', $a->id);                      // last month
        $this->t()->addManual($other, 99, '2026-11-03', $this->patientOf($other)->id);  // someone else
        $this->t()->start($doctor, $a->id);                                              // running: not counted

        $s = $this->t()->monthlySummary($doctor, '2026-11');

        $this->assertSame(90, $s['total_minutes']);
        $this->assertSame(1.5, $s['total_hours']);
        $this->assertSame(3, $s['entries']);
        $this->assertSame(1, $s['adjusted']);
        $this->assertSame(1, $s['running']);
        $this->assertSame(45, $s['days']['2026-11-02']);
        $this->assertSame(45, $s['days']['2026-11-10']);
        $this->assertSame(['Stock query' => 45], $s['requests']->mapWithKeys(fn ($r) => [$r['title'] => $r['minutes']])->all());
        $this->assertCount(2, $s['patients']);

        $this->assertSame(20, $this->t()->monthlySummary($doctor, '2026-10')['total_minutes']);
    }

    public function test_a_bad_month_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        $this->t()->monthlySummary($this->user(UserRole::Doctor), 'last month');
    }

    public function test_csv_export_has_the_total_and_neutralises_formulas(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $p = $this->patientOf($doctor);
        $this->t()->addManual($doctor, 30, '2026-11-02', $p->id, null, '=HYPERLINK("http://evil")');
        $this->t()->addManual($doctor, 15, '2026-11-03', $p->id, null, 'ok');

        $csv = $this->t()->csv($doctor, '2026-11');

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', str_replace('"', '', $csv));
        $this->assertStringContainsString('Total,,,,45,,"0.75 hours"', $csv);
    }

    public function test_who_can_download_the_export(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $other = $this->user(UserRole::Doctor);
        $ops = $this->user(UserRole::Admin);
        $this->t()->addManual($doctor, 30, '2026-11-02', $this->patientOf($doctor)->id);

        $this->actingAs($doctor)->get(route('doctor.time.export', ['month' => '2026-11']))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertSee('Total');

        $this->actingAs($ops)->get(route('doctor.time.export', ['month' => '2026-11', 'doctor' => $doctor->id]))->assertOk();
        $this->actingAs($other)->get(route('doctor.time.export', ['month' => '2026-11', 'doctor' => $doctor->id]))->assertForbidden();
        $this->actingAs($this->user(UserRole::Patient))->get(route('doctor.time.export'))->assertForbidden();
        $this->actingAs($this->user(UserRole::HealthCoach))->get(route('doctor.time.export'))->assertForbidden();
    }

    public function test_the_time_sheet_screen_adds_edits_and_stops(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $p = $this->patientOf($doctor);

        $this->actingAs($this->user(UserRole::Admin))->get(route('doctor.time'))->assertForbidden();

        Livewire::actingAs($doctor)->test(TimeSheet::class)
            ->set('target', 'patient:'.$p->id)->set('minutes', 25)->set('note', 'Review')
            ->call('addManual')->assertHasNoErrors()
            ->assertSee('0.42')
            ->call('edit', TimeEntry::first()->id)
            ->set('newMinutes', 30)->set('reason', '')
            ->call('saveEdit')
            ->assertHasErrors()                  // the reason is missing
            ->set('reason', 'Forgot a call')->call('saveEdit')->assertHasNoErrors()
            ->assertSee('Forgot a call');

        $this->assertSame(30, TimeEntry::first()->minutes);

        $t = $this->t()->start($doctor, $p->id);
        Livewire::actingAs($doctor)->test(TimeSheet::class)->assertSee('Timer running')->call('stop');
        $this->assertFalse($t->fresh()->isRunning());
    }

    public function test_the_timer_button_starts_and_stops_for_its_own_target_only(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $a = $this->patientOf($doctor);
        $b = $this->patientOf($doctor);

        Livewire::actingAs($doctor)->test(TimerButton::class, ['patientId' => $a->id])->call('toggle')->assertSee('Stop timer');
        Livewire::actingAs($doctor)->test(TimerButton::class, ['patientId' => $b->id])->assertSee('Start timer');
        $this->assertNotNull($this->t()->running($doctor)->patient_id);

        Livewire::actingAs($doctor)->test(TimerButton::class, ['patientId' => $a->id])->call('toggle')->assertSee('Start timer');
        $this->assertNull($this->t()->running($doctor));
    }

    public function test_the_timer_shows_on_the_request_and_consultation_pages(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $ops = $this->user(UserRole::Admin);
        $req = app(DoctorRequestService::class)->create($ops, ['title' => 'T', 'assignee_ids' => [$doctor->id]]);

        $this->actingAs($doctor)->get(route('doctor.requests.show', $req))->assertOk()->assertSee('Start timer');
        $this->actingAs($ops)->get(route('doctor.requests.show', $req))->assertOk()->assertDontSee('Start timer');
    }
}

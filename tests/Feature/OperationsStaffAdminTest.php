<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\StaffProfileEditor;
use App\Models\CrmAuditEvent;
use App\Models\DoctorProfile;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Operations\StaffAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4.4 User Admin (Craig's guide 6.4): give a signed-up user a staff role, keep the staff profile, upload a doctor's
 * signature. Operations only; everything is audited; "verified" is what lets a doctor be booked.
 */
class OperationsStaffAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $ops;

    protected function setUp(): void
    {
        parent::setUp();
        config(['operations.staff.signature.disk' => 'local']);
        Storage::fake('local');
        $this->ops = User::factory()->create(['role' => UserRole::Admin]);
    }

    private function svc(): StaffAdmin
    {
        return app(StaffAdmin::class);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function png(string $name = 'sig.png', int $kb = 20): UploadedFile
    {
        return UploadedFile::fake()->image($name, 200, 80)->size($kb);
    }

    private function doctorWithProfile(string $hpcsa = 'MP100'): User
    {
        $d = $this->user(UserRole::Doctor);
        DoctorProfile::create(['user_id' => $d->id, 'hpcsa_number' => $hpcsa, 'qualification' => 'MBChB', 'is_verified' => false]);

        return $d;
    }

    private function fields(array $over = []): array
    {
        return $over + [
            'registration_number' => 'MP0123456', 'board' => 'HPCSA', 'practice_address' => "1 Main Road\nCape Town",
            'registration_status' => 'verified', 'country' => 'za', 'booking_url' => 'https://book.example.test/dr', 'authorisation_booking_url' => 'https://book.example.test/check-in',
        ];
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

    public function test_a_signed_up_patient_can_be_made_a_doctor_a_coach_or_operations(): void
    {
        foreach (['doctor', 'health_coach', 'admin'] as $role) {
            $u = $this->user(UserRole::Patient);

            $this->assertSame($role, $this->svc()->assignRole($this->ops, $u, $role)->role->value);
            $profile = StaffProfile::where('user_id', $u->id)->firstOrFail();
            $this->assertSame($role === 'admin' ? 'not_required' : 'pending', $profile->registration_status);
        }
    }

    public function test_role_changes_are_audited_with_before_and_after(): void
    {
        $u = $this->user(UserRole::Patient);
        $this->svc()->assignRole($this->ops, $u, 'doctor');

        $e = CrmAuditEvent::where('domain', 'staff')->where('action', 'role_assigned')->firstOrFail();
        $this->assertSame((string) $u->id, $e->subject_id);
        $this->assertSame(['role' => 'patient'], $e->before);
        $this->assertSame(['role' => 'doctor'], $e->after);
        $this->assertSame($this->ops->id, $e->actor_id);
    }

    public function test_role_rules(): void
    {
        $doctor = $this->user(UserRole::Doctor);
        $cases = [
            'self' => fn () => $this->svc()->assignRole($this->ops, $this->ops, 'doctor'),
            'patient role' => fn () => $this->svc()->assignRole($this->ops, $doctor, 'patient'),
            'unknown role' => fn () => $this->svc()->assignRole($this->ops, $doctor, 'superuser'),
            'pharmacy role' => fn () => $this->svc()->assignRole($this->ops, $doctor, 'pharmacy_staff'),
            'same role' => fn () => $this->svc()->assignRole($this->ops, $doctor, 'doctor'),
            'pharmacy user' => fn () => $this->svc()->assignRole($this->ops, $this->user(UserRole::PharmacyStaff), 'doctor'),
        ];
        foreach ($cases as $label => $try) {
            try {
                $try();
                $this->fail("Should be refused: $label");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('role', $e->errors(), $label);
            }
        }
        $this->assertSame(UserRole::Admin, $this->ops->fresh()->role);
        $this->assertSame(UserRole::Doctor, $doctor->fresh()->role);
    }

    public function test_only_operations_can_assign_roles_or_edit_profiles(): void
    {
        $u = $this->user(UserRole::Patient);
        foreach ([UserRole::Doctor, UserRole::HealthCoach, UserRole::Patient, UserRole::PharmacyStaff] as $role) {
            $who = $this->user($role);
            $this->forbidden(fn () => $this->svc()->assignRole($who, $u, 'doctor'));
            $this->forbidden(fn () => $this->svc()->saveProfile($who, $this->user(UserRole::Doctor), $this->fields()));
            $this->forbidden(fn () => $this->svc()->uploadSignature($who, $this->user(UserRole::Doctor), $this->png()));
        }
        $this->assertSame(UserRole::Patient, $u->fresh()->role);
    }

    public function test_the_profile_is_saved_cleaned_and_audited(): void
    {
        $d = $this->user(UserRole::Doctor);

        $p = $this->svc()->saveProfile($this->ops, $d, $this->fields(['practice_address' => "  1 Main Road  "]));

        $this->assertSame('MP0123456', $p->registration_number);
        $this->assertSame('HPCSA', $p->board);
        $this->assertSame('ZA', $p->country, 'Upper-cased.');
        $this->assertSame('1 Main Road', $p->practice_address);
        $this->assertSame('https://book.example.test/check-in', $p->authorisation_booking_url);
        $this->assertSame($this->ops->id, $p->updated_by);
        $this->assertSame(1, CrmAuditEvent::where('action', 'profile_saved')->count());

        $again = $this->svc()->saveProfile($this->ops, $d, $this->fields(['board' => 'SANC']));
        $this->assertSame($p->id, $again->id, 'One profile per person.');
        $this->assertSame(1, StaffProfile::where('user_id', $d->id)->count());
        $this->assertSame(['board' => 'HPCSA'], collect(CrmAuditEvent::where('action', 'profile_saved')->latest('id')->first()->before)->only('board')->all());
    }

    public function test_profile_validation(): void
    {
        $d = $this->user(UserRole::Doctor);
        $cases = [
            'registration_status' => $this->fields(['registration_status' => 'great']),
            'registration_number' => $this->fields(['registration_number' => '  ']),   // verified without a number
            'country' => $this->fields(['country' => 'South Africa']),
            'booking_url' => $this->fields(['booking_url' => 'javascript:alert(1)']),
            'booking_url ' => $this->fields(['booking_url' => 'not a link']),
            'authorisation_booking_url' => $this->fields(['authorisation_booking_url' => 'ftp://files.example.test']),
        ];
        foreach ($cases as $field => $data) {
            try {
                $this->svc()->saveProfile($this->ops, $d, $data);
                $this->fail("Should be refused: $field");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(trim($field), $e->errors());
            }
        }
        $this->assertSame(0, StaffProfile::where('user_id', $d->id)->count());
    }

    public function test_empty_optional_fields_are_fine_and_patients_have_no_staff_profile(): void
    {
        $d = $this->user(UserRole::Doctor);
        $p = $this->svc()->saveProfile($this->ops, $d, ['registration_status' => 'pending']);
        $this->assertNull($p->registration_number);
        $this->assertNull($p->booking_url);
        $this->assertSame('ZA', $p->country);

        try {
            $this->svc()->saveProfile($this->ops, $this->user(UserRole::Patient), $this->fields());
            $this->fail('A patient has no staff profile');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('user', $e->errors());
        }
    }

    public function test_verifying_a_doctor_makes_them_bookable_and_lapsing_takes_it_away(): void
    {
        $d = $this->doctorWithProfile('MP100');
        $this->assertFalse($d->doctorProfile->fresh()->is_verified);

        $this->svc()->saveProfile($this->ops, $d, $this->fields(['registration_number' => 'MP555']));
        $this->assertTrue($d->doctorProfile()->first()->is_verified);
        $this->assertSame('MP555', $d->doctorProfile()->first()->hpcsa_number, 'The registration number is the HPCSA number.');

        $this->svc()->saveProfile($this->ops, $d, $this->fields(['registration_number' => 'MP555', 'registration_status' => 'lapsed']));
        $this->assertFalse($d->doctorProfile()->first()->is_verified);
    }

    public function test_a_registration_number_someone_else_holds_is_not_copied_over(): void
    {
        $other = $this->doctorWithProfile('MP777');
        $d = $this->doctorWithProfile('MP100');

        $this->svc()->saveProfile($this->ops, $d, $this->fields(['registration_number' => 'MP777']));

        $this->assertSame('MP100', $d->doctorProfile()->first()->hpcsa_number, 'Unique: left alone, no crash.');
        $this->assertSame('MP777', $other->doctorProfile()->first()->hpcsa_number);
    }

    public function test_a_doctor_without_a_doctor_profile_still_gets_a_staff_profile(): void
    {
        $d = $this->user(UserRole::Doctor);

        $this->svc()->saveProfile($this->ops, $d, $this->fields());

        $this->assertSame(0, DoctorProfile::where('user_id', $d->id)->count(), 'Nothing is invented.');
        $this->assertSame('verified', StaffProfile::where('user_id', $d->id)->value('registration_status'));
    }

    public function test_a_signature_is_stored_privately_replaced_and_synced(): void
    {
        $d = $this->doctorWithProfile();

        $first = $this->svc()->uploadSignature($this->ops, $d, $this->png('my-name.png'));
        Storage::disk('local')->assertExists($first->signature_path);
        $this->assertStringNotContainsString('my-name', $first->signature_path);
        $this->assertSame($first->signature_path, $d->doctorProfile()->first()->signature_path);

        $second = $this->svc()->uploadSignature($this->ops, $d, $this->png('new.png'));
        $this->assertNotSame($first->signature_path, $second->signature_path);
        Storage::disk('local')->assertMissing($first->signature_path);
        Storage::disk('local')->assertExists($second->signature_path);
        $this->assertSame(2, CrmAuditEvent::where('action', 'signature_uploaded')->count());
    }

    public function test_bad_signatures_are_refused(): void
    {
        $d = $this->user(UserRole::Doctor);
        $cases = [
            UploadedFile::fake()->create('sig.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->create('sig.exe', 20),
            UploadedFile::fake()->image('big.png')->size(3000),
            UploadedFile::fake()->createWithContent('fake.png', 'this is not a picture'),
        ];
        foreach ($cases as $bad) {
            try {
                $this->svc()->uploadSignature($this->ops, $d, $bad);
                $this->fail('Should be refused: '.$bad->getClientOriginalName());
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('signature', $e->errors());
            }
        }

        try {
            $this->svc()->uploadSignature($this->ops, $this->user(UserRole::HealthCoach), $this->png());
            $this->fail('Only doctors');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('signature', $e->errors());
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_the_signature_is_only_served_to_operations_and_that_doctor(): void
    {
        $d = $this->doctorWithProfile();
        $this->svc()->uploadSignature($this->ops, $d, $this->png());

        $this->actingAs($this->ops)->get(route('admin.staff-signature', $d))->assertOk();
        $this->actingAs($d)->get(route('admin.staff-signature', $d))->assertOk();
        foreach ([$this->user(UserRole::Doctor), $this->user(UserRole::Patient), $this->user(UserRole::HealthCoach)] as $no) {
            $this->actingAs($no)->get(route('admin.staff-signature', $d))->assertForbidden();
        }
        $this->actingAs($this->ops)->get(route('admin.staff-signature', $this->user(UserRole::Doctor)))->assertNotFound();
    }

    public function test_the_editor_screen(): void
    {
        $u = $this->user(UserRole::Patient);
        $this->actingAs($this->ops)->get(route('admin.staff-profile', $u))->assertOk()->assertSeeHtml('data-not-staff');
        foreach ([UserRole::Doctor, UserRole::HealthCoach, UserRole::Patient] as $role) {
            $this->actingAs($this->user($role))->get(route('admin.staff-profile', $u))->assertForbidden();
        }

        $this->actingAs($this->ops);
        Livewire::test(StaffProfileEditor::class, ['user' => $u])
            ->set('role', 'doctor')->call('assignRole')->assertHasNoErrors()->assertSeeHtml('data-section="profile"')->assertSeeHtml('data-section="signature"')
            ->set('registrationStatus', 'verified')->set('registrationNumber', '')->call('save')->assertHasErrors('registration_number')
            ->set('registrationNumber', 'MP999')->set('bookingUrl', 'nonsense')->call('save')->assertHasErrors('booking_url')
            ->set('bookingUrl', 'https://book.example.test/x')->call('save')->assertHasNoErrors()->assertSeeHtml('data-saved')
            ->set('signature', $this->png())->call('uploadSignature')->assertHasNoErrors()->assertSee('See the current signature');

        $this->assertSame('MP999', StaffProfile::where('user_id', $u->id)->value('registration_number'));
        $this->assertSame(UserRole::Doctor, $u->fresh()->role);
    }

    public function test_the_editor_screen_cannot_make_someone_a_patient_or_change_your_own_role(): void
    {
        $this->actingAs($this->ops);

        Livewire::test(StaffProfileEditor::class, ['user' => $this->ops])->set('role', 'doctor')->call('assignRole')->assertHasErrors('role');
        $this->assertSame(UserRole::Admin, $this->ops->fresh()->role);
    }

    public function test_the_user_list_links_to_the_editor(): void
    {
        $this->user(UserRole::Patient);
        $this->actingAs($this->ops)->get(route('admin.users'))->assertOk()->assertSee('Role and staff profile');
    }
}

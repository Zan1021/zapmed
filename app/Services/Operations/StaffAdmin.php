<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\DoctorProfile;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Compliance\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * User Admin (Craig's guide 6.4): Operations give a signed-up user the Operations, Doctor or Health Coach role and keep the
 * staff profile. Admin only. Every role change and profile change goes into the tamper-evident audit trail.
 *
 *  - You cannot change your own role.
 *  - Roles can only be moved between the assignable staff roles and from patient to staff. Nobody is turned back into a patient
 *    here (deactivate the account instead).
 *  - "Verified" on the profile switches a doctor's DoctorProfile.is_verified on, which is what lets them appear in booking.
 */
class StaffAdmin
{
    public function __construct(private readonly AuditTrail $audit)
    {
    }

    private function assertOps(User $ops): void
    {
        abort_unless($ops->role === UserRole::Admin, 403);
    }

    public function disk()
    {
        return Storage::disk(config('operations.staff.signature.disk'));
    }

    public function assignRole(User $ops, User $user, string $role): User
    {
        $this->assertOps($ops);

        if ($ops->is($user)) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }
        if (! in_array($role, config('operations.staff.assignable_roles'), true)) {
            throw ValidationException::withMessages(['role' => 'Choose Operations, Doctor or Health Coach.']);
        }
        if ($user->role === UserRole::PharmacyStaff) {
            throw ValidationException::withMessages(['role' => 'Pharmacy staff accounts are managed with the pharmacy integration.']);
        }
        if ($user->role->value === $role) {
            throw ValidationException::withMessages(['role' => 'They already have that role.']);
        }

        DB::transaction(function () use ($ops, $user, $role) {
            $before = $user->role->value;
            $user->forceFill(['role' => $role])->save();

            StaffProfile::firstOrCreate(['user_id' => $user->id], [
                'registration_status' => $role === 'admin' ? 'not_required' : 'pending',
                'updated_by' => $ops->id,
            ]);

            $this->audit->record('staff', 'role_assigned', 'user', (string) $user->id, ['role' => $before], ['role' => $role], $ops->id);
        });

        return $user->fresh();
    }

    /**
     * @param array{registration_number?:?string,board?:?string,practice_address?:?string,registration_status?:string,country?:?string,booking_url?:?string,authorisation_booking_url?:?string} $data
     */
    public function saveProfile(User $ops, User $user, array $data): StaffProfile
    {
        $this->assertOps($ops);

        if (! in_array($user->role, [UserRole::Admin, UserRole::Doctor, UserRole::HealthCoach], true)) {
            throw ValidationException::withMessages(['user' => 'Only doctors, health coaches and Operations have a staff profile.']);
        }

        $statuses = array_keys(config('operations.staff.registration_statuses'));
        $status = (string) ($data['registration_status'] ?? 'pending');
        if (! in_array($status, $statuses, true)) {
            throw ValidationException::withMessages(['registration_status' => 'Choose one of the listed statuses.']);
        }

        $number = $this->clean($data['registration_number'] ?? null, 40);
        if ($status === 'verified' && $user->role !== UserRole::Admin && $number === null) {
            throw ValidationException::withMessages(['registration_number' => 'Enter the registration number before you mark them as verified.']);
        }

        $country = strtoupper((string) ($data['country'] ?? 'ZA'));
        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            throw ValidationException::withMessages(['country' => 'Use a two-letter country code, for example ZA.']);
        }

        $values = [
            'registration_number' => $number,
            'board' => $this->clean($data['board'] ?? null, 40),
            'practice_address' => $this->clean($data['practice_address'] ?? null, 500),
            'registration_status' => $status,
            'country' => $country,
            'booking_url' => $this->url($data['booking_url'] ?? null, 'booking_url'),
            'authorisation_booking_url' => $this->url($data['authorisation_booking_url'] ?? null, 'authorisation_booking_url'),
            'updated_by' => $ops->id,
        ];

        return DB::transaction(function () use ($ops, $user, $values) {
            $profile = StaffProfile::firstOrNew(['user_id' => $user->id]);
            $before = $profile->exists ? $profile->only(array_keys($values)) : null;
            $profile->fill($values)->save();

            $this->syncDoctorProfile($user, $values);
            $this->audit->record('staff', 'profile_saved', 'user', (string) $user->id, $before, $values, $ops->id);

            return $profile->fresh();
        });
    }

    /** A doctor's signature image. Doctors only. Replaces the old one. */
    public function uploadSignature(User $ops, User $doctor, UploadedFile $file): StaffProfile
    {
        $this->assertOps($ops);

        if ($doctor->role !== UserRole::Doctor) {
            throw ValidationException::withMessages(['signature' => 'Only doctors have a signature.']);
        }
        $cfg = config('operations.staff.signature');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $cfg['allowed_extensions'], true)) {
            throw ValidationException::withMessages(['signature' => 'Upload a PNG, JPG or WebP image.']);
        }
        if ($file->getSize() > $cfg['max_kb'] * 1024) {
            throw ValidationException::withMessages(['signature' => 'That image is too big (2 MB at most).']);
        }
        if (@getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages(['signature' => 'That file is not a picture.']);
        }

        $path = $file->storeAs('staff-signatures/'.$doctor->id, bin2hex(random_bytes(12)).'.'.$ext, $cfg['disk']);

        return DB::transaction(function () use ($ops, $doctor, $path) {
            $profile = StaffProfile::firstOrCreate(['user_id' => $doctor->id], ['updated_by' => $ops->id]);
            $old = $profile->signature_path;
            $profile->update(['signature_path' => $path, 'updated_by' => $ops->id]);
            DoctorProfile::where('user_id', $doctor->id)->update(['signature_path' => $path]);
            if ($old && $old !== $path) {
                $this->disk()->delete($old);
            }
            $this->audit->record('staff', 'signature_uploaded', 'user', (string) $doctor->id, null, ['replaced' => (bool) $old], $ops->id);

            return $profile->fresh();
        });
    }

    /**
     * The doctor's signature as an inline image for a PDF ("data:image/png;base64,..."), or null when there is none or the
     * file is missing. Read from the private disk here, so the PDF never needs a public link to it.
     */
    public function signatureDataUri(User $doctor): ?string
    {
        $path = StaffProfile::where('user_id', $doctor->id)->value('signature_path');
        if (! $path || ! $this->disk()->exists($path)) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'][$ext] ?? null;
        if (! $mime || $this->disk()->size($path) > config('operations.staff.signature.max_kb') * 1024) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($this->disk()->get($path));
    }

    /** Keep the doctor's own profile in step: verified switches booking on, the registration number is the HPCSA number. */
    private function syncDoctorProfile(User $user, array $values): void
    {
        if ($user->role !== UserRole::Doctor || ! ($doctor = DoctorProfile::where('user_id', $user->id)->first())) {
            return;
        }

        $update = ['is_verified' => $values['registration_status'] === 'verified'];

        // hpcsa_number is unique: only copy it across when nobody else holds it
        $number = $values['registration_number'];
        if ($number && ! DoctorProfile::where('hpcsa_number', $number)->where('id', '!=', $doctor->id)->exists()) {
            $update['hpcsa_number'] = $number;
        }

        $doctor->update($update);
    }

    private function clean(?string $v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private function url(?string $v, string $field): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (mb_strlen($v) > 255 || ! filter_var($v, FILTER_VALIDATE_URL) || ! in_array(parse_url($v, PHP_URL_SCHEME), ['https', 'http'], true)) {
            throw ValidationException::withMessages([$field => 'Enter a full web address that starts with https://.']);
        }

        return $v;
    }
}

<?php

namespace App\Services\Patient;

use App\Enums\UserRole;
use App\Models\HealthRecord;
use App\Models\User;
use App\Services\Coach\CoachAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Medical records and results (Craig's guide 3.1).
 *
 *  - A patient uploads records for their own file. A doctor who treats the patient uploads results for them.
 *  - Readable only by the patient and doctors who treat them. Not by Operations, not by coaches.
 *  - Files sit on a private disk under random names and are served only through an access-checked route.
 */
class HealthRecords
{
    public function __construct(private readonly CoachAccess $access)
    {
    }

    public function disk()
    {
        return Storage::disk(config('patient.health.records.disk'));
    }

    public function canView(User $user, HealthRecord $record): bool
    {
        if ($user->id === $record->patient_id) {
            return true;
        }

        return $user->role === UserRole::Doctor && $record->patient && $this->access->canView($user, $record->patient);
    }

    public function upload(User $uploader, User $patient, UploadedFile $file, string $title): HealthRecord
    {
        $isPatient = $uploader->id === $patient->id && $patient->role === UserRole::Patient;
        $isDoctor = $uploader->role === UserRole::Doctor && $patient->role === UserRole::Patient && $this->access->canView($uploader, $patient);
        abort_unless($isPatient || $isDoctor, 403);

        $title = trim($title);
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Give the document a short name.']);
        }

        $cfg = config('patient.health.records');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $cfg['allowed_extensions'], true)) {
            throw ValidationException::withMessages(['file' => 'Upload a PDF, JPG or PNG file.']);
        }
        if ($file->getSize() > $cfg['max_kb'] * 1024) {
            throw ValidationException::withMessages(['file' => 'That file is too big (10 MB at most).']);
        }
        if (HealthRecord::where('patient_id', $patient->id)->count() >= $cfg['max_per_patient']) {
            throw ValidationException::withMessages(['file' => 'There are already as many documents as can be stored.']);
        }

        $path = $file->storeAs('health-records/'.$patient->id, bin2hex(random_bytes(12)).'.'.$ext, $cfg['disk']);

        return HealthRecord::create([
            'patient_id' => $patient->id,
            'uploader_id' => $uploader->id,
            'uploader_role' => $isPatient ? 'patient' : 'doctor',
            'category' => $isPatient ? HealthRecord::RECORD : HealthRecord::RESULT,
            'title' => mb_substr($title, 0, 120),
            'path' => $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
            'size_bytes' => (int) $file->getSize(),
        ]);
    }

    /** Everything on a patient's file that the viewer may open, newest first. */
    public function listFor(User $viewer, User $patient)
    {
        return HealthRecord::with('uploader')->where('patient_id', $patient->id)->orderByDesc('id')->get()
            ->filter(fn (HealthRecord $r) => $this->canView($viewer, $r))->values();
    }
}

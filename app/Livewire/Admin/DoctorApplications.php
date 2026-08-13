<?php

namespace App\Livewire\Admin;

use App\Models\DoctorApplication;
use App\Models\User;
use App\Models\DoctorProfile;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class DoctorApplications extends Component
{
    use WithPagination;

    public string $statusFilter = 'pending';
    public ?int $viewingId = null;
    public string $adminNotes = '';

    public function filterByStatus(string $status)
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function viewApplication(int $id)
    {
        $this->viewingId = $id;
        $app = DoctorApplication::find($id);
        $this->adminNotes = $app->admin_notes ?? '';
    }

    public function closeView()
    {
        $this->viewingId = null;
        $this->adminNotes = '';
    }

    public function approve(int $id)
    {
        $application = DoctorApplication::findOrFail($id);

        // Create user account
        $password = Str::random(16);
        $user = User::create([
            'first_name' => $application->first_name,
            'last_name' => $application->last_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'role' => UserRole::Doctor,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        // Create doctor profile
        DoctorProfile::create([
            'user_id' => $user->id,
            'hpcsa_number' => $application->hpcsa_number,
            'speciality' => $application->speciality,
            'qualification' => $application->qualification,
            'doctor_type' => $application->doctor_type,
            'is_verified' => true,
        ]);

        // Update application
        $application->update([
            'status' => 'approved',
            'admin_notes' => $this->adminNotes,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        // Send approval email with temporary password
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Dear Dr. {$application->first_name} {$application->last_name},\n\n" .
                "Congratulations! Your application to join Zapmed has been approved.\n\n" .
                "You can now log in with the following credentials:\n" .
                "Email: {$application->email}\n" .
                "Temporary Password: {$password}\n\n" .
                "Please change your password after your first login.\n\n" .
                "After logging in, you'll be asked to set your availability schedule so patients can book consultations with you.\n\n" .
                "Login here: " . url('/login') . "\n\n" .
                "Welcome to the team!\n" .
                "The Zapmed Team",
                function ($message) use ($application) {
                    $message->to($application->email)
                        ->subject('Welcome to Zapmed - Application Approved');
                }
            );
        } catch (\Exception $e) {
            // Continue even if email fails
        }

        $this->viewingId = null;
        session()->flash('message', "Dr. {$application->name} approved and account created.");
    }

    public function reject(int $id)
    {
        $application = DoctorApplication::findOrFail($id);

        $application->update([
            'status' => 'rejected',
            'admin_notes' => $this->adminNotes,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        // Send rejection email
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Dear Dr. {$application->first_name} {$application->last_name},\n\n" .
                "Thank you for your interest in joining Zapmed. After careful review, we are unable to approve your application at this time.\n\n" .
                ($this->adminNotes ? "Reason: {$this->adminNotes}\n\n" : '') .
                "You are welcome to reapply in the future.\n\n" .
                "Kind regards,\nThe Zapmed Team",
                function ($message) use ($application) {
                    $message->to($application->email)
                        ->subject('Application Update - Zapmed');
                }
            );
        } catch (\Exception $e) {
            // Continue
        }

        $this->viewingId = null;
        session()->flash('message', "Application from Dr. {$application->name} rejected.");
    }

    public function render()
    {
        $applications = DoctorApplication::when($this->statusFilter !== 'all', function ($q) {
            $q->where('status', $this->statusFilter);
        })
            ->latest()
            ->paginate(15);

        $counts = [
            'pending' => DoctorApplication::where('status', 'pending')->count(),
            'approved' => DoctorApplication::where('status', 'approved')->count(),
            'rejected' => DoctorApplication::where('status', 'rejected')->count(),
        ];

        return view('livewire.admin.doctor-applications', compact('applications', 'counts'));
    }
}

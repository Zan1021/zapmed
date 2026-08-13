<?php

namespace App\Livewire;

use App\Models\DoctorApplication;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithFileUploads;

class DoctorApply extends Component
{
    use WithFileUploads;

    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $phone = '';
    public string $hpcsa_number = '';
    public string $speciality = 'General Practitioner';
    public string $qualification = '';
    public int $years_experience = 0;
    public string $doctor_type = 'full_time';
    public string $motivation = '';
    public $hpcsa_certificate;
    public $id_document;
    public bool $agree_terms = false;
    public bool $submitted = false;

    protected function rules()
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:doctor_applications,email',
            'phone' => 'required|string|max:20',
            'hpcsa_number' => 'required|string|max:20|unique:doctor_applications,hpcsa_number',
            'speciality' => 'required|string|max:100',
            'qualification' => 'required|string|max:255',
            'years_experience' => 'required|integer|min:0|max:60',
            'doctor_type' => 'required|in:full_time,locum',
            'motivation' => 'nullable|string|max:1000',
            'hpcsa_certificate' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
            'id_document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
            'agree_terms' => 'accepted',
        ];
    }

    protected $messages = [
        'email.unique' => 'An application with this email already exists.',
        'hpcsa_number.unique' => 'An application with this HPCSA number already exists.',
        'agree_terms.accepted' => 'You must agree to the terms and conditions.',
    ];

    public function submit()
    {
        $this->validate();

        $certPath = $this->hpcsa_certificate->store('doctor-applications/certificates', 'public');
        $idPath = $this->id_document->store('doctor-applications/id-documents', 'public');

        DoctorApplication::create([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'hpcsa_number' => $this->hpcsa_number,
            'speciality' => $this->speciality,
            'qualification' => $this->qualification,
            'years_experience' => $this->years_experience,
            'doctor_type' => $this->doctor_type,
            'motivation' => $this->motivation,
            'hpcsa_certificate_path' => $certPath,
            'id_document_path' => $idPath,
        ]);

        // Send confirmation email to applicant
        try {
            Mail::raw(
                "Dear Dr. {$this->first_name} {$this->last_name},\n\n" .
                "Thank you for applying to join Zapmed. We have received your application and will review it within 48 hours.\n\n" .
                "You will receive an email once your application has been reviewed.\n\n" .
                "Kind regards,\nThe Zapmed Team",
                function ($message) {
                    $message->to($this->email)
                        ->subject('Application Received - Zapmed');
                }
            );
        } catch (\Exception $e) {
            // Silently fail - don't block application on email failure
        }

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.doctor-apply')
            ->layout('layouts.bare', ['title' => 'Join Zapmed as a Doctor']);
    }
}

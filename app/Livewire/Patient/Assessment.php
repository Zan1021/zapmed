<?php

namespace App\Livewire\Patient;

use App\Models\Assessment as AssessmentModel;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class Assessment extends Component
{
    use WithFileUploads;

    public string $slug;
    public string $treatmentName = '';
    public array $questions = [];
    public array $answers = [];
    public array $photoUploads = []; // Temporary uploaded files

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        // Resolve treatment name from config
        $this->treatmentName = $this->resolveTreatmentName($slug);

        // Load questions (fall back to default)
        $allQuestions = config('assessments');
        $this->questions = $allQuestions[$slug] ?? $allQuestions['default'];

        // Initialize answers array
        foreach ($this->questions as $question) {
            if ($question['type'] === 'checkbox') {
                $this->answers[$question['id']] = [];
            } elseif ($question['type'] === 'image') {
                $this->answers[$question['id']] = '';
            } elseif (isset($question['prefill']) && $question['prefill'] === 'treatment_name') {
                $this->answers[$question['id']] = $this->treatmentName;
            } else {
                $this->answers[$question['id']] = '';
            }
        }
    }

    /**
     * Handle photo upload for a specific question.
     */
    public function updatedPhotoUploads(): void
    {
        // Validate each uploaded file
        foreach ($this->photoUploads as $questionId => $files) {
            if (is_array($files)) {
                $this->validate([
                    "photoUploads.{$questionId}.*" => 'image|max:5120', // 5MB per image
                ]);
            }
        }
    }

    public function submitAssessment(): void
    {
        // Build validation rules
        $rules = [];
        foreach ($this->questions as $question) {
            if ($question['required']) {
                if ($question['type'] === 'checkbox') {
                    $rules["answers.{$question['id']}"] = 'required|array|min:1';
                } elseif ($question['type'] === 'image') {
                    $rules["photoUploads.{$question['id']}"] = 'required|array|min:1';
                    $rules["photoUploads.{$question['id']}.*"] = 'image|max:5120';
                } else {
                    $rules["answers.{$question['id']}"] = 'required|string|min:1';
                }
            }
        }

        $this->validate($rules, [
            'answers.*.required' => 'This field is required.',
            'answers.*.min' => 'This field is required.',
            'photoUploads.*.required' => 'Please upload at least one photo.',
            'photoUploads.*.*.image' => 'File must be an image.',
            'photoUploads.*.*.max' => 'Image must be under 5MB.',
        ]);

        // Store uploaded photos (optimized as WebP with SEO naming)
        $uploadedPaths = [];
        $imageService = app(\App\Services\ImageService::class);

        foreach ($this->photoUploads as $questionId => $files) {
            if (is_array($files)) {
                $question = collect($this->questions)->firstWhere('id', $questionId);
                $altContext = $this->treatmentName . ' ' . ($question['text'] ?? 'assessment photo');

                $processed = $imageService->processMultiple($files, 'assessments/' . $this->slug, $altContext);

                foreach ($processed as $result) {
                    $uploadedPaths[$questionId][] = [
                        'path' => $result['path'],
                        'alt' => $result['alt'],
                    ];
                }
            }
        }

        // Build structured Q&A for storage
        $structuredAnswers = [];
        foreach ($this->questions as $question) {
            if ($question['type'] === 'image') {
                $structuredAnswers[] = [
                    'question_id' => $question['id'],
                    'question' => $question['text'],
                    'type' => 'image',
                    'answer' => $uploadedPaths[$question['id']] ?? [],
                ];
            } else {
                $answer = $this->answers[$question['id']] ?? null;
                $structuredAnswers[] = [
                    'question_id' => $question['id'],
                    'question' => $question['text'],
                    'type' => $question['type'],
                    'answer' => $answer,
                ];
            }
        }

        // If not authenticated, store in session and redirect to register
        if (!Auth::check()) {
            session([
                'pending_assessment' => [
                    'treatment_slug' => $this->slug,
                    'treatment_name' => $this->treatmentName,
                    'answers' => $structuredAnswers,
                ],
            ]);

            $this->redirect(route('register', ['redirect' => 'assessment']), navigate: true);
            return;
        }

        // Create assessment record
        $assessment = AssessmentModel::create([
            'user_id' => Auth::id(),
            'treatment_slug' => $this->slug,
            'treatment_name' => $this->treatmentName,
            'answers' => $structuredAnswers,
            'status' => 'completed',
        ]);

        // Redirect to booking with assessment_id
        $this->redirect(route('patient.book', ['assessment_id' => $assessment->id]), navigate: true);
    }

    private function resolveTreatmentName(string $slug): string
    {
        $categories = config('treatments');

        foreach ($categories as $category) {
            if (isset($category['treatments'][$slug])) {
                return $category['treatments'][$slug]['name'];
            }
        }

        // Fallback: humanize the slug
        return ucwords(str_replace('-', ' ', $slug));
    }

    public function render()
    {
        return view('livewire.patient.assessment')
            ->layout('layouts.assessment', [
                'title' => $this->treatmentName . ' Assessment - Zapmed',
            ]);
    }
}

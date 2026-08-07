<?php

namespace App\Http\Controllers;

use App\Services\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AiAssistantController extends Controller
{
    public function __construct(
        private AiAssistantService $assistant
    ) {}

    /**
     * Handle an AI assistant query from the homepage.
     */
    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        // Rate limit: 10 queries per minute per IP
        $key = 'ai-assistant:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'response' => 'You\'re asking too many questions too quickly. Please wait a moment and try again.',
                'treatment_slug' => null,
                'treatment_name' => null,
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $result = $this->assistant->ask($request->input('message'));

        // Build the treatment URL if we have a slug
        $treatmentUrl = null;
        if ($result['treatment_slug']) {
            $treatmentUrl = route('treatments.show', $result['treatment_slug']);
        }

        return response()->json([
            'response' => $result['response'],
            'treatment_slug' => $result['treatment_slug'],
            'treatment_name' => $result['treatment_name'],
            'treatment_url' => $treatmentUrl,
        ]);
    }
}

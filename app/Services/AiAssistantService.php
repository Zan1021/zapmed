<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAssistantService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', '');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
        $this->baseUrl = 'https://api.openai.com/v1';
    }

    /**
     * Get a response from the AI assistant about health queries.
     *
     * @param string $userMessage The patient's question
     * @return array{response: string, treatment_slug: ?string, treatment_name: ?string}
     */
    public function ask(string $userMessage): array
    {
        if (!$this->isConfigured()) {
            return $this->getFallbackResponse($userMessage);
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->getSystemPrompt()],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->failed()) {
                Log::error('OpenAI API error', ['status' => $response->status(), 'body' => $response->body()]);
                return $this->getFallbackResponse($userMessage);
            }

            $content = $response->json('choices.0.message.content');
            $parsed = json_decode($content, true);

            if (!$parsed) {
                return $this->getFallbackResponse($userMessage);
            }

            return [
                'response' => $parsed['response'] ?? 'I can help with that. Please explore our treatments or book a consultation with one of our doctors.',
                'treatment_slug' => $parsed['treatment_slug'] ?? null,
                'treatment_name' => $parsed['treatment_name'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('AI Assistant error', ['error' => $e->getMessage()]);
            return $this->getFallbackResponse($userMessage);
        }
    }

    /**
     * Build the system prompt with full platform knowledge.
     */
    private function getSystemPrompt(): string
    {
        $treatments = config('treatments');
        $treatmentContext = $this->buildTreatmentContext($treatments);
        $customKnowledge = $this->getCustomKnowledge();

        return <<<PROMPT
You are Zapmed's AI Health Assistant — a friendly, knowledgeable virtual guide on a South African telehealth platform. Your job is to help potential patients understand their health concerns and direct them to the right treatment page on the platform.

## Your Personality
- Warm, professional, empathetic
- Use simple language (avoid heavy medical jargon)
- Be encouraging — help patients feel comfortable seeking treatment
- Never diagnose — always recommend consulting with a Zapmed doctor
- Keep responses concise (2-3 short paragraphs max)

## Platform Information
Zapmed is an online telehealth platform based in South Africa. Licensed SA doctors provide consultations via video call, prescribe medication, and have it delivered to the patient's door. The process: 1) Start an assessment 2) Consult with a doctor 3) Get medication delivered.

## Available Treatments & Pages
{$treatmentContext}

{$customKnowledge}

## Response Rules
1. Always respond with empathy and understanding
2. If the query matches a treatment, recommend that specific treatment page
3. If unsure which treatment fits, suggest a GP Consultation
4. Never provide medical diagnoses or specific medical advice
5. Always encourage the patient to see a Zapmed doctor
6. If the query is completely unrelated to health (e.g. "what's the weather"), politely redirect to health topics
7. Mention pricing if relevant (keeps expectations clear)

## Response Format
You MUST respond in valid JSON with this exact structure:
{
    "response": "Your helpful message to the patient (2-3 paragraphs, with encouragement to take action)",
    "treatment_slug": "the-slug-of-the-relevant-treatment-page" or null if no specific match,
    "treatment_name": "Display name of the treatment" or null
}

Only use treatment_slug values from the list above. If no treatment matches closely, set both to null and suggest a GP consultation in your response.
PROMPT;
    }

    /**
     * Get custom knowledge entries from database.
     */
    private function getCustomKnowledge(): string
    {
        try {
            $entries = \App\Models\AiKnowledgeEntry::active()->get();

            if ($entries->isEmpty()) {
                return '';
            }

            $sections = $entries->groupBy('category');
            $output = "## Additional Knowledge\n";

            foreach ($sections as $category => $items) {
                $output .= "\n### " . ucfirst($category) . "\n";
                foreach ($items as $item) {
                    $output .= "- **{$item->title}**: {$item->content}\n";
                }
            }

            return $output;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Build treatment context from config.
     */
    private function buildTreatmentContext(array $categories): string
    {
        $lines = [];

        foreach ($categories as $categorySlug => $category) {
            foreach ($category['treatments'] as $slug => $treatment) {
                $price = $treatment['price'] ?? 'Contact for pricing';
                $lines[] = "- {$treatment['name']} (slug: \"{$slug}\", category: {$category['name']}, price: {$price})";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Fallback response when API is not configured or fails.
     */
    private function getFallbackResponse(string $userMessage): array
    {
        $message = strtolower($userMessage);

        // Simple keyword matching as fallback
        $matches = [
            'weight' => ['weight-loss', 'Weight Loss Programme'],
            'slim' => ['weight-loss', 'Weight Loss Programme'],
            'fat' => ['weight-loss', 'Weight Loss Programme'],
            'diet' => ['weight-loss', 'Weight Loss Programme'],
            'erectile' => ['erectile-dysfunction', 'Erectile Dysfunction'],
            'erection' => ['erectile-dysfunction', 'Erectile Dysfunction'],
            'premature' => ['premature-ejaculation', 'Premature Ejaculation'],
            'sti' => ['sti-treatment', 'STI Treatment'],
            'herpes' => ['genital-herpes', 'Genital Herpes'],
            'hair' => ['hair-loss', 'Hair Loss'],
            'bald' => ['hair-loss', 'Hair Loss'],
            'contracepti' => ['birth-control', 'Birth Control'],
            'birth control' => ['birth-control', 'Birth Control'],
            'pill' => ['birth-control', 'Birth Control'],
            'uti' => ['uti-treatment', 'UTI Treatment'],
            'urin' => ['uti-treatment', 'UTI Treatment'],
            'bladder' => ['uti-treatment', 'UTI Treatment'],
            'acne' => ['acne', 'Acne Treatment'],
            'pimple' => ['acne', 'Acne Treatment'],
            'skin' => ['acne', 'Acne Treatment'],
            'wrinkle' => ['anti-ageing', 'Anti-Ageing'],
            'aging' => ['anti-ageing', 'Anti-Ageing'],
            'ageing' => ['anti-ageing', 'Anti-Ageing'],
            'dark spot' => ['hyperpigmentation', 'Hyperpigmentation'],
            'pigment' => ['hyperpigmentation', 'Hyperpigmentation'],
            'cold sore' => ['cold-sores', 'Cold Sores'],
        ];

        foreach ($matches as $keyword => $match) {
            if (str_contains($message, $keyword)) {
                return [
                    'response' => "It sounds like you're looking for help with {$match[1]}. At Zapmed, our licensed doctors can assess your needs and prescribe the right treatment, delivered straight to your door. Start your assessment to get personalised medical guidance.",
                    'treatment_slug' => $match[0],
                    'treatment_name' => $match[1],
                ];
            }
        }

        // Generic fallback
        return [
            'response' => "I'd be happy to help you find the right treatment. At Zapmed, our licensed South African doctors provide online consultations for weight loss, sexual health, skincare, and general health concerns. If you're unsure where to start, a GP Consultation is a great first step — our doctors will guide you to the right treatment.",
            'treatment_slug' => 'gp-consult',
            'treatment_name' => 'GP Consultation',
        ];
    }

    /**
     * Check if the service is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}

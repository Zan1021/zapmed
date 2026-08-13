<?php

namespace App\Livewire\HelpCenter;

use App\Models\HelpArticle;
use App\Models\HelpArticleFeedback;
use Livewire\Component;

class Show extends Component
{
    public HelpArticle $article;
    public bool $hasVoted = false;
    public ?bool $voteValue = null;

    public function mount(string $slug): void
    {
        $this->article = HelpArticle::published()
            ->public()
            ->where('slug', $slug)
            ->firstOrFail();

        // Track view (deduplicate by session)
        $sessionKey = 'help_article_viewed_' . $this->article->id;
        if (!session()->has($sessionKey)) {
            $this->article->incrementViews();
            session()->put($sessionKey, true);
        }

        // Check if user already voted
        $feedbackKey = 'help_feedback_' . $this->article->id;
        if (session()->has($feedbackKey)) {
            $this->hasVoted = true;
            $this->voteValue = session()->get($feedbackKey);
        }
    }

    public function vote(bool $isHelpful): void
    {
        if ($this->hasVoted) {
            return;
        }

        HelpArticleFeedback::create([
            'help_article_id' => $this->article->id,
            'is_helpful' => $isHelpful,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
        ]);

        // Update cached counters
        if ($isHelpful) {
            $this->article->increment('helpful_yes');
        } else {
            $this->article->increment('helpful_no');
        }

        $this->hasVoted = true;
        $this->voteValue = $isHelpful;
        session()->put('help_feedback_' . $this->article->id, $isHelpful);
    }

    public function render()
    {
        $relatedArticles = HelpArticle::published()
            ->public()
            ->where('help_category_id', $this->article->help_category_id)
            ->where('id', '!=', $this->article->id)
            ->ordered()
            ->limit(5)
            ->get();

        // Build JSON-LD structured data
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => $this->article->title,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => strip_tags($this->article->body),
                    ],
                ],
            ],
        ];

        return view('livewire.help-center.show', [
            'relatedArticles' => $relatedArticles,
            'jsonLd' => $jsonLd,
        ])->layout('layouts.bare', ['title' => $this->article->meta_title ?? $this->article->title . ' | Zapmed Help']);
    }
}

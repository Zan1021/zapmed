<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'help_category_id',
        'title',
        'slug',
        'body',
        'excerpt',
        'status',
        'visibility',
        'sort_order',
        'views',
        'helpful_yes',
        'helpful_no',
        'meta_title',
        'meta_description',
        'author_id',
        'published_at',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'views' => 'integer',
        'helpful_yes' => 'integer',
        'helpful_no' => 'integer',
        'published_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'help_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(HelpArticleFeedback::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeInternal($query)
    {
        return $query->where('visibility', 'internal');
    }

    public function scopeSearch($query, string $term)
    {
        $term = '%' . strtolower($term) . '%';

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('lower(title) LIKE ?', [$term])
              ->orWhereRaw('lower(body) LIKE ?', [$term]);
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function incrementViews(): void
    {
        $this->increment('views');
    }

    public function getHelpfulPercentageAttribute(): ?int
    {
        $total = $this->helpful_yes + $this->helpful_no;

        if ($total === 0) {
            return null;
        }

        return (int) round(($this->helpful_yes / $total) * 100);
    }

    public function getExcerptTextAttribute(): string
    {
        if ($this->excerpt) {
            return $this->excerpt;
        }

        return \Illuminate\Support\Str::limit(strip_tags($this->body), 160);
    }
}

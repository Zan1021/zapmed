<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'sort_order',
        'is_active',
        'articles_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'articles_count' => 'integer',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(HelpArticle::class);
    }

    public function publishedArticles(): HasMany
    {
        return $this->hasMany(HelpArticle::class)->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function updateArticlesCount(): void
    {
        $this->update([
            'articles_count' => $this->articles()->where('status', 'published')->count(),
        ]);
    }
}

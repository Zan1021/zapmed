<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'consultation_id',
        'treatment_category',
        'treatment_slug',
        'rating',
        'comment',
        'would_recommend',
        'show_name',
        'is_approved',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'would_recommend' => 'boolean',
            'show_name' => 'boolean',
            'is_approved' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    /**
     * Scope to approved testimonials only.
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope to displayable testimonials (approved + 4-5 stars).
     */
    public function scopeDisplayable($query)
    {
        return $query->where('is_approved', true)->where('rating', '>=', 4);
    }

    /**
     * Scope by treatment category.
     */
    public function scopeForCategory($query, string $category)
    {
        return $query->where('treatment_category', $category);
    }

    /**
     * Get the display name for the testimonial.
     */
    public function getDisplayNameAttribute(): string
    {
        if (!$this->show_name) {
            return 'Verified Patient';
        }

        $patient = $this->patient;
        return $patient->first_name . ' ' . strtoupper(substr($patient->last_name, 0, 1)) . '.';
    }

    /**
     * Check if this category is sensitive (should be anonymous).
     */
    public function getIsSensitiveCategoryAttribute(): bool
    {
        return in_array($this->treatment_category, [
            'mens-health',
            'sexual-health',
            'womens-health',
        ]);
    }
}

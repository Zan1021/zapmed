<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One uploaded result file (uploaded by the patient or by a doctor). */
class BloodTestResult extends Model
{
    protected $fillable = ['blood_test_request_id', 'uploader_id', 'uploader_role', 'path', 'original_name', 'size_bytes'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(BloodTestRequest::class, 'blood_test_request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }
}

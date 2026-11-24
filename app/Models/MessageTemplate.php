<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Wording Operations saved for one message. No row = the built-in wording is used. */
class MessageTemplate extends Model
{
    protected $fillable = ['key', 'subject', 'body', 'is_enabled', 'version', 'updated_by'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'version' => 'integer'];
    }
}

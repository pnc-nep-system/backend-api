<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PolicyDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'authority',
        'version',
        'date',
        'status',
        'file_url',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $appends = ['file_url_full'];

    /**
     * The user who created this policy document.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the full URL for the file.
     * Converts a relative storage path to a full URL the frontend can use.
     */
    public function getFileUrlFullAttribute(): ?string
    {
        if (! $this->file_url) {
            return null;
        }

        // If it's already a full URL, return as-is
        if (str_starts_with($this->file_url, 'http://') || str_starts_with($this->file_url, 'https://')) {
            return $this->file_url;
        }

        // Convert relative storage path to full URL
        return Storage::disk('public')->url($this->file_url);
    }
}

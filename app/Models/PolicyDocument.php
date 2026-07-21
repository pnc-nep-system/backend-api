<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /**
     * The user who created this policy document.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
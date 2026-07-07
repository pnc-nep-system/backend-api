<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    // Loose defaults so this scaffold runs end-to-end — tighten per field
    // (required vs nullable, enum values, max lengths) before going to production.
    public static function validationRules(bool $update = false): array
    {
        return [
            'organisation_id' => 'sometimes',
            'name' => 'sometimes',
            'email' => 'sometimes',
            'password' => 'sometimes',
            'role' => 'sometimes',
            'status' => 'sometimes',
        ];
    }
}

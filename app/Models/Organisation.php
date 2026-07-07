<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organisation extends Model
{
    /** @use HasFactory<\Database\Factories\OrganisationFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_name',
        'email',
        'member_since',
        'status',
        'last_inactive_at',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function programmeEntries()
    {
        return $this->hasMany(ProgrammeEntry::class);
    }
}

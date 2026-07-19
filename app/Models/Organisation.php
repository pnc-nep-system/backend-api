<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organisation extends Model
{
    use HasFactory;

    protected $appends = ['logo_url'];

    protected $fillable = [
        'name',
        'contact_name',
        'email',
        'member_since',
        'status',
        'last_inactive_at',
        'logo_path',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function programmeEntries()
    {
        return $this->hasMany(ProgrammeEntry::class);
    }

    public function accounts()
    {
        return $this->hasMany(OrganisationAccount::class);
    }

    public function programmes()
    {
        return $this->hasMany(Programme::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? \Storage::disk('public')->url($this->logo_path) : null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    /** @use HasFactory<\Database\Factories\DistrictFactory> */
    use HasFactory;

    protected $fillable = [
        'province_id',
        'name',
        'district_name',
    ];

    public function getDistrictNameAttribute()
    {
        return $this->name;
    }

    public function setDistrictNameAttribute($value)
    {
        $this->attributes['name'] = $value;
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function locations()
    {
        return $this->hasMany(ProgrammeLocation::class);
    }
}

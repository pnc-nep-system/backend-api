<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammeGeography extends Model
{
    protected $table = 'programme_geography';

    protected $fillable = [
        'programme_entry_id',
        'province_id',
        'district_id',
        'country',
    ];

    public function programmeEntry()
    {
        return $this->belongsTo(ProgrammeEntry::class);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }
}
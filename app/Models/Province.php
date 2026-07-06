<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
<<<<<<< HEAD
    use HasFactory;

    protected $fillable = [
        'province_name',
    ];

    public function districts()
    {
        return $this->hasMany(District::class);
    }

    public function locations()
    {
        return $this->hasMany(ProgrammeLocation::class);
    }
=======
    /** @use HasFactory<\Database\Factories\ProvinceFactory> */
    use HasFactory;
>>>>>>> 721ff979a023e9640342596acae0b6087b112303
}

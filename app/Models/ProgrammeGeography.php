<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="ProgrammeGeography",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=15),
 *     @OA\Property(property="programme_entry_id", type="integer", example=3),
 *     @OA\Property(
 *         property="province",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Phnom Penh")
 *     ),
 *     @OA\Property(
 *         property="district",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer", example=4),
 *         @OA\Property(property="name", type="string", example="Chamkar Mon")
 *     ),
 *     @OA\Property(property="country", type="string", nullable=true, example="USA"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
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
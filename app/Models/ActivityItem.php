<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityItem extends Model
{
    /** @use HasFactory<\Database\Factories\ActivityItemFactory> */
    use HasFactory;

    protected $fillable = [
        'subcategory_id',
        'code',
        'label',
        'active',
        'is_other',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_other' => 'boolean',
    ];

    public function subcategory()
    {
        return $this->belongsTo(ActivitySubcategory::class, 'subcategory_id');
    }

    public function programmeActivities()
    {
        return $this->hasMany(ProgrammeActivity::class);
    }

    public function taxonomyOtherQueues()
    {
        return $this->hasMany(TaxonomyOtherQueue::class, 'item_id');
    }
}

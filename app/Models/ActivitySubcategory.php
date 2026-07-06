<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivitySubcategory extends Model
{
    /** @use HasFactory<\Database\Factories\ActivitySubcategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'code',
        'label',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(ActivityCategory::class, 'category_id');
    }

    public function items()
    {
        return $this->hasMany(ActivityItem::class, 'subcategory_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityCategory extends Model
{
    /** @use HasFactory<\Database\Factories\ActivityCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function subcategories()
    {
        return $this->hasMany(ActivitySubcategory::class, 'category_id');
    }
}

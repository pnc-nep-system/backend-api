<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgrammeEntry extends Model
{
    /** @use HasFactory<\Database\Factories\ProgrammeEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'budget_band_id',
        'programme_name',
        'start_year',
        'end_year',
        'ongoing',
        'fte_staff',
        'indirect_beneficiaries',
        'direct_beneficiaries',
        'method',
        'verified_date',
        'last_updated_at',
        'last_updated_by',
    ];

    protected $casts = [
        'ongoing' => 'boolean',
        'fte_staff' => 'decimal:2',
        'verified_date' => 'date',
        'last_updated_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            $model->last_updated_at = now();

            if (auth()->check()) {
                $model->last_updated_by = auth()->id();
            }
        });
    }
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function budgetBand()
    {
        return $this->belongsTo(BudgetBand::class);
    }

    public function keywords()
    {
        return $this->hasMany(EntryKeyword::class);
    }

    public function locations()
    {
        return $this->hasMany(ProgrammeLocation::class);
    }

    public function activities()
    {
        return $this->hasMany(ProgrammeActivity::class);
    }

    public function governmentAgreements()
    {
        return $this->hasMany(GovernmentAgreement::class);
    }

    public function advisoryRecommendations()
    {
        return $this->hasMany(AdvisoryRecommendation::class);
    }

    public function taxonomyOtherQueues()
    {
        return $this->hasMany(TaxonomyOtherQueue::class);
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}

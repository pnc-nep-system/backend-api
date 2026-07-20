<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvisoryNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'assign_to_staff_user_id',
        'coordinator_id',
        'submitting_party',
        'document_name',
        'analysis_scope',
        'analysis_scope_detail',
        'status',
        'section_profile',
        'section_gaps',
        'section_coordinators_notes',
        'final_note_file',
        'submitted_at',
        'delivered_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function staffUser()
    {
        return $this->belongsTo(StaffUser::class, 'assign_to_staff_user_id');
    }

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function recommendations()
    {
        return $this->hasMany(AdvisoryRecommendation::class);
    }
}

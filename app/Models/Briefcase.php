<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Briefcase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'freelancer_id',
        'title',
        'description',
        'image_url',
        'project_url',
    ];

    protected $casts = [
        'freelancer_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Freelancer profile that owns this briefcase project.
     */
    public function freelancerProfile(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_id');
    }
}
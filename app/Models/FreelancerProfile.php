<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreelancerProfile extends Model
{
    use HasFactory, SoftDeletes;

    public const WORK_MODES = [
        'remote',
        'on_site',
        'hybrid',
        'home_service',
    ];

    public const RATE_TYPES = [
        'hourly',
        'daily',
        'project',
        'negotiable',
    ];

    protected $fillable = [
        'user_id',
        'description',
        'specialty',
        'location',
        'service_area',
        'work_mode',
        'experience',
        'rate_type',
        'rate',
        'languages',
        'website',
        'facebook',
        'instagram',
        'linkedin',
        'github',
        'portfolio_url',
        'available',
        'average_rate',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'available' => 'boolean',
        'rate' => 'decimal:2',
        'average_rate' => 'decimal:2',
        'languages' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'freelancer_id');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class, 'freelancer_id');
    }

    public function briefcases(): HasMany
    {
        return $this->hasMany(Briefcase::class, 'freelancer_id');
    }
}
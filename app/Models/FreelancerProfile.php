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

    protected $fillable = [
        'user_id',
        'description',
        'specialty',
        'hourly_rate',
        'location',
        'available',
        'average_rate',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'available' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'average_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * User owner of the freelancer profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Services published by the freelancer profile.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'freelancer_id');
    }

    /**
     * Availabilities registered by the freelancer profile.
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class, 'freelancer_id');
    }

    /**
     * Briefcases / portfolio items of the freelancer profile.
     */
    public function briefcases(): HasMany
    {
        return $this->hasMany(Briefcase::class, 'freelancer_id');
    }
}
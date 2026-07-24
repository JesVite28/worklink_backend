<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'freelancer_id',
        'title',
        'description',
        'price',
        'category',
        'location',
        'is_active',
    ];

    protected $casts = [
        'freelancer_id' => 'integer',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Freelancer profile that owns this service.
     */
    public function freelancerProfile(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_id');
    }

    /**
     * Solicitudes de contratación asociadas a este servicio.
     */
    public function contractRequests(): HasMany
    {
        return $this->hasMany(ContractRequest::class, 'service_id');
    }
}
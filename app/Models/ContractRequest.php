<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contract_requests';

    protected $fillable = [
        'client_id',
        'freelancer_id',
        'service_id',
        'description',
        'budget',
        'status',
    ];

    protected $casts = [
        'client_id' => 'integer',
        'freelancer_id' => 'integer',
        'service_id' => 'integer',
        'budget' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * User who creates the contract request.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Freelancer profile that receives the request.
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_id');
    }

    /**
     * Service requested by the client.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
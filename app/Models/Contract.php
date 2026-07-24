<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_IN_PROCESS = 'in_process';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELED = 'canceled';

    public const STATUSES = [
        self::STATUS_IN_PROCESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELED,
    ];

    protected $table = 'contracts';

    protected $fillable = [
        'request_id',
        'start_date',
        'end_date',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'request_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function contractRequest(): BelongsTo
    {
        return $this->belongsTo(
            ContractRequest::class,
            'request_id'
        );
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(
            Review::class,
            'contract_id'
        );
    }
}
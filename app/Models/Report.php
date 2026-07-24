<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_RESOLVED = 'resolved';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_REVIEWED,
        self::STATUS_RESOLVED,
    ];

    protected $fillable = [
        'reporter_id',
        'reported_id',
        'reason',
        'description',
        'status',
    ];

    protected $casts = [
        'reporter_id' => 'integer',
        'reported_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reporter_id'
        );
    }

    public function reported(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reported_id'
        );
    }
}
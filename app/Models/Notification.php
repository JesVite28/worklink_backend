<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_MESSAGE = 'message';
    public const TYPE_APPLICATION_RECEIVED = 'application_received';
    public const TYPE_APPLICATION_ACCEPTED = 'application_accepted';
    public const TYPE_APPLICATION_REJECTED = 'application_rejected';
    public const TYPE_CONTRACT_REQUEST = 'contract_request';
    public const TYPE_CONTRACT_REQUEST_ACCEPTED = 'contract_request_accepted';
    public const TYPE_CONTRACT_REQUEST_REJECTED = 'contract_request_rejected';
    public const TYPE_CONTRACT_REQUEST_CANCELED = 'contract_request_canceled';
    public const TYPE_CONTRACT_CREATED = 'contract_created';
    public const TYPE_CONTRACT_COMPLETED = 'contract_completed';
    public const TYPE_CONTRACT_CANCELED = 'contract_canceled';
    public const TYPE_REVIEW_RECEIVED = 'review_received';

    public const TYPES = [
        self::TYPE_MESSAGE,
        self::TYPE_APPLICATION_RECEIVED,
        self::TYPE_APPLICATION_ACCEPTED,
        self::TYPE_APPLICATION_REJECTED,
        self::TYPE_CONTRACT_REQUEST,
        self::TYPE_CONTRACT_REQUEST_ACCEPTED,
        self::TYPE_CONTRACT_REQUEST_REJECTED,
        self::TYPE_CONTRACT_REQUEST_CANCELED,
        self::TYPE_CONTRACT_CREATED,
        self::TYPE_CONTRACT_COMPLETED,
        self::TYPE_CONTRACT_CANCELED,
        self::TYPE_REVIEW_RECEIVED,
    ];

    protected $fillable = [
        'user_id',
        'type',
        'message',
        'is_read',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }
}
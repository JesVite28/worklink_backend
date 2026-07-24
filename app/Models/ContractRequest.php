<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractRequest extends Model
{
    use HasFactory, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Estados
    |--------------------------------------------------------------------------
    */

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELED = 'canceled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELED,
    ];

    public const FINAL_STATUSES = [
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELED,
    ];

    /*
    |--------------------------------------------------------------------------
    | Configuración
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Usuario que envió la solicitud.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'client_id',
        );
    }

    /**
     * Perfil freelancer que recibe la solicitud.
     */
    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(
            FreelancerProfile::class,
            'freelancer_id',
        );
    }

    /**
     * Servicio que se desea contratar.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Service::class,
            'service_id',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Comprobaciones de estado
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function isFinalized(): bool
    {
        return in_array(
            $this->status,
            self::FINAL_STATUSES,
            true,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending($query)
    {
        return $query->where(
            'status',
            self::STATUS_PENDING,
        );
    }

    public function scopeAccepted($query)
    {
        return $query->where(
            'status',
            self::STATUS_ACCEPTED,
        );
    }

    public function scopeRejected($query)
    {
        return $query->where(
            'status',
            self::STATUS_REJECTED,
        );
    }

    public function scopeCanceled($query)
    {
        return $query->where(
            'status',
            self::STATUS_CANCELED,
        );
    }
}
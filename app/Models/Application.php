<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
    ];

    protected $table = 'applications';

    protected $fillable = [
        'vacancy_id',
        'freelancer_id',
        'message',
        'status',
    ];

    protected $casts = [
        'vacancy_id' => 'integer',
        'freelancer_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Vacante a la que pertenece la postulación.
     */
    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(
            Vacancy::class,
            'vacancy_id'
        );
    }

    /**
     * Perfil freelancer que realizó la postulación.
     */
    public function freelancerProfile(): BelongsTo
    {
        return $this->belongsTo(
            FreelancerProfile::class,
            'freelancer_id'
        );
    }
}
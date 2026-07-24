<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vacancy extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_PAUSED,
        self::STATUS_CLOSED,
    ];

    protected $table = 'vacancies';

    protected $fillable = [
        'company_id',
        'title',
        'description',
        'category',
        'location',
        'salary',
        'status',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'salary' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Perfil empresarial propietario de la vacante.
     */
    public function companyProfile(): BelongsTo
    {
        return $this->belongsTo(
            CompanyProfile::class,
            'company_id'
        );
    }

    /**
     * Postulaciones recibidas por la vacante.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(
            Application::class,
            'vacancy_id'
        );
    }

    /**
     * Vacantes abiertas pertenecientes a empresas activas.
     */
    public function scopePubliclyAvailable(
        Builder $query
    ): Builder {
        return $query
            ->where('status', self::STATUS_OPEN)
            ->whereHas(
                'companyProfile',
                function (Builder $companyQuery) {
                    $companyQuery->fromActiveCompanies();
                }
            );
    }
}
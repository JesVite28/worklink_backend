<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'company_profiles';

    /**
     * average_rate es calculado por el backend.
     */
    protected $fillable = [
        'user_id',
        'company_name',
        'description',
        'industry',
        'location',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'average_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Usuario propietario del perfil empresarial.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Vacantes publicadas por la empresa.
     */
    public function vacancies(): HasMany
    {
        return $this->hasMany(Vacancy::class, 'company_id');
    }


    /**
     * Calificaciones recibidas por el usuario empresa.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(
            Review::class,
            'evaluated_id',
            'user_id'
        );
    }

    /**
     * Perfiles pertenecientes a empresas activas.
     */
    public function scopeFromActiveCompanies(Builder $query): Builder
    {
        return $query->whereHas('user', function (Builder $userQuery) {
            $userQuery
                ->where('is_active', true)
                ->whereHas('roles', function (Builder $roleQuery) {
                    $roleQuery->where('name', 'empresa');
                });
        });
    }
}
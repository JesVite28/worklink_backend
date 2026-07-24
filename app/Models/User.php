<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'last_name',
        'maternal_last_name',
        'email',
        'password',
        'phone',
        'profile_photo',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'roles_users',
            'user_id',
            'role_id'
        )
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    /**
     * Perfil de empresa asociado al usuario.
     */
    public function companyProfile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class, 'user_id');
    }


    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }


    public function reviewsGiven(): HasMany
    {
        return $this->hasMany(
            Review::class,
            'evaluator_id'
        );
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(
            Review::class,
            'evaluated_id'
        );
    }


    public function notifications(): HasMany
    {
        return $this->hasMany(
            Notification::class,
            'user_id'
        );
    }


    public function reportsMade(): HasMany
    {
        return $this->hasMany(
            Report::class,
            'reporter_id'
        );
    }

    public function reportsReceived(): HasMany
    {
        return $this->hasMany(
            Report::class,
            'reported_id'
        );
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public function hasAllRoles(array $roles): bool
    {
        return collect($roles)->every(
            fn ($role) => $this->hasRole($role)
        );
    }

    public function mainRole(): ?Role
    {
        return $this->roles()->first();
    }
}
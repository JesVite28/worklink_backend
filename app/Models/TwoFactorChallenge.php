<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorChallenge extends Model
{
    use HasFactory;

    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_ENABLE = 'enable';

    public const PURPOSE_CHANGE_PASSWORD =
        'change_password';

    protected $fillable = [
        'user_id',
        'purpose',
        'token_hash',
        'code_hash',
        'attempts',
        'expires_at',
        'verified_at',
    ];

    protected $hidden = [
        'token_hash',
        'code_hash',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id',
        );
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function hasExceededAttempts(
        int $maximumAttempts = 5,
    ): bool {
        return $this->attempts >=
            $maximumAttempts;
    }
}
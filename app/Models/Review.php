<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_id',
        'evaluator_id',
        'evaluated_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'contract_id' => 'integer',
        'evaluator_id' => 'integer',
        'evaluated_id' => 'integer',
        'rating' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(
            Contract::class,
            'contract_id'
        );
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'evaluator_id'
        );
    }

    public function evaluated(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'evaluated_id'
        );
    }
}
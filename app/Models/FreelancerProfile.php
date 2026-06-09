<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class FreelancerProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'description',
        'specialty',
        'hourly_rate',
        'location',
        'available',
        'average_rate',
    ];

    protected $casts = [
        'available' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'average_rate' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'freelancer_id');
    }

    public function availabilities()
    {
        return $this->hasMany(Availability::class, 'freelancer_id');
    }

    public function briefcases()
    {
        return $this->hasMany(Briefcase::class, 'freelancer_id');
    }
}

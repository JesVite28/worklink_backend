<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Briefcase extends Model
{
    use softDeletes, HasFactory;

    protected $fillable = [
        'freelancer_id',
        'title',
        'description',
        'url_image',
        'url_proyecto',
    ];

    public function freelancerProfile()
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_id');
    }
}

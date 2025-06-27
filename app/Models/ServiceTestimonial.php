<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceTestimonial extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'photo',
        'message',
        'home_service_id',
    ];

    public function homeService(): BelongsTo
    {
        return $this->belongsTo(HomeService::class, 'home_service_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionDetails extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'price',
        'home_service_id',
        'booking_transaction_id',
    ];

    public function bookingTransaction()
    {
        return $this->belongsTo(BookingTransaction::class, 'booking_transaction_id');
    }

    public function homeService()
    {
        return $this->belongsTo(HomeService::class, 'home_service_id');
    }
}

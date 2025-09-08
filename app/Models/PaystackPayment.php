<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Model;

class PaystackPayment extends Model
{
    protected $table = 'paystack_payments';

    protected $fillable = [
        'order_id',
        'reference',
        'authorization_url',
        'access_code',
        'status',
        'amount',
        'fees',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
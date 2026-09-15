<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'patient_id',
        'appointment_id',
        'total_amount',
        'discount',
        'final_amount',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}

<?php

namespace App\Models;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Concerns\HasAuditLogs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory, HasAuditLogs;

    protected $fillable = [
        'appointment_id',
        'patient_id',
        'amount',
        'discount',
        'final_amount',
        'status',
        'payment_method',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'discount' => 'integer',
        'final_amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}

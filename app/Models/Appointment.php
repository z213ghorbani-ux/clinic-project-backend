<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'service_id',
        'invoice_id',
        'start_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'appointment_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'appointment_id');
    }
}

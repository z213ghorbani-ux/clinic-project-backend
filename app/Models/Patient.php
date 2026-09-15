<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = [
        'full_name',
        'mobile',
        'national_code',
        'file_number',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'patient_id');
    }
}

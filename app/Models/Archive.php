<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Archive extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_name',
        'national_code',
        'file_number',
        'mobile',
        'issued_by',
        'issued_by_name',
        'issued_at',
        'form_data',
        'attachments',
    ];

    protected $casts = [
        'form_data' => 'array',
        'attachments' => 'array',
        'issued_at' => 'datetime',
    ];
}

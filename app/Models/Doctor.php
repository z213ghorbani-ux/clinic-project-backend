<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasAuditLogs;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Doctor extends Model
{
    use HasFactory;
    use HasAuditLogs;


    protected $fillable = [
        'name',
        'medical_council_code',
        'specialty',
        'medical_code', 
        'mobile',
        'stamp_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}

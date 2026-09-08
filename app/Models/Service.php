<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasAuditLogs;
use Illuminate\Database\Eloquent\Relations\HasMany;



class Service extends Model
{
    use HasFactory;
    use HasAuditLogs;


    protected $fillable = [
        'name',
        'price',
        'duration_minutes',
        'description',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}

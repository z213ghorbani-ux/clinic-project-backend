<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait HasAuditLogs
{
    protected static function bootHasAuditLogs(): void
    {
        static::created(function (Model $model): void {
            $model->writeAuditLog('created');
        });

        static::updated(function (Model $model): void {
            $model->writeAuditLog('updated');
        });

        static::deleted(function (Model $model): void {
            $model->writeAuditLog('deleted');
        });
    }

    public function writeAuditLog(string $event): void
    {
        $oldValues = null;
        $newValues = null;

        if ($event === 'created') {
            $newValues = $this->getAttributes();
        } elseif ($event === 'updated') {
            $oldValues = array_intersect_key(
                $this->getOriginal(),
                $this->getChanges()
            );
            $newValues = $this->getChanges();
        } elseif ($event === 'deleted') {
            $oldValues = $this->getOriginal();
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}

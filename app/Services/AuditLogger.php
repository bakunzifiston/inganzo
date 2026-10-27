<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function log(string $action, ?Model $subject = null, array $changes = [], ?User $actor = null): AuditLog
    {
        $actor ??= Auth::user();
        $userAgent = app()->runningInConsole() ? 'console' : Request::userAgent();

        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes ?: null,
            'ip_address' => app()->runningInConsole() ? null : Request::ip(),
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Log the dirty attributes of a model that is about to be saved.
     *
     * @param  list<string>  $hidden  attributes whose values must not be written to the log
     */
    public function logChanges(string $action, Model $model, array $hidden = []): ?AuditLog
    {
        $changes = [];

        foreach ($model->getDirty() as $attribute => $new) {
            $old = $model->getOriginal($attribute);
            $changes[$attribute] = in_array($attribute, $hidden, true)
                ? ['old' => '[redacted]', 'new' => '[redacted]']
                : ['old' => $old instanceof \BackedEnum ? $old->value : $old, 'new' => $new];
        }

        return $changes ? $this->log($action, $model, $changes) : null;
    }
}

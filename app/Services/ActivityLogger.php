<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ActivityLogger
{
    public static function log(
        string $action,
        ?string $description = null,
        ?Model $model = null,
        array $properties = []
    ): void {
        try {
            $data = [
                'user_id' => Auth::id(),
                'action' => $action,
                'model_type' => $model ? get_class($model) : null,
                'model_id' => $model ? $model->getKey() : null,
                'description' => $description,
                'properties' => $properties ?: null,
            ];

            if (Schema::hasColumn('activity_logs', 'ip_address')) {
                $data['ip_address'] = request()?->ip();
            }

            if (Schema::hasColumn('activity_logs', 'user_agent')) {
                $data['user_agent'] = request()?->userAgent();
            }

            ActivityLog::create($data);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

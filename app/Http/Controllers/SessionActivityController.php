<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionActivityController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        $request->session()->put('last_activity_at', now()->timestamp);

        $minutes = (int) Setting::getValue('auto_logout_minutes', 30);
        $minutes = max(1, min(1440, $minutes));
        $warningSeconds = (int) Setting::getValue('auto_logout_warning_seconds', 60);
        $warningSeconds = max(10, min(600, $warningSeconds));

        return response()->json([
            'ok' => true,
            'server_time' => now()->toDateTimeString(),
            'auto_logout' => [
                'enabled' => (string) Setting::getValue('auto_logout_enabled', '0') === '1',
                'timeout_seconds' => $minutes * 60,
                'warning_seconds' => $warningSeconds,
            ],
        ]);
    }
}

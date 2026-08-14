<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    /**
     * Application health check endpoint.
     *
     * Checks: database connectivity, cache (read/write), storage (disk writable).
     * Returns 200 if all healthy, 503 if any check fails.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        // Database
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'ok', 'connection' => config('database.default')];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $healthy = false;
        }

        // Cache
        try {
            $key = 'health_check_' . time();
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);
            $checks['cache'] = ['status' => $value === 'ok' ? 'ok' : 'fail', 'driver' => config('cache.default')];
            if ($value !== 'ok') {
                $healthy = false;
            }
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $healthy = false;
        }

        // Storage
        try {
            $testFile = 'health_check_test.txt';
            Storage::disk('local')->put($testFile, 'ok');
            $content = Storage::disk('local')->get($testFile);
            Storage::disk('local')->delete($testFile);
            $checks['storage'] = ['status' => $content === 'ok' ? 'ok' : 'fail'];
            if ($content !== 'ok') {
                $healthy = false;
            }
        } catch (\Throwable $e) {
            $checks['storage'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $healthy = false;
        }

        // App info
        $checks['app'] = [
            'status' => 'ok',
            'environment' => app()->environment(),
            'debug' => config('app.debug'),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
        ];

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}

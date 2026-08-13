<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LogMedicalAccess
{
    /**
     * Log access to medical data (consultations, prescriptions, patient records).
     * Required for POPIA compliance and medical audit trails.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log for authenticated users accessing medical routes
        if (!Auth::check()) {
            return $response;
        }

        $path = $request->path();

        // Define medical data routes to log
        $medicalRoutes = [
            'doctor/consultation',
            'doctor/prescription',
            'admin/appointments',
            'prescriptions',
            'patient/appointments',
        ];

        $shouldLog = false;
        foreach ($medicalRoutes as $route) {
            if (str_starts_with($path, $route)) {
                $shouldLog = true;
                break;
            }
        }

        if ($shouldLog && $response->isSuccessful()) {
            DB::table('audit_logs')->insert([
                'user_id' => Auth::id(),
                'action' => 'medical_data_access',
                'resource_type' => $this->inferModelType($path),
                'resource_id' => $this->extractModelId($request),
                'description' => Auth::user()->role->value . ' accessed ' . $path,
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 255),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $response;
    }

    private function inferModelType(string $path): ?string
    {
        if (str_contains($path, 'consultation')) return 'App\\Models\\Consultation';
        if (str_contains($path, 'prescription')) return 'App\\Models\\Prescription';
        if (str_contains($path, 'appointment')) return 'App\\Models\\Appointment';
        return null;
    }

    private function extractModelId(Request $request): ?int
    {
        // Extract numeric ID from route parameters
        foreach ($request->route()?->parameters() ?? [] as $param) {
            if (is_numeric($param)) return (int) $param;
            if (is_object($param) && method_exists($param, 'getKey')) return $param->getKey();
        }
        return null;
    }
}

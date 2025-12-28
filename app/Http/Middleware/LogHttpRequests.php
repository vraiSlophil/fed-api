<?php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogHttpRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        $statusCode = $response->getStatusCode();
        
        // Logger les codes d'erreur intéressants pour la sécurité
        if (in_array($statusCode, [401, 403, 404, 429, 500, 502, 503])) {
            $logData = [
                'timestamp' => now()->toIso8601String(),
                'status' => $statusCode,
                'method' => $request->method(),
                'path' => $request->path(),
                'full_url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
            ];
            
            // Si utilisateur authentifié, ajouter son ID
            if ($request->user()) {
                $logData['user_id'] = $request->user()->id;
            }
            
            // Logger selon la sévérité
            match(true) {
                $statusCode >= 500 => Log::emergency('HTTP 500 Error', $logData),
                $statusCode === 403 => Log::warning('HTTP 403 Forbidden', $logData),
                $statusCode === 401 => Log::warning('HTTP 401 Unauthorized', $logData),
                $statusCode === 429 => Log::warning('HTTP 429 Too Many Requests', $logData),
                default => Log::info("HTTP {$statusCode}", $logData),
            };
        }
        
        return $response;
    }
}
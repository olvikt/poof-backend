<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Courier\Observability\CourierRuntimeRequestCollector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ObserveCourierRuntimeEndpoint
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $endpointName): Response
    {
        $startedAt = microtime(true);
        $collector = app(CourierRuntimeRequestCollector::class);
        $collector->reset();

        $response = $next($request);
        $httpStatusCode = $response->getStatusCode();

        $collector->observeEndpoint($endpointName, 'api', $startedAt, [
            'http_status_code' => $httpStatusCode,
            'http_status_family' => $httpStatusCode >= 400 ? 'error' : 'ok',
        ]);

        return $response;
    }
}

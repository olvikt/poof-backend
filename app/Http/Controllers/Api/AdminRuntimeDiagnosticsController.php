<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class AdminRuntimeDiagnosticsController extends Controller
{
    public function show(): JsonResponse
    {
        abort_if(! auth()->user()?->isAdmin(), 403);

        $summaries = collect(File::glob(base_path('docs/release-summaries/release-*.md')))
            ->map(fn (string $path): array => [
                'path' => $path,
                'modified_at' => File::lastModified($path),
            ])
            ->sortByDesc('modified_at')
            ->values();

        $latestSummary = $summaries->first();

        return response()->json([
            'runtime_mode' => app()->environment(),
            'queue_driver' => (string) config('queue.default'),
            'cache_driver' => (string) config('cache.default'),
            'release_summary' => $latestSummary ? [
                'file' => basename((string) $latestSummary['path']),
                'modified_at' => (int) $latestSummary['modified_at'],
            ] : null,
        ]);
    }
}


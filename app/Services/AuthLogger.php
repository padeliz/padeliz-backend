<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class AuthLogger
{
    private $baseDir;

    public function __construct()
    {
        $this->baseDir = storage_path('logs/auth');

        // Ensure the auth directory exists
        if (!File::exists($this->baseDir)) {
            File::makeDirectory($this->baseDir, 0775, true);
        }
    }

    public function logError(\Exception $e, array $context = [])
    {
        $now = Carbon::now();
        $filename = $now->format('Y-m-d') . '.log';
        $path = $this->baseDir . '/' . $filename;

        $logEntry = [
            'timestamp' => $now->toIso8601String(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'context' => $context
        ];

        File::append(
            $path,
            json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
        );
    }
}

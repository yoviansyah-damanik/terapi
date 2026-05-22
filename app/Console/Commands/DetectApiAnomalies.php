<?php

namespace App\Console\Commands;

use App\Constants\SecurityConfig;
use App\Models\Api\ApiLog;
use App\Models\Api\ApiSecurityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DetectApiAnomalies extends Command
{
    protected $signature   = 'api:detect-anomalies';
    protected $description = 'Deteksi anomali traffic API dan catat ke database';

    public function handle(): int
    {
        if (!SecurityConfig::bool('api.security.anomaly.enabled')) {
            $this->info('Anomaly detection dinonaktifkan via konfigurasi.');
            return Command::SUCCESS;
        }

        $window = SecurityConfig::int('api.security.anomaly.window_minutes');
        $since  = now()->subMinutes($window);

        $this->detectHighErrorRate($since, $window);
        $this->detectHighVolume($since, $window);
        $this->detectBruteForce($since, $window);

        $this->info('Deteksi anomali selesai pada ' . now()->toDateTimeString());

        return Command::SUCCESS;
    }

    private function detectHighErrorRate(\Carbon\Carbon $since, int $window): void
    {
        $minRequests = SecurityConfig::int('api.security.anomaly.min_requests');
        $threshold   = SecurityConfig::int('api.security.anomaly.error_rate_pct');

        $results = ApiLog::where('created_at', '>=', $since)
            ->select(
                'ip_address',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN response_status >= 400 THEN 1 ELSE 0 END) as errors')
            )
            ->groupBy('ip_address')
            ->having('total', '>=', $minRequests)
            ->having(DB::raw('(errors * 100.0 / total)'), '>=', $threshold)
            ->get();

        foreach ($results as $row) {
            $errorRate = round(($row->errors / $row->total) * 100, 1);

            $exists = ApiSecurityLog::where('type', 'anomaly_high_failure')
                ->where('ip_address', $row->ip_address)
                ->where('created_at', '>=', $since)
                ->whereNull('resolved_at')
                ->exists();

            if (!$exists) {
                ApiSecurityLog::create([
                    'type'       => 'anomaly_high_failure',
                    'ip_address' => $row->ip_address,
                    'detail'     => [
                        'window_minutes'  => $window,
                        'total_requests'  => $row->total,
                        'error_count'     => $row->errors,
                        'error_rate_pct'  => $errorRate,
                        'threshold_pct'   => $threshold,
                        'min_requests'    => $minRequests,
                    ],
                ]);
            }
        }
    }

    private function detectHighVolume(\Carbon\Carbon $since, int $window): void
    {
        $threshold = SecurityConfig::int('api.security.anomaly.high_volume');

        $results = ApiLog::where('created_at', '>=', $since)
            ->select('ip_address', DB::raw('COUNT(*) as total'))
            ->groupBy('ip_address')
            ->having('total', '>=', $threshold)
            ->get();

        foreach ($results as $row) {
            $exists = ApiSecurityLog::where('type', 'anomaly_high_volume')
                ->where('ip_address', $row->ip_address)
                ->where('created_at', '>=', $since)
                ->whereNull('resolved_at')
                ->exists();

            if (!$exists) {
                ApiSecurityLog::create([
                    'type'       => 'anomaly_high_volume',
                    'ip_address' => $row->ip_address,
                    'detail'     => [
                        'window_minutes'  => $window,
                        'total_requests'  => $row->total,
                        'threshold'       => $threshold,
                    ],
                ]);
            }
        }
    }

    private function detectBruteForce(\Carbon\Carbon $since, int $window): void
    {
        $threshold = SecurityConfig::int('api.security.anomaly.brute_force');

        $results = ApiLog::where('created_at', '>=', $since)
            ->where('path', 'like', '%auth/token%')
            ->where('response_status', 401)
            ->select('ip_address', DB::raw('COUNT(*) as attempts'))
            ->groupBy('ip_address')
            ->having('attempts', '>=', $threshold)
            ->get();

        foreach ($results as $row) {
            $exists = ApiSecurityLog::where('type', 'anomaly_brute_force')
                ->where('ip_address', $row->ip_address)
                ->where('created_at', '>=', $since)
                ->whereNull('resolved_at')
                ->exists();

            if (!$exists) {
                ApiSecurityLog::create([
                    'type'       => 'anomaly_brute_force',
                    'ip_address' => $row->ip_address,
                    'detail'     => [
                        'window_minutes' => $window,
                        'attempts'       => $row->attempts,
                        'threshold'      => $threshold,
                        'path'           => 'api/auth/token',
                    ],
                ]);
            }
        }
    }
}

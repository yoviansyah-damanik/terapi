<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\Simrs\Employee as SimrsEmployee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSimrsEmployeeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public string $employeeId,
    ) {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        try {
            $simrsEmployee = SimrsEmployee::find($this->employeeId);

            if (!$simrsEmployee) {
                Log::warning("SyncSimrsEmployee: Employee not found in SIMRS", [
                    'employee_id' => $this->employeeId,
                ]);
                return;
            }

            Employee::createFromSimrs($simrsEmployee);

            Log::info("SyncSimrsEmployee: Successfully synced", [
                'employee_id' => $this->employeeId,
            ]);

        } catch (\Exception $e) {
            Log::error("SyncSimrsEmployee: Error syncing employee", [
                'employee_id' => $this->employeeId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function tags(): array
    {
        return ['simrs', 'employee-sync', 'employee_id:' . $this->employeeId];
    }
}

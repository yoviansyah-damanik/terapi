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

class SyncBatchSimrsEmployeesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;

    public function __construct(
        public array $employeeIds = [],
        public bool $syncAll = false,
        public int $limit = 100,
    ) {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        Log::info("SyncBatchSimrsEmployees: Starting batch sync", [
            'sync_all' => $this->syncAll,
            'employee_ids' => count($this->employeeIds),
            'limit' => $this->limit,
        ]);

        $existingIds = Employee::pluck('employee_id')->toArray();

        $query = SimrsEmployee::query()
            ->whereNotIn('id', $existingIds);

        if (!$this->syncAll && !empty($this->employeeIds)) {
            $query->whereIn('id', $this->employeeIds);
        }

        $employees = $query->limit($this->limit)->get();

        $dispatched = 0;
        foreach ($employees as $employee) {
            SyncSimrsEmployeeJob::dispatch($employee->id)
                ->delay(now()->addSeconds($dispatched));
            $dispatched++;
        }

        Log::info("SyncBatchSimrsEmployees: Dispatched jobs", [
            'count' => $dispatched,
        ]);
    }

    public function tags(): array
    {
        return ['simrs', 'batch-sync', 'employee'];
    }
}

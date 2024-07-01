<?php

namespace App\Jobs;

use App\Core\ProgressiveBatchJob;
use Illuminate\Support\Facades\Log;

class SimpleJob extends ProgressiveBatchJob
{

    /**
     * Create a new job instance.
     */
    public function __construct(public ?string $random_string)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function run(): void
    {
        if (strlen($this->random_string) != 30) {
            Log::info(sprintf('%s [%s] FAILS', class_basename($this), $this->random_string));
            throw new \Exception('Random string must be 30 characters long');
        }

        Log::info(sprintf('%s [%s] RAN', class_basename($this), $this->random_string));
    }
}

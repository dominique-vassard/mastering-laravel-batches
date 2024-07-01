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
        Log::info(sprintf('%s [%s] RAN', class_basename($this), $this->random_string));
    }
}

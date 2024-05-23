<?php

namespace App\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExampleJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $number)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // If batch is cancelled, stop execution
        if ($this->batch()->cancelled()) {
            Log::info(sprintf('%s [%d] CANCELLED: Do nothing', class_basename($this), $this->number));
            return;
        }

        sleep(5);
        Log::info(sprintf('%s [%d] RAN', class_basename($this), $this->number));
    }
}

<?php

namespace App\Jobs;

use App\Events\Batch\BatchJobFailed;
use App\Models\BatchQueue;
use App\Repositories\RedisBatchQueueRepository;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Throwable;

class SimpleJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $random_string)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // throw new Exception("FAILED");

            Log::info(sprintf('%s [%s] RAN', class_basename($this), $this->random_string));
        } catch (Throwable $e) {
            if ($this->batch()) {
                $args = [];
                $job_reflection = new ReflectionClass($this);
                $constructor = $job_reflection->getConstructor();
                foreach ($constructor->getParameters() as $param) {
                    $param_name = $param->getName();
                    $args[$param->getName()] = $this->$param_name;
                }

                $job = [
                    'uuid' => $this->job->uuid(),
                    'error' => $e->getMessage(),
                    'class' => get_class($this),
                    'args' => $args,
                    'failed_at' => Carbon::now(),
                ];

                event(new BatchJobFailed($this->batch(), $job));
            }
        } finally {
            if ($this->batch() && !$this->batch()->cancelled()) {
                $batch_queue = new BatchQueue($this->batch(), new RedisBatchQueueRepository());
                $next_item  = $batch_queue->pop();
                if ($next_item) {
                    $this->batch()->add(new static($next_item));
                }
            }
        }
    }
}

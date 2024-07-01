<?php

namespace App\Core;

use App\Events\Batch\BatchJobFailed;
use App\Models\BatchQueue;
use App\Repositories\RedisBatchQueueRepository;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ReflectionClass;
use Throwable;

abstract class ProgressiveBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Run the job either:
     * - in "classic" mode
     * - as a progressive batch with  error and next job management  
     */
    public function handle(): void
    {
        if ($this->batch()) {
            $this->runAsProgressiveBatchJob();
        } else {
            $this->runAsJob();
        }
    }

    /**
     * This method will hold the job work to be done
     *
     * @return void
     */
    abstract protected function run(): void;

    /**
     * Run the job is "classic" mode, not as a part as a progressive batch
     *
     * @return void
     */
    protected function runAsJob(): void
    {
        $this->run();
    }

    /**
     * Run job as part of a progressive batch
     *
     * @return void
     */
    protected function runAsProgressiveBatchJob(): void
    {
        try {
            $this->run();
        } catch (Throwable $e) {
            $this->progressiveBatchFailed($e);
            throw $e;
        } finally {
            $this->addNextJobToBatch();
        }
    }

    /**
     * If job failed, send a event with data about the failed job
     * For use in other parts of the application
     *
     * @param Throwable $e
     * @return void
     */
    protected function progressiveBatchFailed(Throwable $e): void
    {
        $args = [];
        $job_reflection = new ReflectionClass($this);
        $constructor = $job_reflection->getConstructor();
        foreach ($constructor->getParameters() as $param) {
            $param_name = $param->getName();
            $args[$param->getName()] = $this->$param_name;
        }

        event(new BatchJobFailed($this->batch(), [
            'uuid' => $this->job->uuid(),
            'error' => $e->getMessage(),
            'class' =>  get_class($this),
            'args' => $args,
        ]));
    }

    /**
     * Check if a job should be added to the batch
     * Data required by job is pulled from the batch queue
     *
     * @return void
     */
    protected function addNextJobToBatch(): void
    {
        $batch_queue = new BatchQueue($this->batch(), new RedisBatchQueueRepository());
        $next_item  = $batch_queue->pop();
        if ($next_item->isDefined()) {
            $this->batch()->add(new static($next_item->get()));
        }
    }
}

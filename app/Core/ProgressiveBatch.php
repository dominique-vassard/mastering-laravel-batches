<?php

namespace App\Core;

use App\Events\Batch\BatchEnded;
use App\Events\Batch\BatchProgressed;
use App\Events\Batch\BatchStarted;
use App\Core\ProgressiveBatchJob;
use App\Events\Batch\BatchCancelled;
use App\Models\BatchQueue;
use App\Repositories\RedisBatchQueueRepository;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;

class ProgressiveBatch
{
    /**
     * Create a batch for the given job and data
     *
     * @param string    $job_class  The job to batch
     * @param array     $data       The data to use as queue
     *
     * @return PendingBatch
     */
    public function create(string $job_class, array $data): PendingBatch
    {
        self::validateJob($job_class);

        $total_jobs = count($data);
        $first_data = array_shift($data);
        $first_data .= '_fails';

        return Bus::batch([new $job_class($first_data)])
            ->before(function (Batch $batch) use ($data) {
                $batch_queue = new BatchQueue($batch, new RedisBatchQueueRepository());
                $batch_queue->data = $data;
                $batch_queue->create();

                event(new BatchStarted($batch));
            })
            ->catch(function (Batch $batch, Throwable $e) {
                Log::error(sprintf('Batch [%s] failed with error [%s].', $batch->id, $e->getMessage()));
            })
            ->then(fn (Batch $batch) => Log::info(sprintf('Batch [%s] ended.', $batch->id)))
            ->progress(function (Batch $batch) use ($total_jobs) {
                $nb_processed_jobs = $batch->processedJobs() + $batch->failedJobs;
                $previous_progress = $total_jobs > 0 ? round((($nb_processed_jobs - 1) / $total_jobs) * 100) : 0;
                $progress =  $total_jobs > 0 ? round(($nb_processed_jobs / $total_jobs) * 100) : 0;

                if (floor($previous_progress / 10) != floor($progress / 10)) {
                    event(new BatchProgressed($batch, [
                        'progress' => (int)(floor($progress / 10) * 10),
                        'nb_jobs_processed' => $nb_processed_jobs,
                        'nb_jobs_failed' => $batch->failedJobs,
                        'nb_jobs_total' =>  $total_jobs,
                    ]));
                }
            })
            ->finally(function (Batch $batch) {
                event(new BatchEnded($batch));
            })
            ->allowFailures();
    }

    /**
     * Cancel batch with the given id
     *
     * @param string $batch_id
     * @return void
     */
    public function cancel(string $batch_id): void
    {
        $batch = $this->findOrFail($batch_id);
        $batch->cancel();

        // Clear the queue
        $batch_queue = new BatchQueue($batch, new RedisBatchQueueRepository());
        $batch_queue->delete();

        event(new BatchCancelled($batch));
    }

    /**
     * Retry batch with the given id
     *
     * @param string $batch_id
     * @return void
     */
    public function retry(string $batch_id): void
    {
        $batch = $this->findOrFail($batch_id);
        Artisan::call('queue:retry-batch', ['id' => $batch->id]);
    }

    public function retryJobs(string $batch_id, array $job_ids): void
    {
        $batch = $this->findOrFail($batch_id);
        $batch_failed_job_ids = collect($batch->failedJobIds);


        $invalid_job_ids = collect($job_ids)
            ->reject(fn ($job_id) => $batch_failed_job_ids->contains($job_id));

        throw_unless($invalid_job_ids->isEmpty(), new InvalidArgumentException(sprintf(
            'Job ids [%s] do not exist or are not linked to batch [%s].',
            implode(', ', $invalid_job_ids->toArray()),
            $batch->id
        )));

        array_map(fn ($job_id) => Artisan::call('queue:retry', ['id' => $job_id]), $job_ids);
    }

    /**
     * List failed jobs for the given batch id
     *
     * @param string $batch_id
     * @return array
     */
    public function listFailedJobs(string $batch_id): array
    {
        $batch = $this->findOrFail($batch_id);

        $queue_failer = app('queue.failer');
        return collect($batch->failedJobIds)
            ->map(function ($failed_job_id) use ($queue_failer) {
                // Get job data
                $job_data = $queue_failer->find($failed_job_id);
                $payload = json_decode($job_data->payload, true);
                $job_class = unserialize($payload['data']['command']);

                // Extract arg names from class constructor signature and their data from unserialized class
                $args = [];
                $job_reflection = new ReflectionClass($job_class);
                $constructor = $job_reflection->getConstructor();
                foreach ($constructor->getParameters() as $param) {
                    $param_name = $param->getName();
                    $args[$param->getName()] = $job_class->$param_name;
                }

                return [
                    'uuid' => $payload['uuid'],
                    'class' => $payload['data']['commandName'],
                    'args' => json_encode($args, JSON_PRETTY_PRINT),
                    'failed_at' => $job_data->failed_at,
                ];
            })
            ->toArray();
    }
    /**
     * Find the batch with the given id or return null
     *
     * @param string $batch_id
     * @return Batch|null
     */
    public function find(string $batch_id): ?Batch
    {
        return Bus::findBatch($batch_id);
    }

    /**
     * Find the batch with the given id or throws
     *
     * @param string $batch_id
     * @return Batch
     */
    public function findOrFail(string $batch_id): Batch
    {
        $batch = Bus::findBatch($batch_id);
        throw_unless($batch, new InvalidArgumentException(sprintf('Batch [%s] does not exist.', $batch_id)));

        return $batch;
    }


    /**
     * Check that given job class is valid.
     * It should be a subclass of ProgressiveBatchJob.
     *
     * @param string $job_class
     * @return void
     */
    private static function validateJob(string $job_class): void
    {
        try {
            $job = new ReflectionClass($job_class);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(sprintf('Job class [%s] does not exist.', $job_class));
        }
        throw_unless($job->isSubclassOf(ProgressiveBatchJob::class), new InvalidArgumentException(
            sprintf('Job class must extend %s. ', ProgressiveBatchJob::class)
        ));
    }
}

<?php

namespace App\Console\Commands;

use App\Events\Batch\BatchCancelled;
use App\Events\Batch\BatchEnded;
use App\Events\Batch\BatchProgressed;
use App\Events\Batch\BatchStarted;
use App\Jobs\SimpleJob;
use App\Models\BatchQueue;
use App\Repositories\BatchQueueRepository;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class Batcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batcher';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batcher';

    protected array $batch_ids = [];
    protected string $current_batch_id = '9c2c2acb-313c-4a96-93c9-55a17c57109a';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->chooseOperation();
    }

    protected function chooseOperation(): void
    {
        $operations = [
            'dispatchBatch' => 'Dispatch a new batch',
            'cancelBatch' => 'Cancel a batch',
            'retryBatch' => 'Retry all failed jobs of a batch',
            'retryJob' => 'Retry a job',
            'inspectBatch' => 'Inspect a batch',
            'exit' => 'Exit',
        ];
        $operation = select('What do you want to do now?', options: $operations, default: 'exit', scroll: 10);
        $this->$operation();
        $this->chooseOperation();
    }

    /**
     * Run a batch of ExampleJobs
     *
     * @return void
     */
    protected function dispatchBatch(): void
    {
        $this->info('Dispatch batch');

        $nb_jobs = text('How many jobs should the batch contains?', default: 5);

        if ($nb_jobs < 1) {
            $this->error('Cannot dispatch a batch with less than 1 job.');
            $this->dispatchBatch();
        }

        $data = array_map(fn ($_) => Str::random(30), range(1, $nb_jobs));
        $total_jobs = count($data);

        $first_data = array_shift($data);

        $batch = Bus::batch([new SimpleJob($first_data)])
            ->before(function (Batch $batch) use ($data) {
                Log::info(sprintf('Batch [%s] created.', $batch->id));

                $batch_queue = new BatchQueue($batch, new BatchQueueRepository());
                $batch_queue->data = $data;
                $batch_queue->create();

                event(new BatchStarted($batch));
            })
            ->catch(function (Batch $batch, Throwable $e) {
                Log::error(sprintf('Batch [%s] failed with error [%s].', $batch->id, $e->getMessage()));
            })
            ->then(fn (Batch $batch) => Log::info(sprintf('Batch [%s] ended.', $batch->id)))
            ->progress(function (Batch $batch) use ($total_jobs) {
                $previous_progress = $total_jobs > 0 ? round((($batch->processedJobs() - 1) / $total_jobs) * 100) : 0;
                $progress =  $total_jobs > 0 ? round(($batch->processedJobs() / $total_jobs) * 100) : 0;

                if (floor($previous_progress / 10) != floor($progress / 10)) {
                    $progress_data = [
                        'progress' => $progress,
                        'processed_jobs' => $batch->processedJobs(),
                        'failed_jobs' => $batch->failedJobs,
                        'total_jobs' => $total_jobs,

                    ];
                    event(new BatchProgressed($batch, $progress_data));
                    Log::info(sprintf(
                        'Batch [%s] progress : %d/%d [%d%%]',
                        $batch->id,
                        $batch->processedJobs(),
                        $total_jobs,
                        (int)(floor($progress / 10) * 10),
                    ));
                }
            })
            ->finally(function (Batch $batch) {
                Log::info(sprintf('Batch [%s] finally ended.', $batch->id));
                event(new BatchEnded($batch));
            })
            ->allowFailures()
            ->dispatch();

        $this->current_batch_id = $batch->id;
    }

    /**
     * Get the batch id on chic operate
     *
     * @return string
     */
    protected function getBatchId(): string
    {
        return count($this->batch_ids) > 0 ? select('Select batch for operation', $this->batch_ids) : $this->current_batch_id;
    }

    /**
     * Write batch data 
     *
     * @return void
     */
    protected function inspectBatch(): void
    {
        $batch_id = $this->getBatchId();
        $batch = Bus::findBatch($batch_id);
        dump($batch->toArray());
    }

    /**
     * Cancel the batch with the given id
     *
     * @param string $batch_id
     * @return void
     */
    protected function cancelBatch(): void
    {
        $batch = Bus::findBatch($this->current_batch_id);
        $batch->cancel();

        // Clear the queue
        $batch_queue = new BatchQueue($batch, new BatchQueueRepository());
        $batch_queue->delete();

        event(new BatchCancelled($batch));

        $this->info(sprintf('Batch %s cancelled.', $batch->id));
    }

    /**
     * Retry the batch, meaning retry all its failed jobs
     *
     * @return void
     */
    protected function retryBatch(): void
    {
        $batch_id = $this->getBatchId();
        if (confirm(sprintf('Are you sure to retry all failed jobs of batch [%s]?', $batch_id), false)) {
            $this->call('queue:retry-batch', ['id' => $this->current_batch_id]);
        };
    }

    /**
     * Retry a specific job from a batch
     *
     * @return void
     */
    protected function retryJob(): void
    {
        $batch_id = $this->getBatchId();
        $batch = Bus::findBatch($batch_id);
        $queue_failer = app('queue.failer');
        $failed_jobs = collect($batch->failedJobIds)
            ->mapWithKeys(function ($failed_job_id) use ($queue_failer) {
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

                $job = [
                    'uuid' => $payload['uuid'],
                    'class' => $payload['data']['commandName'],
                    'args' => json_encode($args, JSON_PRETTY_PRINT),
                    'failed_at' => $job_data->failed_at,
                ];

                return [$payload['uuid'] => $job];
            });


        table(['uuid', 'class', 'args', 'failed_at'], $failed_jobs);

        $job_uuid_to_retry = select('Which job do you want to retry', array_column($failed_jobs->toArray(), 'uuid'));
        $this->call('queue:retry', ['id' => $job_uuid_to_retry]);

        $this->info('Job pushed to queue for retry');
    }

    /**
     * Exit the command
     *
     * @return void
     */
    protected function exit(): void
    {
        $this->question('Bye');
        exit;
    }
}

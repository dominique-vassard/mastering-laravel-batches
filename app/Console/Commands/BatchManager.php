<?php

namespace App\Console\Commands;

use App\Core\ProgressiveBatch;
use App\Events\Batch\BatchCancelled;
use App\Events\Batch\BatchEnded;
use App\Events\Batch\BatchProgressed;
use App\Events\Batch\BatchStarted;
use App\Events\Batch\BatchStartedPlain;
use App\Jobs\SimpleJob;
use App\Models\BatchQueue;
use App\Repositories\RedisBatchQueueRepository;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class BatchManager extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batch:manage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected array $batch_ids = [];
    protected string $current_batch_id;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->chooseOperation();
    }

    private function chooseOperation(): void
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

        // Run operation or exit command
        if ($operation == 'exit') {
            $this->question('Bye');
        } else {
            $this->$operation();
            $this->chooseOperation();
        }
    }

    protected function dispatchBatch(): void
    {
        $this->info('Dispatch batch');

        $nb_jobs = text('How many jobs should the batch contains?', default: 5);

        if ($nb_jobs < 2) {
            $this->error('Cannot dispatch a batch with less than 1 job.');
            $this->dispatchBatch();
        }

        $data = [Str::random(30), null];
        $data = array_merge($data, array_map(fn ($_) => Str::random(30), range(1, $nb_jobs - 2)));
        $batch = (new ProgressiveBatch())->create(SimpleJob::class, $data)
            ->dispatch();

        $this->current_batch_id = $batch->id;
        $this->batch_ids[] = $batch->id;
    }

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

    protected function cancelBatch(): void
    {
        (new ProgressiveBatch())->cancel($this->current_batch_id);
    }

    protected function retryBatch(): void
    {
        $batch_id = $this->getBatchId();
        if (confirm(sprintf('Are you sure to retry all failed jobs of batch [%s]?', $batch_id), false)) {
            (new ProgressiveBatch())->retry($this->current_batch_id);
        };
    }

    protected function retryJob(): void
    {
        $batch_id = $this->getBatchId();

        $progressive_batch = new ProgressiveBatch();
        $failed_jobs = collect($progressive_batch->listFailedJobs($batch_id))
            ->mapWithKeys(fn ($failed_job) => [$failed_job['uuid'] => $failed_job]);

        table(['uuid', 'class', 'args', 'failed_at'], $failed_jobs);

        $job_uuid_to_retry = select('Which job do you want to retry', array_column($failed_jobs->toArray(), 'uuid'));
        $progressive_batch->retryJobs($batch_id, [$job_uuid_to_retry]);

        $this->info('Job pushed to queue for retry');
    }
}

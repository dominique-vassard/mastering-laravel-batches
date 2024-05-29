<?php

namespace App\Console\Commands;

use App\Jobs\ExampleJob;
use App\Jobs\OtherJob;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class Batcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'batcher
                            {--cancel= : Cancel batch with the given id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batcher';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('cancel')) {
            $this->cancelBatch($this->option('cancel'));
        } else {
            $this->dispatchBatch(ExampleJob::class);
            $this->dispatchBatch(OtherJob::class);
        }
    }

    /**
     * Cancel the batch with the given id
     *
     * @param string $batch_id
     * @return void
     */
    private function cancelBatch(string $batch_id): void
    {
        // Find the batch for the given id
        $batch = Bus::findBatch($batch_id);
        // Cancel the batch
        $batch->cancel();
    }

    /**
     * Run a batch of ExampleJobs
     *
     * @return void
     */
    private function dispatchBatch(string $job_class_name): void
    {
        Bus::batch([new $job_class_name(1)])
            ->before(fn (Batch $batch) => Log::info(sprintf('Batch [%s] created.', $batch->id)))
            ->catch(function (Batch $batch, Throwable $e) {
                Log::error(sprintf('Batch [%s] failed with error [%s].', $batch->id, $e->getMessage()));
            })
            ->then(fn (Batch $batch) => Log::info(sprintf('Batch [%s] ended.', $batch->id)))
            ->progress(fn (Batch $batch) =>
            Log::info(sprintf(
                'Batch [%s] progress : %d/%d [%d%%]',
                $batch->id,
                $batch->processedJobs(),
                $batch->totalJobs,
                $batch->progress()
            )))
            ->finally(fn (Batch $batch) => Log::info(sprintf('Batch [%s] finally ended.', $batch->id)))
            ->allowFailures()
            ->dispatch();
    }
}

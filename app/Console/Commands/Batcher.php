<?php

namespace App\Console\Commands;

use App\Jobs\ExampleJob;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Bus::batch(
            Arr::map(
                range(1, 5),
                fn ($number) => new ExampleJob($number)
            )
        )
            ->before(fn (Batch $batch) => Log::info(sprintf('Batch [%s] created.', $batch->id)))
            ->then(fn (Batch $batch) => Log::info(sprintf('Batch [%s] ended.', $batch->id)))
            ->dispatch();
    }
}

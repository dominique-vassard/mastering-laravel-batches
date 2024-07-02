<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static Illuminate\Bus\PendingBatch create(string $job_class, array $data)
 * @method static void cancel(string $batch_id)
 * @method static void retry(string $batch_id)
 * @method static void retryJobs(string $batch_id array $job_ids)
 * @method static array listFailedJobs(string $batch_id)
 * @method static ?Illuminate\Bus\Batch find(string $batch_id)
 * @method static Illuminate\Bus\Batch findOrFail(string $batch_id)
 * 
 * @see App\Core\ProgressiveBatch
 */
class ProgressiveBatch extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'progressive_batch';
    }
}

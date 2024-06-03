<?php

namespace App\Repositories;

use App\Contracts\BatchQueueRepositoryContract;
use App\DataObjects\QueueData;
use Illuminate\Support\Facades\Redis;

class BatchQueueRepository implements BatchQueueRepositoryContract
{
    /**
     * Create the list
     *
     * @param string $key
     * @param array $data
     * @return integer
     */
    public function create(string $key, array $data): int
    {
        return Redis::rpush($key, ...$data);
    }

    /**
     * Delete the list
     *
     * @param string $key
     * @return boolean
     */
    public function delete(string $key): bool
    {
        return Redis::del($key);
    }

    /**
     * Return the first item of the list
     *
     * @param [type] $key
     * @return string|null
     */
    public function getFirstItem(string $key): ?string
    {
        return  Redis::lpop($key);
    }

    /**
     * REturn the que length
     *
     * @param string $key
     * @return integer
     */
    public function length(string $key): int
    {
        return Redis::llen($key);
    }
}

<?php

namespace App\Repositories;

use App\Contracts\BatchQueueRepository;
use Illuminate\Support\Facades\Redis;
use PhpOption\None;
use PhpOption\Option;
use PhpOption\Some;

class RedisBatchQueueRepository implements BatchQueueRepository
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
        $serialized_data = array_map(fn ($value) => serialize($value), $data);
        return Redis::rpush($key, ...$serialized_data);
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
     * @param string $key
     * @return mixed
     */
    public function getFirstItem(string $key): Option
    {
        $serialized_item =  Redis::lpop($key);
        $item = None::create();
        if ($serialized_item !== false) {
            $item = Some::create(unserialize($serialized_item));
        }
        return $item;
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

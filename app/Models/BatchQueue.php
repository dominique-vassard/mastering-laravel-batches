<?php

namespace App\Models;

use App\Contracts\BatchQueueRepository;
use Illuminate\Bus\Batch;

class BatchQueue
{
    protected BatchQueueRepository $repository;
    public string $key;
    public array $data;

    public function __construct(Batch $batch, BatchQueueRepository $batch_queue_repository)
    {
        $this->key = 'batch-queue-' . $batch->id;
        $this->repository = $batch_queue_repository;
    }

    /**
     * Persist the queue to database
     *
     * @return boolean
     */
    public function create(): bool
    {
        return $this->repository->create($this->key, $this->data);
    }

    /**
     * Delete the queue from database
     *
     * @return boolean
     */
    public function delete(): bool
    {
        return $this->repository->delete($this->key);
    }

    /**
     * Return the first element in queue
     *
     * @return mixed
     */
    public function pop(): mixed
    {
        return $this->repository->getFirstItem($this->key);
    }

    /**
     * Return the number of item in queue
     *
     * @return integer
     */
    public function count(): int
    {
        return $this->repository->length($this->key);
    }

    /**
     * Determine wether the queue is empty
     *
     * @return boolean
     */
    public function isEmpty(): bool
    {
        return $this->count() > 0;
    }
}

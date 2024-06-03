<?php

namespace App\Models;

use App\Contracts\BatchQueueRepositoryContract;
use Illuminate\Bus\Batch;

class BatchQueue
{
    protected BatchQueueRepositoryContract $repository;
    public string $key;
    public array $data;

    public function __construct(Batch $batch, BatchQueueRepositoryContract $batch_queue_repository)
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
        return $this->repository->create($this->key, $this->toQueueableData());
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
        $item = $this->repository->getFirstItem($this->key);
        return $item ? $this->fromQueuedData($item) : null;
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

    /**
     * Modify data to fit the underlying database
     *
     * @return array
     */
    public function toQueueableData(): array
    {
        return array_map(fn ($value) => json_encode($value), $this->data);
    }

    /**
     * Modify data received from the underlying database to get a workable format
     *
     * @param string $data
     * @return mixed
     */
    public function fromQueuedData(string $data): mixed
    {
        return json_decode($data);
    }
}

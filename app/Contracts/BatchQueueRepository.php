<?php

namespace App\Contracts;

interface BatchQueueRepository
{
    public function create(string $key, array $data): int;
    public function delete(string $key): bool;
    public function getFirstItem(string $key): mixed;
    public function length(string $key): int;
}

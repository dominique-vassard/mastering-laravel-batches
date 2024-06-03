<?php

namespace App\Contracts;

interface BatchQueueRepositoryContract
{
    public function create(string $key, array $data): int;
    public function delete(string $key): bool;
    public function getFirstItem(string $key): ?string;
    public function length(string $key): int;
}

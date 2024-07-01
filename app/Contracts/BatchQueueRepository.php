<?php

namespace App\Contracts;

use PhpOption\Option;

interface BatchQueueRepository
{
    public function create(string $key, array $data): int;
    public function delete(string $key): bool;
    public function getFirstItem(string $key): Option;
    public function length(string $key): int;
}

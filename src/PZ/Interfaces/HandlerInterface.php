<?php

/*
 * This file is a part of the PZ Bot project.
 *
 * Copyright (c) 2024-present Valithor Obsidion <valithor@valzargaming.com>
 */

namespace PZ\Interfaces;

use Discord\Helpers\Collection;
use \Traversable;

interface HandlerInterface
{
    public function get(): array;
    public function set(array $handlers): self;
    public function pull(int|string $index, ?callable $default = null): array;
    public function fill(array $commands, array $handlers): self;
    public function pushHandler(callable $callback, int|string|null $command = null): self;
    public function count(): int;
    public function first(): array;
    public function last(): array;
    public function isset(int|string $offset): bool;
    public function has(array ...$indexes): bool;
    public function filter(callable $callback): self;
    public function find(callable $callback): array;
    public function clear(): self;
    public function map(callable $callback): self;
    public function merge(object $handler): self;
    public function toArray(): array;
    public function offsetExists(int|string $offset): bool;
    public function offsetGet(int|string $offset): array;
    public function offsetSet(int|string $offset, callable $callback): self;
    public function getIterator(): Traversable;
    public function __debugInfo(): array;

    public function checkRank(?Collection $roles = null, array $allowed_ranks = []): bool;
}
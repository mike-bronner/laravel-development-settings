<?php

declare(strict_types=1);

namespace MikeBronner\DevelopmentSettings\Tests\Fixtures;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Arr;
use Override;

final class ArrayConfig implements Repository
{
    public function __construct(private array $items = [])
    {
    }

    #[Override]
    public function has($key): bool
    {
        return Arr::has($this->items, $key);
    }

    #[Override]
    public function get($key, $default = null): mixed
    {
        return Arr::get($this->items, $key, $default);
    }

    #[Override]
    public function all(): array
    {
        return $this->items;
    }

    #[Override]
    public function set($key, $value = null): void
    {
        Arr::set($this->items, $key, $value);
    }

    #[Override]
    public function prepend($key, $value): void
    {
        $this->set($key, [$value, ...$this->get($key, [])]);
    }

    #[Override]
    public function push($key, $value): void
    {
        $this->set($key, [...$this->get($key, []), $value]);
    }
}

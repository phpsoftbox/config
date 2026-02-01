<?php

declare(strict_types=1);

namespace PhpSoftBox\Config\Contracts;

interface ConfigMutableInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function override(string $key, mixed $value): void;
}

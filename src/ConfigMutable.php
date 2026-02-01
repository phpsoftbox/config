<?php

declare(strict_types=1);

namespace PhpSoftBox\Config;

use PhpSoftBox\Config\Contracts\ConfigMutableInterface;

final readonly class ConfigMutable implements ConfigMutableInterface
{
    public function __construct(
        private Config $config,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    public function override(string $key, mixed $value): void
    {
        $this->config->override($key, $value);
    }
}

<?php

declare(strict_types=1);

namespace PhpSoftBox\Config\Provider;

interface DataProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function load(): array;
}

<?php

declare(strict_types=1);

namespace PhpSoftBox\Config\Provider;

final class ArrayDataProvider extends AbstractDataProvider
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        return $this->data;
    }
}

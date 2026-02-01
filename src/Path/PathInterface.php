<?php

declare(strict_types=1);

namespace PhpSoftBox\Config\Path;

interface PathInterface
{
    public function baseDir(): string;

    public function createPath(string $relatedPath): string;
}

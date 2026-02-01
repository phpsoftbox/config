<?php

declare(strict_types=1);

namespace PhpSoftBox\Config\Path;

use PhpSoftBox\Storage\FileHelper;

use function basename;
use function implode;
use function rtrim;
use function str_contains;
use function trim;

abstract class AbstractPath implements PathInterface
{
    public function __construct(
        private readonly string $baseDir,
    ) {
    }

    public function baseDir(): string
    {
        return $this->baseDir;
    }

    public function createPath(string $relatedPath): string
    {
        return $this->path($relatedPath);
    }

    protected function path(string ...$parts): string
    {
        $segments = [rtrim($this->baseDir, '/')];

        foreach ($parts as $part) {
            $part = trim($part, '/');
            if ($part === '') {
                continue;
            }
            $segments[] = $part;
        }

        $path = implode('/', $segments);

        $baseName = basename($path);

        if (str_contains($baseName, '.') || $baseName === 'hot') {
            FileHelper::ensureDirectoryForFile($path);
        } else {
            FileHelper::ensureDirectory($path);
        }

        return $path;
    }
}

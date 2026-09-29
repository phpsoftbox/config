<?php

declare(strict_types=1);

namespace PhpSoftBox\Config\Path;

use PhpSoftBox\Storage\FileHelper;

use function implode;
use function rtrim;
use function trim;

/**
 * Пути проекта относительно базового каталога.
 *
 * Геттеры только собирают путь и ничего не создают (на read-only FS они не падают). Каталог, в который будут писать,
 * создаётся явно — {@see self::ensureDirectory()}.
 */
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

    /**
     * Создаёт каталог (с родителями), если его нет, и возвращает путь.
     */
    public function ensureDirectory(string $path): string
    {
        FileHelper::ensureDirectory($path);

        return $path;
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

        return implode('/', $segments);
    }
}

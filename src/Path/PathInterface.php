<?php

declare(strict_types=1);

namespace PhpSoftBox\Config\Path;

interface PathInterface
{
    public function baseDir(): string;

    /**
     * Путь относительно базового каталога. Ничего не создаёт.
     */
    public function createPath(string $relatedPath): string;

    /**
     * Создаёт каталог, если его нет, и возвращает путь.
     */
    public function ensureDirectory(string $path): string;
}

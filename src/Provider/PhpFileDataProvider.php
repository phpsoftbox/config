<?php

declare(strict_types=1);

namespace PhpSoftBox\Config\Provider;

use function array_key_exists;
use function array_replace_recursive;
use function count;
use function glob;
use function is_array;
use function is_file;
use function pathinfo;

use const PATHINFO_FILENAME;

final class PhpFileDataProvider extends AbstractDataProvider
{
    public function __construct(
        private string $pattern,
        private bool $keyByFilename = true,
    ) {
    }

    public function load(): array
    {
        $merged = [];
        $files  = glob($this->pattern) ?: [];

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $data = require $file;
            if (!is_array($data)) {
                continue;
            }
            if ($this->keyByFilename) {
                $key = pathinfo($file, PATHINFO_FILENAME);
                if ($key !== '' && count($data) === 1 && array_key_exists($key, $data)) {
                    $data = $data[$key];
                }
                $data = $key !== '' ? [$key => $data] : $data;
            }
            $merged = array_replace_recursive($merged, $data);
        }

        return $merged;
    }
}

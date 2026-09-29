<?php

declare(strict_types=1);

namespace PhpSoftBox\Config\Cli;

use PhpSoftBox\Env\EnvStorage;
use Throwable;

use function class_exists;
use function getenv;
use function is_string;

/**
 * `APP_KEY` для команд шифрования конфигурации: из `.env` (через `phpsoftbox/env`, если он установлен), затем из
 * окружения процесса.
 */
final class AppKeyResolver
{
    public static function resolve(): string
    {
        if (class_exists(EnvStorage::class)) {
            try {
                $value = EnvStorage::value('APP_KEY')->string();
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            } catch (Throwable) {
                // .env не загружен — берём из окружения процесса.
            }
        }

        foreach ([$_ENV['APP_KEY'] ?? null, $_SERVER['APP_KEY'] ?? null, getenv('APP_KEY')] as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }
}

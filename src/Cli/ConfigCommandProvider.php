<?php

declare(strict_types=1);

namespace PhpSoftBox\Config\Cli;

use PhpSoftBox\CliApp\Command\ArgumentDefinition;
use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\CliApp\Command\CommandRegistryInterface;
use PhpSoftBox\CliApp\Command\OptionDefinition;
use PhpSoftBox\CliApp\Loader\CommandProviderInterface;

final class ConfigCommandProvider implements CommandProviderInterface
{
    public function register(CommandRegistryInterface $registry): void
    {
        $registry->register(Command::define(
            name: 'config:cache:clear',
            description: 'Очистить кеш config для выбранного окружения',
            signature: [],
            handler: ClearConfigCacheHandler::class,
        ));

        $registry->register(Command::define(
            name: 'config:encrypt',
            description: 'Зашифровать значение для config (выводит ciphertext)',
            signature: [
                new ArgumentDefinition(
                    name: 'value',
                    description: 'Строка для шифрования',
                    required: true,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'cipherKey',
                    short: 'k',
                    description: 'Ключ шифрования (по умолчанию APP_KEY)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
            ],
            handler: ConfigEncryptHandler::class,
        ));

        $registry->register(Command::define(
            name: 'config:decrypt',
            description: 'Расшифровать значение из config (ожидает ciphertext)',
            signature: [
                new ArgumentDefinition(
                    name: 'value',
                    description: 'Строка для расшифровки',
                    required: true,
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'cipherKey',
                    short: 'k',
                    description: 'Ключ шифрования (по умолчанию APP_KEY)',
                    required: false,
                    default: null,
                    type: 'string',
                ),
            ],
            handler: ConfigDecryptHandler::class,
        ));
    }
}

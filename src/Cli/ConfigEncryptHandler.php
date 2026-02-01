<?php

declare(strict_types=1);

namespace PhpSoftBox\Config\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Encryptor\Encryptor;
use Throwable;

use function class_exists;
use function is_string;

final class ConfigEncryptHandler implements HandlerInterface
{
    public function run(RunnerInterface $runner): int|Response
    {
        if (!class_exists(Encryptor::class)) {
            $runner->io()->writeln('Encryptor не установлен. Установите пакет phpsoftbox/encryptor.', 'error');

            return Response::FAILURE;
        }

        $value = (string) $runner->request()->param('value', '');
        if ($value === '') {
            $runner->io()->writeln('Укажите значение для шифрования.', 'error');

            return Response::FAILURE;
        }

        $key = $this->resolveKey($runner);
        if ($key === '') {
            $runner->io()->writeln('Ключ шифрования не задан. Используйте APP_KEY или --cipherKey.', 'error');

            return Response::FAILURE;
        }

        try {
            $encryptor = new Encryptor(defaultKey: $key);

            $ciphertext = $encryptor->encrypt($value, $key);
        } catch (Throwable $e) {
            $runner->io()->writeln('Ошибка шифрования: ' . $e->getMessage(), 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln($ciphertext, 'success');

        return Response::SUCCESS;
    }

    private function resolveKey(RunnerInterface $runner): string
    {
        $key = $runner->request()->option('cipherKey');
        if (is_string($key) && $key !== '') {
            return $key;
        }

        $envKey = $_ENV['APP_KEY'] ?? $_SERVER['APP_KEY'] ?? '';

        return is_string($envKey) ? $envKey : '';
    }
}

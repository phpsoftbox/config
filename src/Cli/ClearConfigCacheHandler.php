<?php

declare(strict_types=1);

namespace PhpSoftBox\Config\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Config\ConfigFactory;
use Psr\SimpleCache\CacheInterface;

use function is_string;

final readonly class ClearConfigCacheHandler implements HandlerInterface
{
    public function __construct(
        private ?CacheInterface $cache = null,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        if ($this->cache === null) {
            $runner->io()->writeln('Кеш не сконфигурирован (CacheInterface недоступен).', 'error');

            return Response::FAILURE;
        }

        $env = $runner->request()->option('environment');
        if (!is_string($env) || $env === '') {
            $runner->io()->writeln('Не указано окружение для очистки кеша config.', 'error');

            return Response::FAILURE;
        }

        $key = ConfigFactory::cacheKeyForEnvironment($env);

        if ($this->cache->delete($key)) {
            $runner->io()->writeln('Кеш config очищен (' . $key . ').', 'success');

            return Response::SUCCESS;
        }

        $runner->io()->writeln('Не удалось очистить кеш config (' . $key . ').', 'error');

        return Response::FAILURE;
    }
}

<?php

declare(strict_types=1);

namespace PhpSoftBox\Config;

use DateInterval;
use InvalidArgumentException;
use PhpSoftBox\Encryptor\Contracts\EncryptedValueResolverInterface;
use Psr\SimpleCache\CacheInterface;

use function array_key_exists;
use function array_map;
use function basename;
use function count;
use function explode;
use function getenv;
use function glob;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function pathinfo;
use function trim;

use const PATHINFO_FILENAME;

/**
 * Сборка конфигурации из провайдеров или каталога `config/`.
 *
 * `baseDir` — корень проекта, обязателен. Без провайдеров читаются `config/*.php` (кроме файлов из
 * {@see self::SKELETON_FILES} — они не возвращают конфигурацию и исполнять их нельзя), `config/{env}/*.php`,
 * `config/local.php` и `config/local/*.php`.
 */
final readonly class ConfigFactory
{
    public const string CACHE_KEY_PREFIX = 'config.configs';

    /**
     * Файлы скелета в `config/`, которые не являются конфигурацией.
     */
    public const array SKELETON_FILES = [
        'app.php', 'bootstrap.php', 'cli-app.php', 'cli.bootstrap.php', 'container.php', 'cs-fixer.php',
        'dependencies.php', 'middleware.php',
    ];

    public function __construct(
        private string $environment,
        private string $baseDir,
        private ?string $extra = null,
        private ?EncryptedValueResolverInterface $encryptedValueResolver = null,
        private ?CacheInterface $cache = null,
        private int|DateInterval|null $cacheTtl = null,
        private array $providers = [],
    ) {
        if (trim($this->environment) === '') {
            throw new InvalidArgumentException('Config environment must be a non-empty string.');
        }
    }

    public function create(): Config
    {
        $baseDir = $this->baseDir;
        $env     = $this->environment;
        $extras  = $this->extra ?? (string) (getenv('PSB_CONFIG_EXTRA') ?: '');

        if ($this->cache !== null) {
            $cached = $this->cache->get(self::cacheKeyForEnvironment($this->environment));
            if (is_array($cached)) {
                return new Config(
                    sources: [$cached],
                    encryptedValueResolver: $this->encryptedValueResolver,
                    baseDir: $baseDir,
                );
            }
        }

        $layers = $this->providers === []
            ? $this->loadFromConfigDirectory($env, $baseDir)
            : $this->loadFromProviders();

        // 4) extra: explicit list via env
        if ($extras !== '') {
            foreach (array_map('trim', explode(',', $extras)) as $path) {
                if ($path === '') {
                    continue;
                }
                if (is_file($path)) {
                    $data = require $path;
                    if (is_array($data)) {
                        $layers[] = $this->wrapByFilename($path, $data);
                    }
                }
            }
        }

        $config = new Config(
            sources: $layers,
            encryptedValueResolver: $this->encryptedValueResolver,
            baseDir: $baseDir,
        );

        if ($this->cache !== null) {
            $this->cache->set(
                self::cacheKeyForEnvironment($this->environment),
                $config->raw(),
                $this->cacheTtl,
            );
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMergedConfig(): array
    {
        return $this->create()->raw();
    }

    /**
     * @return list<array>
     */
    private function loadFromConfigDirectory(string $env, string $baseDir): array
    {
        $cfgDir = $baseDir . '/config';
        $layers = [];

        // 1) base: config/*.php, кроме файлов скелета
        foreach (glob($cfgDir . '/*.php') ?: [] as $file) {
            if (in_array(basename($file), self::SKELETON_FILES, true)) {
                continue;
            }
            $data = require $file;
            if (is_array($data)) {
                $layers[] = $this->wrapByFilename($file, $data);
            }
        }

        // 2) env: config/{env}/*.php
        $envDir = $cfgDir . '/' . $env;
        if (is_dir($envDir)) {
            foreach (glob($envDir . '/*.php') ?: [] as $file) {
                $data = require $file;
                if (is_array($data)) {
                    $layers[] = $this->wrapByFilename($file, $data);
                }
            }
        }

        // 3) local overrides: config/local.php and config/local/*.php
        $localFile = $cfgDir . '/local.php';
        if (is_file($localFile)) {
            $data = require $localFile;
            if (is_array($data)) {
                $layers[] = $this->wrapByFilename($localFile, $data);
            }
        }
        $localDir = $cfgDir . '/local';
        if (is_dir($localDir)) {
            foreach (glob($localDir . '/*.php') ?: [] as $file) {
                $data = require $file;
                if (is_array($data)) {
                    $layers[] = $this->wrapByFilename($file, $data);
                }
            }
        }

        return $layers;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function wrapByFilename(string $file, array $data): array
    {
        $key = pathinfo($file, PATHINFO_FILENAME);
        if ($key === '') {
            return $data;
        }

        if (count($data) === 1 && array_key_exists($key, $data)) {
            $data = $data[$key];
        }

        return [$key => $data];
    }

    /**
     * @return list<array>
     */
    private function loadFromProviders(): array
    {
        $layers = [];

        foreach ($this->providers as $provider) {
            $data = $provider->load();
            if ($data !== []) {
                $layers[] = $data;
            }
        }

        return $layers;
    }

    public static function cacheKeyForEnvironment(string $environment): string
    {
        if (trim($environment) === '') {
            throw new InvalidArgumentException('Config environment must be a non-empty string.');
        }

        return self::CACHE_KEY_PREFIX . '.' . $environment;
    }
}

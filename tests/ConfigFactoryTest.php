<?php

declare(strict_types=1);

namespace PhpSoftBox\Tests\Config;

use FilesystemIterator;
use InvalidArgumentException;
use PhpSoftBox\Cache\Driver\ArrayDriver;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Config\ConfigFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function getenv;
use function is_dir;
use function mkdir;
use function putenv;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;
use function var_export;

#[CoversClass(ConfigFactory::class)]
#[CoversMethod(ConfigFactory::class, 'create')]
#[CoversClass(Config::class)]
#[CoversMethod(Config::class, 'get')]
#[CoversMethod(Config::class, 'has')]
#[CoversMethod(Config::class, 'all')]
final class ConfigFactoryTest extends TestCase
{
    #[Test]
    public function environmentMustBeANonEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConfigFactory(environment: '   ');
    }

    /**
     * Проверим, что базовые файлы из config/*.php считываются и сливаются, а container.php игнорируется.
     *
     * @see ConfigFactory::create()
     * @see Config::get()
     * @see Config::has()
     */
    #[Test]
    public function createMergesBaseAndIgnoresContainer(): void
    {
        $base = $this->makeTempConfigBase();

        // Подложим два базовых файла и container.php, который должен быть проигнорирован
        $this->putPhpArray($base . '/config/a.php', ['value' => 1]);
        $this->putPhpArray($base . '/config/b.php', ['x' => 2]);
        $this->putPhpArray($base . '/config/container.php', ['should' => 'be_ignored']);

        // Создаём конфиг фабрикой на основании нашего baseDir
        $config = new ConfigFactory(environment: 'dev', baseDir: $base)->create();

        // Базовые ключи доступны
        $this->assertSame(1, $config->get('a.value'));
        $this->assertSame(2, $config->get('b.x'));

        // Проверим, что container.php не попал в конфиг
        $this->assertSame(false, $config->has('container'));

        $this->cleanup($base);
    }

    /**
     * Проверим, что конфиги из config/{env}/*.php перекрывают базовые значения.
     *
     * @see ConfigFactory::create()
     * @see Config::get()
     */
    #[Test]
    public function createMergesEnvOverridesBase(): void
    {
        $base = $this->makeTempConfigBase();

        // Базовые значения
        $this->putPhpArray($base . '/config/app.php', [
            'x'      => 1,
            'nested' => ['v' => 1],
        ]);

        // Значения для prod
        $this->mkdirp($base . '/config/prod');
        $this->putPhpArray($base . '/config/prod/app.php', [
            'x'      => 2,
            'y'      => 3,
            'nested' => ['v' => 9, 'w' => 5],
        ]);

        // env=prod, должны примениться overrides
        $config = new ConfigFactory(environment: 'prod', baseDir: $base)->create();

        // Базовое перекрыто env
        $this->assertSame(2, $config->get('app.x'));
        $this->assertSame(3, $config->get('app.y'));

        // Проверим рекурсивное слияние
        $this->assertSame(9, $config->get('app.nested.v'));
        $this->assertSame(5, $config->get('app.nested.w'));

        $this->cleanup($base);
    }

    /**
     * Проверим, что файлы из config/local/*.php перекрывают базовые и env.
     *
     * @see ConfigFactory::create()
     * @see Config::get()
     */
    #[Test]
    public function createMergesLocalOverridesEnv(): void
    {
        $base = $this->makeTempConfigBase();

        // База и env
        $this->putPhpArray($base . '/config/app.php', ['x' => 1]);
        $this->mkdirp($base . '/config/dev');
        $this->putPhpArray($base . '/config/dev/app.php', ['x' => 2]);

        // local directory overrides
        $this->mkdirp($base . '/config/local');
        $this->putPhpArray($base . '/config/local/app.php', ['x' => 4]);
        $this->putPhpArray($base . '/config/local/extra.php', ['z' => 42]);

        $config = new ConfigFactory(environment: 'dev', baseDir: $base)->create();

        // Последнее слово за config/local/*.php
        $this->assertSame(4, $config->get('app.x'));
        $this->assertSame(42, $config->get('extra.z'));

        $this->cleanup($base);
    }

    /**
     * Проверим, что явные файлы из extra-list применяются в порядке перечисления и перекрывают все предыдущие значения.
     *
     * @see ConfigFactory::create()
     * @see Config::get()
     */
    #[Test]
    public function createMergesExtrasOverridesAll(): void
    {
        $base = $this->makeTempConfigBase();

        // База
        $this->putPhpArray($base . '/config/app.php', ['x' => 1]);

        // Дополнительные файлы
        $this->mkdirp($base . '/extra1');
        $this->mkdirp($base . '/extra2');
        $extra1 = $base . '/extra1/app.php';
        $extra2 = $base . '/extra2/app.php';
        $this->putPhpArray($extra1, ['x' => 5]);
        $this->putPhpArray($extra2, ['x' => 6, 'e' => 1]);

        // Передаём список явно (без использования переменных окружения)
        $config = new ConfigFactory(environment: 'dev', baseDir: $base, extra: $extra1 . ', ' . $extra2)->create();

        // Последний extra имеет приоритет
        $this->assertSame(6, $config->get('app.x'));
        $this->assertSame(1, $config->get('app.e'));

        $this->cleanup($base);
    }

    /**
     * Проверим, что файлы конфигурации, возвращающие не-массив, игнорируются фабрикой.
     *
     * @see ConfigFactory::create()
     * @see Config::all()
     */
    #[Test]
    public function createIgnoresNonArrayFiles(): void
    {
        $base = $this->makeTempConfigBase();

        // Файл возвращает скаляр, должен быть проигнорирован
        $this->putPhpRaw($base . '/config/bad.php', 'return 123;');

        $config = new ConfigFactory(environment: 'dev', baseDir: $base)->create();

        // Проверим, что конфиг пуст
        $this->assertSame([], $config->all());

        $this->cleanup($base);
    }

    /**
     * Проверим, что переданный env применяется при сборке конфига.
     *
     * @see ConfigFactory::create()
     * @see Config::get()
     */
    #[Test]
    public function createUsesProvidedEnvironment(): void
    {
        $base = $this->makeTempConfigBase();

        // База и env/prod
        $this->putPhpArray($base . '/config/app.php', ['x' => 1]);
        $this->mkdirp($base . '/config/prod');
        $this->putPhpArray($base . '/config/prod/app.php', ['x' => 2]);

        $config = new ConfigFactory(environment: 'prod', baseDir: $base)->create();

        // Должно примениться prod-переопределение
        $this->assertSame(2, $config->get('app.x'));

        $this->cleanup($base);
    }

    /**
     * Проверим, что PSB_CONFIG_EXTRA читается из env и файлы применяются по порядку.
     *
     * @see ConfigFactory::create()
     * @see Config::get()
     */
    #[Test]
    public function createReadsExtrasFromEnvironment(): void
    {
        $base = $this->makeTempConfigBase();

        // База
        $this->putPhpArray($base . '/config/app.php', ['x' => 1]);

        // Extra файлы
        $this->mkdirp($base . '/extra1');
        $this->mkdirp($base . '/extra2');
        $extra1 = $base . '/extra1/app.php';
        $extra2 = $base . '/extra2/app.php';
        $this->putPhpArray($extra1, ['x' => 5]);
        $this->putPhpArray($extra2, ['x' => 7]);

        $prev = getenv('PSB_CONFIG_EXTRA');
        putenv('PSB_CONFIG_EXTRA=' . $extra1 . ',' . $extra2);

        $config = new ConfigFactory(environment: 'dev', baseDir: $base)->create();

        // Должно взять значение из последнего extra
        $this->assertSame(7, $config->get('app.x'));

        // Вернём окружение в исходное состояние
        $this->restoreEnv('PSB_CONFIG_EXTRA', $prev);
        $this->cleanup($base);
    }

    /**
     * Проверим, что конфиг сохраняется в кеше с ключом окружения.
     *
     * @see ConfigFactory::create()
     */
    #[Test]
    public function createStoresCacheByEnvironmentKey(): void
    {
        $base = $this->makeTempConfigBase();

        $this->putPhpArray($base . '/config/app.php', ['x' => 1]);

        $cache = new SimpleCache(new ArrayDriver());

        $config = new ConfigFactory(environment: 'dev', baseDir: $base, cache: $cache)->create();

        $key    = ConfigFactory::cacheKeyForEnvironment('dev');
        $cached = $cache->get($key);

        $this->assertIsArray($cached);
        $this->assertSame($config->all(), $cached);

        $this->cleanup($base);
    }

    /**
     * Проверим, что при наличии кеша фабрика читает конфиг из него.
     *
     * @see ConfigFactory::create()
     */
    #[Test]
    public function createReadsConfigFromCache(): void
    {
        $base = $this->makeTempConfigBase();

        $this->putPhpArray($base . '/config/app.php', ['x' => 1]);

        $cache = new SimpleCache(new ArrayDriver());

        $cache->set(ConfigFactory::cacheKeyForEnvironment('dev'), ['app' => ['x' => 9]]);

        $config = new ConfigFactory(environment: 'dev', baseDir: $base, cache: $cache)->create();

        $this->assertSame(9, $config->get('app.x'));

        $this->cleanup($base);
    }

    #[Test]
    public function cachedApplicationKeysRemainOrdinaryConfigValues(): void
    {
        $base  = $this->makeTempConfigBase();
        $cache = new SimpleCache(new ArrayDriver());

        $cache->set(ConfigFactory::cacheKeyForEnvironment('demo'), [
            'app' => [
                'env'   => 'prod',
                'debug' => false,
            ],
        ]);

        $config = new ConfigFactory(
            environment: 'demo',
            baseDir: $base,
            cache: $cache,
        )->create();

        self::assertSame('prod', $config->get('app.env'));
        self::assertFalse($config->get('app.debug'));

        $this->cleanup($base);
    }

    // --- helpers ---

    private function makeTempConfigBase(): string
    {
        $base = sys_get_temp_dir() . '/psb_cfg_' . bin2hex(random_bytes(8));
        $this->mkdirp($base . '/config');

        return $base;
    }

    private function mkdirp(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    private function putPhpArray(string $path, array $data): void
    {
        $dir = dirname($path);
        $this->mkdirp($dir);
        $code = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        file_put_contents($path, $code);
    }

    private function putPhpRaw(string $path, string $phpReturnStatement): void
    {
        $dir = dirname($path);
        $this->mkdirp($dir);
        $code = "<?php\n\n" . $phpReturnStatement . "\n";
        file_put_contents($path, $code);
    }

    private function cleanup(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $it = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);

        $ri = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($ri as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($path);
    }

    private function restoreEnv(string $name, mixed $prev): void
    {
        if ($prev === false || $prev === null) {
            // unset
            putenv($name);
        } else {
            putenv($name . '=' . (string) $prev);
        }
    }
}

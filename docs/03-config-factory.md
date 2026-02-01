# ConfigFactory

`ConfigFactory` собирает конфигурацию из каталога `config/` по слоям:

1. `config/*.php` (кроме `container.php`)
2. `config/{env}/*.php`
3. `config/local/*.php`
4. extra-файлы из списка

Каждый следующий слой перекрывает предыдущий (рекурсивно).

```php
<?php

use PhpSoftBox\Config\ConfigFactory;

$factory = new ConfigFactory(
    environment: 'prod',
    baseDir: __DIR__ . '/..',
    extra: __DIR__ . '/../extra.php'
);

$config = $factory->create();
```

Если нужно расшифровывать `EncryptedValue`, передайте резолвер:

```php
<?php

use PhpSoftBox\Config\ConfigFactory;
use PhpSoftBox\Encryptor\Encryptor;

$factory = new ConfigFactory(
    environment: 'prod',
    baseDir: __DIR__ . '/..',
    encryptedValueResolver: new Encryptor(defaultKey: 'secret-key')
);

$config = $factory->create();
```

## Окружение и переменные среды

- `PSB_CONFIG_EXTRA` — список дополнительных файлов через запятую

`ConfigFactory` всегда получает готовую непустую строку окружения. Определение `APP_ENV`,
выбор enum case и нормализация относятся к bootstrap приложения и выполняются до фабрики.
`PSB_CONFIG_EXTRA` по умолчанию читается из окружения.

`PSB_CONFIG_EXTRA` нужен для точечных переопределений без правок в репозитории
(локальные/CI/стендовые файлы, временные хотфиксы).

Имя файла используется как верхнеуровневый ключ. Например, чтобы переопределить `app.*`,
extra-файл должен называться `app.php`.

Пример:

```bash
export PSB_CONFIG_EXTRA=/etc/psb/config.local.php,/tmp/config.override.php
```

```php
<?php

use PhpSoftBox\Config\ConfigFactory;

$config = (new ConfigFactory(
    environment: 'prod',
    baseDir: __DIR__ . '/..',
))->create();

// Если в extra-файлах есть файл db.php с ['host' => 'remote']
$host = $config->get('db.host');
```

## Структура config/

```
config/
  app.php
  database.php
  prod/
    app.php
  local/
    app.php
```

Имя файла становится верхнеуровневым ключом конфигурации. Например, `config/app.php`
даёт доступ по ключам `app.*`, а `config/database.php` — по `database.*`.
Для переопределений используйте файлы с тем же именем в `config/{env}/` или `config/local/`.

Файлы конфигурации должны возвращать массив. Если файл возвращает не массив, он игнорируется.

## Пример с Env + Config + PHP-DI

Ниже пример, где:

- Env загружается первым и заполняет `env()` хелпер.
- `ConfigFactory` получает окружение из Env (это важно для правильного cache key).
- DI контейнер собирает `Config` как сервис.

`bootstrap.php`:

```php
<?php

declare(strict_types=1);

use DI\ContainerBuilder;

$builder = new ContainerBuilder();
$builder->addDefinitions(__DIR__ . '/config/container.php');

$container = $builder->build();

$config = $container->get(\PhpSoftBox\Config\Config::class);
```

`config/container.php`:

```php
<?php

declare(strict_types=1);

use function DI\factory;

use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use PhpSoftBox\Cache\Driver\ArrayDriver;
use PhpSoftBox\Cache\Psr16\SimpleCache;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Config\ConfigFactory;
use PhpSoftBox\Env\Environment;
use PhpSoftBox\Env\EnvStorage;
use PhpSoftBox\Env\Variables;

return [
    Environment::class => factory(fn () => Environment::create(__DIR__ . '/..')
        ->setEnvironment(null) // null => APP_ENV
        ->setPrefix('APP_')),

    Variables::class => factory(fn (ContainerInterface $c) => $c->get(Environment::class)->load()),

    CacheInterface::class => factory(fn () => new SimpleCache(new ArrayDriver())),

    ConfigFactory::class => factory(function (ContainerInterface $c) {
        $c->get(Variables::class);
        $environment = EnvStorage::value('APP_ENV')->string(default: 'dev') ?? 'dev';

        return new ConfigFactory(
            environment: $environment,
            baseDir: __DIR__ . '/..',
            cache: $c->get(CacheInterface::class),
        );
    }),

    Config::class => factory(fn (ContainerInterface $c) => $c->get(ConfigFactory::class)->create()),
];
```

`config/app.php` может по-прежнему содержать application-level поля `env` и `debug`, но Config не
придаёт им специальной семантики. Типизированное окружение и effective debug относятся к компоненту
Application.

Пример:

```php
<?php

return [
    'env' => env('APP_ENV', 'dev'),
];
```

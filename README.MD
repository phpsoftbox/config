# PhpSoftBox Config

Компонент для чтения и объединения конфигурации с поддержкой вложенных ключей и слоёв окружений.

## Установка

```bash
composer require phpsoftbox/config
```

## Быстрый старт

```php
<?php

declare(strict_types=1);

use PhpSoftBox\Config\Config;
$config = new Config(sources: [
    ['app' => ['name' => 'demo'], 'debug' => false],
    fn () => ['debug' => true],
]);

$name = $config->get('app.name'); // demo
$debug = $config->get('debug');   // true
```

`Config` хранит только конфигурационные данные. Окружение приложения, его сравнение и effective
debug предоставляет компонент `phpsoftbox/application` через `ApplicationEnvironment`.

`ConfigFactory` принимает строковый идентификатор окружения только для выбора конфигурационного
слоя и формирования cache key:

```php
$config = new ConfigFactory(environment: 'dev')->create();
```

Произвольные ключи `app.env` и `app.debug` не имеют специальной семантики.

## Документация

- [docs/01-usage.md](docs/01-usage.md) — доступ к значениям, read-only, ArrayAccess
- [docs/02-merge-options.md](docs/02-merge-options.md) — слияние источников и merge options
- [docs/03-config-factory.md](docs/03-config-factory.md) — ConfigFactory и переменные окружения
- [docs/04-encrypted-value.md](docs/04-encrypted-value.md) — интеграция с EncryptedValue

Настройки в БД вынесены в отдельный компонент:
- `phpsoftbox/db-config`

## CLI

```bash
php psb config:encrypt "secret" --cipherKey="your-key"
php psb config:decrypt "ciphertext" --cipherKey="your-key"
```

Если `--cipherKey` не указан, используется `APP_KEY` из окружения.

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
$config = new ConfigFactory(environment: 'dev', baseDir: __DIR__ . '/..')->create();
```

`baseDir` — корень проекта, обязателен (раньше по умолчанию указывал в `vendor/`).

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

Если `--cipherKey` не указан, используется `APP_KEY`: из `.env` (если установлен `phpsoftbox/env`), затем из
окружения процесса.

`config:cache:clear` очищает кеш окружения приложения (как у `RunnerInterface::environment()` из `phpsoftbox/cli-app`),
а с `--environment` — указанного.

## Пути

`AbstractPath` собирает пути относительно корня проекта и ничего не создаёт: геттеры и `createPath()` работают и на
read-only FS. Каталог, в который будут писать, создаётся явно — `ensureDirectory($path)`.

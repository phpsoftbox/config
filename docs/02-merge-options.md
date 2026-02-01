# Слияние источников

`Config` принимает список источников: массивы, callables или другие `Config`.
Слияние по умолчанию рекурсивное и заменяет скалярные значения.

Настройки слияния:
- `recursive` — рекурсивное слияние (по умолчанию `true`).
- `list` — поведение для списков:
  - `replace` (по умолчанию)
  - `append`
  - `append_unique`

```php
<?php

use PhpSoftBox\Config\Config;

$config = new Config(
    sources: [
        ['db' => ['ports' => [3306, 3307]]],
        ['db' => ['ports' => [4406]]],
    ],
    readOnly: true,
    mergeOptions: ['list' => 'append']
);

// [3306, 3307, 4406]
$ports = $config->get('db.ports');
```

## Примеры list-режимов

`replace` (по умолчанию):

```php
<?php

use PhpSoftBox\Config\Config;

$config = new Config(
    sources: [
        ['list' => [1, 2]],
        ['list' => [3, 4]],
    ],
    mergeOptions: ['list' => 'replace']
);

// [3, 4]
$list = $config->get('list');
```

`append` — конкатенация:

```php
<?php

use PhpSoftBox\Config\Config;

$config = new Config(
    sources: [
        ['list' => [1, 2]],
        ['list' => [3, 4]],
    ],
    mergeOptions: ['list' => 'append']
);

// [1, 2, 3, 4]
$list = $config->get('list');
```

`append_unique` — добавляет, не убирая уже существующие дубликаты, но не повторяет новые:

```php
<?php

use PhpSoftBox\Config\Config;

$config = new Config(
    sources: [
        ['list' => [1, 2, 2]],
        ['list' => [2, 3, 3]],
    ],
    mergeOptions: ['list' => 'append_unique']
);

// [1, 2, 2, 3]
$list = $config->get('list');
```

## Пример recursive=false

Если отключить рекурсивное слияние, вложенные массивы перезаписываются целиком:

```php
<?php

use PhpSoftBox\Config\Config;

$config = new Config(
    sources: [
        ['db' => ['host' => 'localhost', 'ports' => [3306]]],
        ['db' => ['host' => 'remote']],
    ],
    mergeOptions: ['recursive' => false]
);

// ['host' => 'remote']
$db = $config->get('db');
```

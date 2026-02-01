# Использование Config

## Доступ к значениям

- `get('a.b.c')` — вложенные ключи через точку.
- `has('a.b')` — проверка наличия.
- `all()` — получить все данные массивом.

```php
<?php

use PhpSoftBox\Config\Config;
$config = new Config(sources: [['db' => ['host' => 'localhost']]]);

if ($config->has('db.host')) {
    $host = $config->get('db.host');
}
```

## Read-only и ArrayAccess

По умолчанию конфигурация read-only. Для изменения отключите режим:

```php
<?php

use PhpSoftBox\Config\Config;
$config = new Config(sources: [['x' => 1]], readOnly: false);
$config->set('x', 2);
$config['x'] = 3;
unset($config['x']);
```

Если `readOnly = true`, попытка записи выбросит `LogicException`.

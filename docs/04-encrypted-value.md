# EncryptedValue

`Config` умеет расшифровывать значения типа `EncryptedValue`, если передан
`EncryptedValueResolverInterface` (например, `Encryptor`). Расшифровка работает
рекурсивно для массивов.

```php
<?php

use PhpSoftBox\Config\Config;
use PhpSoftBox\Encryptor\EncryptedValue;
use PhpSoftBox\Encryptor\Encryptor;

$encryptor = new Encryptor(defaultKey: 'secret-key');
$hash = $encryptor->encrypt('payload', 'secret-key');

$config = new Config(
    sources: [
        ['secret' => new EncryptedValue($hash)],
    ],
    encryptedValueResolver: $encryptor,
);

$plain = $config->get('secret'); // payload

$config = new Config(
    sources: [
        ['errorhub' => ['project_key' => new EncryptedValue($hash)]],
    ],
    encryptedValueResolver: $encryptor,
);

$resolved = $config->get('errorhub'); // ['project_key' => 'payload']
```

Если резолвер не передан, `get()` выбросит `LogicException` при попытке
получить `EncryptedValue`.

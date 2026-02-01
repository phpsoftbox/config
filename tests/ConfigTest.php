<?php

declare(strict_types=1);

namespace PhpSoftBox\Tests\Config;

use LogicException;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Config\Contracts\ConfigMutableInterface;
use PhpSoftBox\Encryptor\Driver\DriverRegistry;
use PhpSoftBox\Encryptor\Driver\OpenSslDriver;
use PhpSoftBox\Encryptor\EncryptedValue;
use PhpSoftBox\Encryptor\Encryptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function function_exists;

#[CoversClass(Config::class)]
#[CoversMethod(Config::class, 'get')]
#[CoversMethod(Config::class, 'has')]
#[CoversMethod(Config::class, 'override')]
#[CoversMethod(Config::class, 'mutable')]
final class ConfigTest extends TestCase
{
    /**
     * Проверяем объединение источников и доступ по ключам с точкой.
     */
    #[Test]
    public function testMergeAndDotAccess(): void
    {
        $cfg = new Config([
            ['a' => ['b' => 1, 'c' => 2]],
            ['a' => ['c' => 3], 'x' => 10],
        ]);

        $this->assertTrue($cfg->has('a.b'));
        $this->assertSame(1, $cfg->get('a.b'));
        $this->assertSame(3, $cfg->get('a.c'));
        $this->assertSame(10, $cfg->get('x'));
        $this->assertSame('def', $cfg->get('missing', 'def'));
    }

    /**
     * Проверяем, что EncryptedValue расшифровывается при прямом доступе.
     */
    #[Test]
    public function testEncryptedValueResolvedWithEncryptor(): void
    {
        if (!function_exists('openssl_encrypt')) {
            $this->markTestSkipped('OpenSSL extension is required for this test.');
        }

        $encryptor = new Encryptor(
            registry: new DriverRegistry([new OpenSslDriver()]),
            defaultKey: 'secret-key',
        );

        $ciphertext = $encryptor->encrypt('payload', 'secret-key');

        $cfg = new Config(
            [
                ['secret' => new EncryptedValue($ciphertext)],
            ],
            encryptedValueResolver: $encryptor,
        );

        $this->assertSame('payload', $cfg->get('secret'));
    }

    /**
     * Проверяем, что EncryptedValue расшифровывается рекурсивно в массивах.
     */
    #[Test]
    public function testEncryptedValueResolvedRecursively(): void
    {
        if (!function_exists('openssl_encrypt')) {
            $this->markTestSkipped('OpenSSL extension is required for this test.');
        }

        $encryptor = new Encryptor(
            registry: new DriverRegistry([new OpenSslDriver()]),
            defaultKey: 'secret-key',
        );

        $ciphertext = $encryptor->encrypt('payload', 'secret-key');

        $cfg = new Config(
            [
                [
                    'errorhub' => [
                        'project_key' => new EncryptedValue($ciphertext),
                        'nested'      => [
                            'token' => new EncryptedValue($ciphertext),
                        ],
                    ],
                ],
            ],
            encryptedValueResolver: $encryptor,
        );

        $config = $cfg->get('errorhub');

        $this->assertIsArray($config);
        $this->assertSame('payload', $config['project_key']);
        $this->assertSame('payload', $config['nested']['token']);
    }

    /**
     * Проверяем, что без Encryptor попытка получить EncryptedValue вызывает исключение.
     */
    #[Test]
    public function testEncryptedValueWithoutResolverThrows(): void
    {
        $cfg = new Config([
            [
                'errorhub' => [
                    'project_key' => new EncryptedValue('ciphertext'),
                ],
            ],
        ]);

        $this->expectException(LogicException::class);

        $cfg->get('errorhub');
    }

    /**
     * Проверяем, что mutable-адаптер может временно переопределять readOnly-конфиг.
     */
    #[Test]
    public function testMutableAdapterOverridesReadOnlyConfig(): void
    {
        $cfg = new Config(
            sources: [[
                'pushr' => [
                    'app_id' => 'app-old',
                ],
            ]],
            readOnly: true,
        );

        $mutable = $cfg->mutable();
        $this->assertInstanceOf(ConfigMutableInterface::class, $mutable);

        $mutable->override('pushr.app_id', 'app-new');

        $this->assertSame('app-new', $cfg->get('pushr.app_id'));

        $this->expectException(LogicException::class);
        $cfg->set('pushr.app_id', 'app-fail');
    }

}

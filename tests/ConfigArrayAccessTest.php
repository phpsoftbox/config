<?php

declare(strict_types=1);

namespace PhpSoftBox\Tests\Config;

use LogicException;
use PhpSoftBox\Config\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
#[CoversMethod(Config::class, 'offsetExists')]
#[CoversMethod(Config::class, 'offsetGet')]
#[CoversMethod(Config::class, 'offsetSet')]
#[CoversMethod(Config::class, 'offsetUnset')]
#[CoversMethod(Config::class, 'withReadOnly')]
#[CoversMethod(Config::class, 'set')]
final class ConfigArrayAccessTest extends TestCase
{
    /**
     * Проверяет ArrayAccess: exists/get/set/unset и режим readOnly.
     *
     * @see Config::offsetExists()
     * @see Config::offsetGet()
     * @see Config::offsetSet()
     * @see Config::offsetUnset()
     * @see Config::withReadOnly()
     * @see Config::set()
     */
    #[Test]
    public function arrayAccessAndReadOnly(): void
    {
        $cfg = new Config([['a' => ['b' => 1]]], readOnly: false);

        $this->assertTrue(isset($cfg['a.b']));
        $this->assertSame(1, $cfg['a.b']);

        $cfg['a.c'] = 2;
        $this->assertSame(2, $cfg['a.c']);

        unset($cfg['a.c']);
        $this->assertNull($cfg['a.c']);

        $ro = new Config([['x' => 1]], readOnly: true);

        $this->expectException(LogicException::class);
        $ro['x'] = 2;
    }
}

<?php

declare(strict_types=1);

namespace PhpSoftBox\Tests\Config;

use LogicException;
use PhpSoftBox\Config\Config;
use PHPUnit\Framework\TestCase;

final class ConfigMergeTest extends TestCase
{
    public function testDefaultRecursiveReplace(): void
    {
        $cfg = new Config([
            ['db' => ['host' => 'localhost', 'ports' => [3306, 3307]], 'debug' => false],
            ['db' => ['host' => 'remote', 'ports' => [4406]], 'debug' => true],
        ]);

        $this->assertSame('remote', $cfg->get('db.host'));
        $this->assertSame([4406], $cfg->get('db.ports'));
        $this->assertTrue($cfg->get('debug'));
    }

    public function testListAppendMode(): void
    {
        $cfg = new Config([
            ['list' => [1, 2]],
            ['list' => [3, 4]],
        ], readOnly: true, mergeOptions: ['list' => 'append']);

        $this->assertSame([1,2,3,4], $cfg->get('list'));
    }

    public function testListAppendUniqueMode(): void
    {
        $cfg = new Config([
            ['list' => [1, 2, 2]],
            ['list' => [2, 3, 3]],
        ], readOnly: true, mergeOptions: ['list' => 'append_unique']);

        $this->assertSame([1,2,2,3], $cfg->get('list'));
    }

    public function testNonRecursiveReplace(): void
    {
        $cfg = new Config([
            ['a' => ['x' => 1], 'b' => 2],
            ['a' => ['y' => 10]],
        ], readOnly: true, mergeOptions: ['recursive' => false]);

        $this->assertSame(['y' => 10], $cfg->get('a'));
        $this->assertSame(2, $cfg->get('b'));
    }

    public function testCallableSources(): void
    {
        $base = new Config([
            ['env' => 'dev', 'db' => ['host' => 'localhost', 'ports' => [3306]]],
        ]);

        $fromCallable       = fn () => ['db' => ['ports' => [3307]]];
        $fromConfigCallable = fn () => new Config([
            ['db' => ['host' => 'remote']],
        ]);

        $cfg = new Config([
                    $base,
                    $fromCallable,
                    $fromConfigCallable,
                ], readOnly: true, mergeOptions: ['list' => 'append']);

        $this->assertSame('remote', $cfg->get('db.host'));
        $this->assertSame([3306, 3307], $cfg->get('db.ports'));
        $this->assertSame('dev', $cfg->get('env'));
    }

    public function testReadOnlySetThrows(): void
    {
        $cfg = new Config([['x' => 1]], readOnly: true);

        $this->expectException(LogicException::class);
        $cfg->set('x', 2);
    }
}

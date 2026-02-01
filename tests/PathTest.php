<?php

declare(strict_types=1);

namespace PhpSoftBox\Tests\Config;

use PhpSoftBox\Config\Path\AbstractPath;
use PHPUnit\Framework\TestCase;

use function rtrim;
use function sys_get_temp_dir;
use function uniqid;

final class PathTest extends TestCase
{
    public function testCreatePathTrimsSlashes(): void
    {
        $baseDir = $this->tempBaseDir();
        $path    = new TestPath($baseDir . '/');

        $this->assertSame($baseDir . '/local/cache', $path->createPath('/local/cache/'));
    }

    public function testPathJoinsNestedSegments(): void
    {
        $baseDir = $this->tempBaseDir();
        $path    = new TestPath($baseDir);

        $this->assertSame($baseDir . '/local/cache', $path->pathPublic('local', 'cache'));
        $this->assertSame($baseDir . '/local/cache', $path->pathPublic('/local/', '/cache/'));
    }

    public function testPathWithEmptyPartsReturnsBase(): void
    {
        $baseDir = $this->tempBaseDir();
        $path    = new TestPath($baseDir . '/');

        $this->assertSame($baseDir, $path->pathPublic());
        $this->assertSame($baseDir, $path->pathPublic(''));
    }

    private function tempBaseDir(): string
    {
        return rtrim(sys_get_temp_dir(), '/') . '/psb-path-' . uniqid('', true);
    }
}

final class TestPath extends AbstractPath
{
    public function pathPublic(string ...$parts): string
    {
        return $this->path(...$parts);
    }
}

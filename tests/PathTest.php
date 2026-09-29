<?php

declare(strict_types=1);

namespace PhpSoftBox\Tests\Config;

use PhpSoftBox\Config\Path\AbstractPath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function rmdir;
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

    /**
     * Проверим, что геттеры пути ничего не создают, а ensureDirectory() создаёт каталог явно.
     *
     * @see AbstractPath::createPath()
     * @see AbstractPath::ensureDirectory()
     */
    #[Test]
    public function gettersHaveNoSideEffects(): void
    {
        $baseDir = $this->tempBaseDir();
        $path    = new TestPath($baseDir);

        $cache = $path->createPath('local/cache');
        self::assertDirectoryDoesNotExist($cache);

        self::assertSame($cache, $path->ensureDirectory($cache));
        self::assertDirectoryExists($cache);

        rmdir($cache);
        rmdir($baseDir . '/local');
        rmdir($baseDir);
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

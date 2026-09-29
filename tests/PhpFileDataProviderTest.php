<?php

declare(strict_types=1);

namespace PhpSoftBox\Tests\Config;

use PhpSoftBox\Config\Provider\PhpFileDataProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;
use function var_export;

#[CoversClass(PhpFileDataProvider::class)]
#[CoversMethod(PhpFileDataProvider::class, 'load')]
final class PhpFileDataProviderTest extends TestCase
{
    /**
     * Проверим, что списки из разных файлов провайдера не сливаются по индексу: следующий файл заменяет список, как при
     * слиянии слоёв Config.
     *
     * @see PhpFileDataProvider::load()
     */
    #[Test]
    public function replacesListsInsteadOfMergingByIndex(): void
    {
        $dir = sys_get_temp_dir() . '/psb_provider_' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/a.php', '<?php return ' . var_export(['queue' => ['workers' => ['mail', 'sms', 'push']]], true) . ';');
        file_put_contents($dir . '/b.php', '<?php return ' . var_export(['queue' => ['workers' => ['mail']]], true) . ';');

        $data = new PhpFileDataProvider($dir . '/*.php', keyByFilename: false)->load();

        self::assertSame(['mail'], $data['queue']['workers']);

        unlink($dir . '/a.php');
        unlink($dir . '/b.php');
        rmdir($dir);
    }
}

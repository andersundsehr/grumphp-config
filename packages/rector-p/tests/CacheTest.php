<?php

declare(strict_types=1);

namespace Andersundsehr\RectorP\Tests;

use Andersundsehr\RectorP\Cache;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cache::class)]
final class CacheTest extends TestCase
{
    private string $directory;

    private string $file;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/rector-p-cache-test-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->file = $this->directory . '/Example.php';
        file_put_contents($this->file, '<?php echo 1;');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testFileIsProcessedUntilItChanges(): void
    {
        $cache = new Cache($this->directory);
        self::assertFalse($cache->isProcessed($this->file));

        $cache->setProcessed($this->file);
        self::assertTrue($cache->isProcessed($this->file));

        file_put_contents($this->file, '<?php echo 2;');
        self::assertFalse($cache->isProcessed($this->file));
    }

    public function testEveryConfigHasItsOwnProgress(): void
    {
        $cache = new Cache($this->directory);
        $cache->useConfigFile('/project/rector-a.php');
        $cache->setProcessed($this->file);

        $cache->useConfigFile('/project/rector-b.php');
        self::assertFalse($cache->isProcessed($this->file));

        $cache->setProcessed($this->file);
        $cache->clear();
        self::assertFalse($cache->isProcessed($this->file));

        $cache->useConfigFile('/project/rector-a.php');
        self::assertTrue($cache->isProcessed($this->file), 'clear() must only remove the progress of the current config');
        self::assertFileExists($this->directory . '/cache-' . hash('sha256', '/project/rector-a.php') . '.json');
    }

    public function testStoresTheProgressInThePackageDirectoryByDefault(): void
    {
        $configFile = '/project/rector-' . bin2hex(random_bytes(8)) . '.php';
        $expectedFile = __DIR__ . '/../cache-' . hash('sha256', $configFile) . '.json';
        $cache = new Cache();
        $cache->useConfigFile($configFile);

        try {
            $cache->setProcessed($this->file);
            self::assertFileExists($expectedFile);
        } finally {
            // removes the file wherever it was written, also when the assertion failed
            $cache->clear();
        }

        self::assertFileDoesNotExist($expectedFile);
    }

    public function testRectorPhpInTheCurrentDirectoryKeepsCacheJson(): void
    {
        $cache = new Cache($this->directory);
        $cache->useConfigFile(getcwd() . DIRECTORY_SEPARATOR . 'rector.php');
        $cache->setProcessed($this->file);

        self::assertFileExists($this->directory . '/cache.json');
    }

    public function testProgressFileIsReadable(): void
    {
        $cache = new Cache($this->directory);
        $cache->useConfigFile('/project/rector-a.php');
        $cache->setProcessed($this->file);

        $expected = "{\n    \"" . $this->file . '": "' . hash_file('sha256', $this->file) . "\"\n}";
        self::assertSame($expected, file_get_contents($this->directory . '/cache-' . hash('sha256', '/project/rector-a.php') . '.json'));
    }

    public function testFileNameThatIsNotUtf8Throws(): void
    {
        $file = $this->directory . "/\xff.php";
        if (@file_put_contents($file, '<?php echo 1;') === false) {
            self::markTestSkipped('the file system does not allow file names that are not UTF-8');
        }

        $cache = new Cache($this->directory);

        $this->expectException(JsonException::class);
        $cache->setProcessed($file);
    }

    public function testClearWithoutProgressDoesNothing(): void
    {
        $cache = new Cache($this->directory);
        $cache->clear();

        self::assertSame([$this->file], glob($this->directory . '/*'));
    }
}

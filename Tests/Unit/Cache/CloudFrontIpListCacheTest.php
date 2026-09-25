<?php

declare(strict_types=1);

namespace Mfd\CloudfrontTrustedProxies\Tests\Unit\Cache;

use Mfd\CloudfrontTrustedProxies\Cache\CloudFrontIpListCache;
use Mfd\CloudfrontTrustedProxies\Http\CloudFrontIpRangesClient;
use TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class CloudFrontIpListCacheTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private string $compiledFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiledFile = Environment::getVarPath() . '/cache/code/cloudfront_trusted_proxies/edge_server_ips.php';
        @unlink($this->compiledFile);
        $this->resetProcessCache();
    }

    private function createCache(): PhpFrontend
    {
        return new PhpFrontend(CloudFrontIpListCache::CACHE_IDENTIFIER, new SimpleFileBackend('Testing'));
    }

    protected function tearDown(): void
    {
        @unlink($this->compiledFile);
        $this->resetProcessCache();
        parent::tearDown();
    }

    private function resetProcessCache(): void
    {
        $property = new \ReflectionProperty(CloudFrontIpListCache::class, 'processCache');
        $property->setValue(null, null);
    }

    public function testGetFetchesAndCompilesOnFirstCall(): void
    {
        $client = $this->createMock(CloudFrontIpRangesClient::class);
        $client->expects($this->once())->method('fetchIpRanges')->willReturn(['13.32.0.0/15']);

        $subject = new CloudFrontIpListCache($client, $this->createCache());

        self::assertSame(['13.32.0.0/15'], $subject->get());
        self::assertFileExists($this->compiledFile);
    }

    public function testGetReadsFromCompiledFileWithoutRefetchingWhenFresh(): void
    {
        $client = $this->createMock(CloudFrontIpRangesClient::class);
        $client->expects($this->once())->method('fetchIpRanges')->willReturn(['13.32.0.0/15']);

        (new CloudFrontIpListCache($client, $this->createCache()))->get();
        $this->resetProcessCache();

        $secondCallClient = $this->createMock(CloudFrontIpRangesClient::class);
        $secondCallClient->expects($this->never())->method('fetchIpRanges');

        self::assertSame(['13.32.0.0/15'], (new CloudFrontIpListCache($secondCallClient, $this->createCache()))->get());
    }

    public function testGetFallsBackToStaleCompiledDataWhenRefetchFails(): void
    {
        $client = $this->createStub(CloudFrontIpRangesClient::class);
        $client->method('fetchIpRanges')->willReturn(['13.32.0.0/15']);
        (new CloudFrontIpListCache($client, $this->createCache()))->get();
        $this->resetProcessCache();

        // Force the compiled data to look expired.
        $stale = include $this->compiledFile;
        $stale['fetchedAt'] = 0;
        file_put_contents($this->compiledFile, "<?php\n\nreturn " . var_export($stale, true) . ';');

        $failingClient = $this->createStub(CloudFrontIpRangesClient::class);
        $failingClient->method('fetchIpRanges')->willThrowException(new \RuntimeException('boom'));

        self::assertSame(['13.32.0.0/15'], (new CloudFrontIpListCache($failingClient, $this->createCache()))->get());
    }
}

<?php

declare(strict_types=1);

namespace Mfd\CloudfrontTrustedProxies\Tests\Unit\Middleware;

use Mfd\CloudfrontTrustedProxies\Cache\CloudFrontIpListCache;
use Mfd\CloudfrontTrustedProxies\Middleware\CloudFrontEdgeServerIpMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class CloudFrontEdgeServerIpMiddlewareTest extends UnitTestCase
{
    private ?string $originalReverseProxyIp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalReverseProxyIp = $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] ?? null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] = $this->originalReverseProxyIp;
        parent::tearDown();
    }

    private function createHandler(): RequestHandlerInterface
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response());

        return $handler;
    }

    private function createExtensionConfiguration(bool $trustEdgeServerIps): ExtensionConfiguration
    {
        $extensionConfiguration = $this->createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturnMap([
            ['cloudfront_trusted_proxies', 'trustEdgeServerIps', $trustEdgeServerIps],
        ]);

        return $extensionConfiguration;
    }

    public function testAppendsEdgeServerIpsToExistingReverseProxyIp(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] = '10.0.0.0/8';

        $cache = $this->createStub(CloudFrontIpListCache::class);
        $cache->method('get')->willReturn(['13.32.0.0/15', '2600:9000::/28']);

        (new CloudFrontEdgeServerIpMiddleware($cache, $this->createExtensionConfiguration(true)))
            ->process(new ServerRequest('https://example.com/'), $this->createHandler());

        self::assertSame(
            '10.0.0.0/8,13.32.0.0/15,2600:9000::/28',
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'],
        );
    }

    public function testLeavesReverseProxyIpUntouchedWhenCacheIsEmpty(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] = '10.0.0.0/8';

        $cache = $this->createStub(CloudFrontIpListCache::class);
        $cache->method('get')->willReturn([]);

        (new CloudFrontEdgeServerIpMiddleware($cache, $this->createExtensionConfiguration(true)))
            ->process(new ServerRequest('https://example.com/'), $this->createHandler());

        self::assertSame('10.0.0.0/8', $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP']);
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] = '10.0.0.0/8';

        $cache = $this->createMock(CloudFrontIpListCache::class);
        $cache->expects($this->never())->method('get');

        (new CloudFrontEdgeServerIpMiddleware($cache, $this->createExtensionConfiguration(false)))
            ->process(new ServerRequest('https://example.com/'), $this->createHandler());

        self::assertSame('10.0.0.0/8', $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP']);
    }
}

<?php

declare(strict_types=1);

namespace Mfd\CloudfrontTrustedProxies\Middleware;

use Mfd\CloudfrontTrustedProxies\Cache\CloudFrontIpListCache;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Appends AWS CloudFront's published edge IPs to SYS/reverseProxyIP so TYPO3
 * trusts their X-Forwarded-* headers when resolving the real client IP. Runs
 * in the 'core' stack, before normalized-params-attribute — that's the
 * middleware that actually reads reverseProxyIP, so this has to land before it.
 */
class CloudFrontEdgeServerIpMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CloudFrontIpListCache $cache,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->isEnabled()) {
            return $handler->handle($request);
        }

        $edgeServerIps = $this->cache->get();
        if ($edgeServerIps !== []) {
            $configured = trim((string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] ?? ''));
            $configuredIps = $configured !== '' ? explode(',', $configured) : [];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP'] = implode(',', [...$configuredIps, ...$edgeServerIps]);
        }

        return $handler->handle($request);
    }

    private function isEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get('cloudfront_trusted_proxies', 'trustEdgeServerIps');
        } catch (\Exception) {
            return false;
        }
    }
}

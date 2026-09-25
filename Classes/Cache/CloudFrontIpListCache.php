<?php

declare(strict_types=1);

namespace Mfd\CloudfrontTrustedProxies\Cache;

use Mfd\CloudfrontTrustedProxies\Http\CloudFrontIpRangesClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Cache\Exception as CacheException;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;

/**
 * Compiled-PHP-array cache for AWS CloudFront's published edge IP ranges, so
 * it's served from OPcache on every request instead of being re-fetched from
 * AWS's API. Refetched at most once per TTL; a stale/missing/corrupt compiled
 * entry just triggers one synchronous refetch rather than blocking the request
 * forever — a failed refetch falls back to whatever was last written (or an
 * empty list).
 *
 * Storage goes through TYPO3's caching framework (registered as the
 * "cloudfront_trusted_proxies" cache in ext_localconf.php) instead of
 * hand-rolled file I/O; staleness is tracked ourselves via the stored
 * "fetchedAt" so a request can still fall back to expired data instead of
 * losing it once the TTL passes.
 */
class CloudFrontIpListCache implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const CACHE_IDENTIFIER = 'cloudfront_trusted_proxies';

    private const TTL_SECONDS = 86400;

    private const ENTRY_IDENTIFIER = 'edge_server_ips';

    /** @var string[]|null */
    private static ?array $processCache = null;

    public function __construct(
        private readonly CloudFrontIpRangesClient $client,
        private readonly PhpFrontend $cache,
    ) {
    }

    /**
     * @return string[]
     */
    public function get(): array
    {
        if (self::$processCache !== null) {
            return self::$processCache;
        }

        $compiled = $this->read();

        if ($compiled === null || time() - $compiled['fetchedAt'] > self::TTL_SECONDS) {
            $refetched = $this->fetch();
            if ($refetched !== null) {
                $compiled = ['fetchedAt' => time(), 'ips' => $refetched];
                $this->write($compiled);
            }
        }

        return self::$processCache = $compiled['ips'] ?? [];
    }

    /**
     * @return string[]|null
     */
    private function fetch(): ?array
    {
        try {
            return $this->client->fetchIpRanges();
        } catch (\Exception $exception) {
            $this->logger?->error('Could not fetch AWS CloudFront edge IP ranges', ['exception' => $exception]);
            return null;
        }
    }

    /**
     * @return array{fetchedAt: int, ips: string[]}|null
     */
    private function read(): ?array
    {
        $data = $this->cache->require(self::ENTRY_IDENTIFIER);
        if (!is_array($data) || !isset($data['fetchedAt'], $data['ips']) || !is_array($data['ips'])) {
            return null;
        }

        return $data;
    }

    /**
     * @param array{fetchedAt: int, ips: string[]} $data
     */
    private function write(array $data): void
    {
        try {
            $this->cache->set(self::ENTRY_IDENTIFIER, 'return ' . var_export($data, true) . ';');
        } catch (CacheException $exception) {
            $this->logger?->error('Could not write AWS CloudFront edge IP range cache', ['exception' => $exception]);
        }
    }
}

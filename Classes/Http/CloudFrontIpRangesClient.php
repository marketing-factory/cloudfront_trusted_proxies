<?php

declare(strict_types=1);

namespace Mfd\CloudfrontTrustedProxies\Http;

use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Fetches AWS's published IP ranges and extracts the ones used by CloudFront
 * (both IPv4 and IPv6 prefixes are listed in the same document).
 *
 * @see https://docs.aws.amazon.com/vpc/latest/userguide/aws-ip-ranges.html
 */
class CloudFrontIpRangesClient
{
    private const IP_RANGES_URL = 'https://ip-ranges.amazonaws.com/ip-ranges.json';

    private const SERVICE = 'CLOUDFRONT';

    public function __construct(private readonly RequestFactory $requestFactory)
    {
    }

    /**
     * @return string[]
     */
    public function fetchIpRanges(): array
    {
        $response = $this->requestFactory->request(self::IP_RANGES_URL, 'GET', ['timeout' => 5]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                'AWS IP ranges request to ' . self::IP_RANGES_URL . " failed with status {$response->getStatusCode()}",
                4477201853,
            );
        }

        $data = json_decode((string)$response->getBody(), true);
        if (!is_array($data)
            || !isset($data['prefixes'], $data['ipv6_prefixes'])
            || !is_array($data['prefixes'])
            || !is_array($data['ipv6_prefixes'])
        ) {
            throw new \RuntimeException(
                'AWS IP ranges response from ' . self::IP_RANGES_URL . ' was not in the expected format',
                9821345067,
            );
        }

        return [
            ...$this->extractPrefixes($data['prefixes'], 'ip_prefix'),
            ...$this->extractPrefixes($data['ipv6_prefixes'], 'ipv6_prefix'),
        ];
    }

    /**
     * @param array<mixed> $entries
     * @return string[]
     */
    private function extractPrefixes(array $entries, string $prefixKey): array
    {
        $prefixes = [];
        foreach ($entries as $entry) {
            if (is_array($entry)
                && ($entry['service'] ?? null) === self::SERVICE
                && is_string($entry[$prefixKey] ?? null)
                && $entry[$prefixKey] !== ''
            ) {
                $prefixes[] = $entry[$prefixKey];
            }
        }

        // CloudFront prefixes can be listed once per network border group, so the same prefix may repeat.
        return array_values(array_unique($prefixes));
    }
}

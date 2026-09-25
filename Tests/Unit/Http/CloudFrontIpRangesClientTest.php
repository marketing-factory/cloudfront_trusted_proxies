<?php

declare(strict_types=1);

namespace Mfd\CloudfrontTrustedProxies\Tests\Unit\Http;

use Mfd\CloudfrontTrustedProxies\Http\CloudFrontIpRangesClient;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class CloudFrontIpRangesClientTest extends UnitTestCase
{
    public function testFetchIpRangesExtractsCloudFrontPrefixesFromBothListsAndDropsOtherServices(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects($this->once())->method('request')->with(
            'https://ip-ranges.amazonaws.com/ip-ranges.json',
            'GET',
            ['timeout' => 5],
        )->willReturn(new Response(body: $this->jsonStream([
            'prefixes' => [
                ['ip_prefix' => '3.0.0.0/15', 'service' => 'CLOUDFRONT'],
                ['ip_prefix' => '13.32.0.0/15', 'service' => 'CLOUDFRONT'],
                ['ip_prefix' => '10.0.0.0/8', 'service' => 'EC2'],
            ],
            'ipv6_prefixes' => [
                ['ipv6_prefix' => '2600:9000::/28', 'service' => 'CLOUDFRONT'],
                ['ipv6_prefix' => '2406:da00::/32', 'service' => 'EC2'],
            ],
        ])));

        $subject = new CloudFrontIpRangesClient($requestFactory);

        self::assertSame(
            ['3.0.0.0/15', '13.32.0.0/15', '2600:9000::/28'],
            $subject->fetchIpRanges(),
        );
    }

    public function testFetchIpRangesDeduplicatesRepeatedPrefixes(): void
    {
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturn(new Response(body: $this->jsonStream([
            'prefixes' => [
                ['ip_prefix' => '13.32.0.0/15', 'service' => 'CLOUDFRONT', 'network_border_group' => 'us-east-1'],
                ['ip_prefix' => '13.32.0.0/15', 'service' => 'CLOUDFRONT', 'network_border_group' => 'us-west-2'],
            ],
            'ipv6_prefixes' => [],
        ])));

        $subject = new CloudFrontIpRangesClient($requestFactory);

        self::assertSame(['13.32.0.0/15'], $subject->fetchIpRanges());
    }

    public function testFetchIpRangesThrowsOnNonSuccessStatus(): void
    {
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturn(new Response(statusCode: 500));

        $this->expectException(\RuntimeException::class);

        (new CloudFrontIpRangesClient($requestFactory))->fetchIpRanges();
    }

    public function testFetchIpRangesThrowsWhenPrefixesKeyIsMissing(): void
    {
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturn(new Response(body: $this->jsonStream([
            'ipv6_prefixes' => [],
        ])));

        $this->expectException(\RuntimeException::class);

        (new CloudFrontIpRangesClient($requestFactory))->fetchIpRanges();
    }

    public function testFetchIpRangesThrowsOnNonArrayBody(): void
    {
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturn(new Response(body: $this->jsonStream('not-an-array')));

        $this->expectException(\RuntimeException::class);

        (new CloudFrontIpRangesClient($requestFactory))->fetchIpRanges();
    }

    private function jsonStream(mixed $data): Stream
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write(json_encode($data, JSON_THROW_ON_ERROR));
        $stream->rewind();

        return $stream;
    }
}

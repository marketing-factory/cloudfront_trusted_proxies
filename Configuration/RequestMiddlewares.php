<?php

declare(strict_types=1);

use Mfd\CloudfrontTrustedProxies\Middleware\CloudFrontEdgeServerIpMiddleware;

return [
    'core' => [
        'mfd/cloudfront-trusted-proxies/edge-server-ip' => [
            'target' => CloudFrontEdgeServerIpMiddleware::class,
            'after' => [
                'typo3/cms-core/verify-host-header',
            ],
            'before' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
        ],
    ],
];

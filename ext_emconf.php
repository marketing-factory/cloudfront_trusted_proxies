<?php

if (!isset($_EXTKEY)) {
    $_EXTKEY = 'cloudfront_trusted_proxies';
}

$EM_CONF[$_EXTKEY] = [
    'title' => 'CloudFront trusted proxies',
    'description' => "Trust AWS CloudFront's published edge IP ranges as reverse proxies",
    'category' => 'frontend',
    'author' => '',
    'author_email' => '',
    'state' => 'alpha',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];

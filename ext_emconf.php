<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'CloudFront trusted proxies',
    'description' => "Trust AWS CloudFront's published edge IP ranges as reverse proxies",
    'category' => 'misc',
    'author' => 'Ingo Schmitt',
    'author_email' => 'ingo.schmitt@marketing-factory.de',
    'author_company' => 'Marketing Factory Digital GmbH',
    'state' => 'beta',
    'clearCacheOnLoad' => 1,
    'version' => '1.1.5',
    'constraints' => [
        'depends' => [
            'php' => '8.3.0-8.5.99',
            'typo3' => '13.0.0-13.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'cdn_utils' => '*',
        ],
    ],
];

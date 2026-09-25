<?php

declare(strict_types=1);

use Mfd\CloudfrontTrustedProxies\Cache\CloudFrontIpListCache;
use TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;

defined('TYPO3') or die();

if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][CloudFrontIpListCache::CACHE_IDENTIFIER] ?? null)) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][CloudFrontIpListCache::CACHE_IDENTIFIER] = [
        'frontend' => PhpFrontend::class,
        'backend' => SimpleFileBackend::class,
        'groups' => ['system'],
    ];
}

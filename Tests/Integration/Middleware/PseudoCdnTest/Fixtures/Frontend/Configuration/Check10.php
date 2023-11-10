<?php
$GLOBALS['TYPO3_CONF_VARS']['FE']['pseudoCdn'] = [
    'enable' => true,
    'maxConnectionsPerDomain' => 9,
    'maxSubdomains' => 99,
    'search' => '/test/i',
    'ignoreIfContains' => '/test/',
    'baseDomain' => 'test.com',
    'protocol' => 'test://'
];

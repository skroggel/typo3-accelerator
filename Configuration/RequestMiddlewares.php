<?php
return [
    'frontend' => [
        'madj2k/accelerator/middleware/pseudo-cdn' => [
            'target' => \Madj2k\Accelerator\Middleware\PseudoCdn::class,
            'before' => [
                'typo3/cms-frontend/output-compression',
            ],
        ],
        'madj2k/accelerator/middleware/html-minify' => [
            'target' => \Madj2k\Accelerator\Middleware\HtmlMinify::class,
            'after' => [
                'madj2k/accelerator/middleware/pseudo-cdn',
            ],
        ],
        'madj2k/accelerator/middleware/proxy-caching-header' => [
            'target' => \Madj2k\Accelerator\Middleware\ProxyCachingHeader::class,
            'after' => [
                'madj2k/accelerator/middleware/html-minify',
            ],
        ],
    ]
];

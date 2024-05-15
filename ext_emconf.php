<?php

$EM_CONF[$_EXTKEY] = [
	'title' => 'TYPO3 Accelerator',
	'description' => 'Speed up your TYPO3 installation: add Critical CSS (Above The Fold) inline, minify the HTML of your website, use subdomains as CDN to reduce page load, manage proxy-caching (e.g with Varnish) via page-properties, reduce database size when storing JSON-arrays with persisted objects to the database',
	'category' => 'FE',
	'author' => 'Steffen Kroggel',
	'author_email' => 'developer@steffenkroggel.de',
	'shy' => '',
	'priority' => '',
	'module' => '',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => '0',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'version' => '12.4.4',
    'constraints' => [
		'depends' => [
			'typo3' => '10.4.0-12.4.99',
        ],
		'conflicts' => [
		],
		'suggests' => [
            'varnish' => '2.4.0-2.4.99'
		],
	],
];

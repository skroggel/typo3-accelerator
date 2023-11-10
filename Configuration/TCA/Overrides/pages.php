<?php

$tempColumnsPages = [

	'tx_accelerator_proxy_caching' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:accelerator/Resources/Private/Language/locallang_db.xlf:tx_accelerator_domain_model_pages.tx_accelerator_proxy_caching',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'default' => 0,
            'maxitems' => 1,
            'items' => [
                ['LLL:EXT:accelerator/Resources/Private/Language/locallang_db.xlf:tx_accelerator_domain_model_pages.tx_accelerator_proxy_caching.I.inherit', 0],
                ['LLL:EXT:accelerator/Resources/Private/Language/locallang_db.xlf:tx_accelerator_domain_model_pages.tx_accelerator_proxy_caching.I.enabled', 1],
                ['LLL:EXT:accelerator/Resources/Private/Language/locallang_db.xlf:tx_accelerator_domain_model_pages.tx_accelerator_proxy_caching.I.disabled', 2],

            ],
        ],
    ],
    'tx_accelerator_pseudo_cdn' => [
        'exclude' => 1,
       // 'displayCond' => 'FIELD:is_siteroot:REQ:true',
        'label' => 'LLL:EXT:accelerator/Resources/Private/Language/locallang_db.xlf:tx_accelerator_domain_model_pages.tx_accelerator_pseudo_cdn',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'default' => 0,
            'maxitems' => 1,
            'items' => [
                ['LLL:EXT:accelerator/Resources/Private/Language/locallang_db.xlf:tx_accelerator_domain_model_pages.tx_accelerator_pseudo_cdn.I.inherit', 0],
                ['LLL:EXT:accelerator/Resources/Private/Language/locallang_db.xlf:tx_accelerator_domain_model_pages.tx_accelerator_pseudo_cdn.I.enabled', 1],
                ['LLL:EXT:accelerator/Resources/Private/Language/locallang_db.xlf:tx_accelerator_domain_model_pages.tx_accelerator_pseudo_cdn.I.disabled', 2],

            ],
        ],
    ],
];

//===========================================================================
// Add fields
//===========================================================================
// Add TCA
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages',$tempColumnsPages);

// Add fields to the existing palettes
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette('pages', 'access','--linebreak--,tx_accelerator_proxy_caching','after:fe_login_mode');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette('pages', 'miscellaneous','tx_accelerator_pseudo_cdn,--linebreak--','after:is_siteroot');


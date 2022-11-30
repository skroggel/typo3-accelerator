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
];

//===========================================================================
// Add fields
//===========================================================================
// Add TCA
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages',$tempColumnsPages);

// Add field to the existing palette
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette('pages', 'access','--linebreak--,tx_accelerator_proxy_caching','after:fe_login_mode');



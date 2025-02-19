<?php

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') or die('Access denied.');

call_user_func(
    function($extKey)
    {

        $typo3Version = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Information\Typo3Version::class);
        $version = $typo3Version->getMajorVersion();
        if ($version < 12) {
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages',
                [
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
                ]
            );

        } else {
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages',
                [
                    'tx_accelerator_proxy_caching' => [
                        'exclude' => 1,
                        'label' => 'LLL:EXT:accelerator/Resources/Private/Language/locallang_db.xlf:tx_accelerator_domain_model_pages.tx_accelerator_proxy_caching',
                        'config' => [
                            'type' => 'select',
                            'renderType' => 'selectSingle',
                            'default' => 0,
                            'maxitems' => 1,
                            'items' => [
                                [
                                    'label' => 'LLL:EXT:accelerator/Resources/Private/Language/locallang_db.xlf:tx_accelerator_domain_model_pages.tx_accelerator_proxy_caching.I.inherit',
                                    'value' => 0
                                ],
                                [
                                    'label' => 'LLL:EXT:accelerator/Resources/Private/Language/locallang_db.xlf:tx_accelerator_domain_model_pages.tx_accelerator_proxy_caching.I.enabled',
                                    'value' => 1
                                ],
                                [
                                    'label' => 'LLL:EXT:accelerator/Resources/Private/Language/locallang_db.xlf:tx_accelerator_domain_model_pages.tx_accelerator_proxy_caching.I.disabled',
                                    'value' => 2
                                ],
                            ],
                        ],
                    ],
                ]
            );
        }

        $extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('accelerator');
        if (
            (isset($extConf['proxyCachingMode']))
            && ($extConf['proxyCachingMode'] !== 'hetzner')
        ){

            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
                'pages',
                'access',
                '--linebreak--,tx_accelerator_proxy_caching', 'after:fe_group'
            );
        }

    },
    'accelerator'
);

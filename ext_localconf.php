<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die('Access denied.');

call_user_func(
    function($extKey)
    {

        //=================================================================
        // Add Rootline Fields
        //=================================================================
        $rootlineFields = &$GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'];
        $newRootlineFields = 'backend_layout, tx_accelerator_proxy_caching';
        $rootlineFields .= (empty($rootlineFields))? $newRootlineFields : ',' . $newRootlineFields;

        //=================================================================
        // Register Hooks
        //=================================================================
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-preProcess'][] = 'Madj2k\\Accelerator\\Hooks\\CriticalCssHook->render_preProcess';
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-postTransform'][] = 'Madj2k\\Accelerator\\Hooks\\CriticalCssHook->render_postTransform';

        //=================================================================
        // Register Caching
        //=================================================================
        if( !isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$extKey]) ) {
            $GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][$extKey] = [];
        }

        if( !isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$extKey]['frontend'])) {
            $GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][$extKey]['frontend'] = 'TYPO3\\CMS\\Core\\Cache\\Frontend\\VariableFrontend';
        }

        if( !isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][$extKey]['backend'])) {
            $GLOBALS['TYPO3_CONF_VARS'] ['SYS']['caching']['cacheConfigurations'][$extKey]['backend'] = 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend';
        }

        //=================================================================
        // XClasses
        //=================================================================
        if (ExtensionManagementUtility::isLoaded('varnish')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\Opsone\Varnish\Utility\VarnishHttpUtility::class] = [
                'className' => \Madj2k\Accelerator\Utility\VarnishHttpUtility::class,
            ];
        }

        //=================================================================
        // Configure Logger
        //=================================================================
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['Madj2k']['Accelerator']['writerConfiguration'] = array(

            // configuration for WARNING severity, including all
            // levels with higher severity (ERROR, CRITICAL, EMERGENCY)
            \TYPO3\CMS\Core\Log\LogLevel::WARNING => array(
                // add a FileWriter
                'TYPO3\\CMS\\Core\\Log\\Writer\\FileWriter' => array(
                    // configuration for the writer
                    'logFile' => \TYPO3\CMS\Core\Core\Environment::getVarPath() .'/log/tx_accelerator.log'
                )
            ),
        );
    },
    'accelerator'
);



<?php
defined('TYPO3') or die('Access denied.');

call_user_func(
    function($extKey)
    {

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
            $extKey,
            'Configuration/TypoScript',
            'Accelerator'
        );
        
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
            $extKey,
            'Configuration/TypoScript/ResponsiveImages',
            'Acceleratoro - Responsive Images (deprectated)'
        );

    },
    'accelerator'
);

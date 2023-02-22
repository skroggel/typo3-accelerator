<?php

namespace Madj2k\Accelerator\ContentProcessing;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class PseudoCdn
 *
 * @author Steffen Kroggel <developer@steffenkroggel.de>
 * @copyright Steffen Kroggel
 * @package Madj2k_Accelerator
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class PseudoCdn
{

    /**
     * @var array
     */
    protected array $settings = [];


    /**
     * @var int
     */
    protected int $replacementCnt = 1;


    /**
     * @var int
     */
    protected int $domainCnt = 1;


    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->settings = $this->getSettings();
    }


    /**
     * Adds static-domain to links and images
     *
     * @param string $prefix prefix
     * @param string $path path
     * @return string new path
     */
    protected function addDomain(string $prefix, string $path): string
    {

        // check if counter has reached maximum and set new domain
        if ($this->replacementCnt > intval($this->settings['maxConnectionsPerDomain'])) {
            if (($this->domainCnt + 1) <= intval($this->settings['maxSubdomains'])) {
                $this->domainCnt++;
            }
            $this->replacementCnt = 1;
        }

        // cut of leading backslash
        if (strpos($path, '/') === 0) {
            $path = substr($path, 1);
        }

        // strip protocol from domain
        $baseDomain = str_replace($this->settings['protocol'], '', $this->settings['baseDomain']);

        // build new subdomain
        $domain = 'static' . ($this->domainCnt) . '.' . $baseDomain;

        // Add one to counter
        $this->replacementCnt++;

        // add domain to url
        return $prefix . $this->settings['protocol'] . $domain . '/' . $path;
    }


    /**
     * Adds PseudoCDN into content
     *
     * @param string $content content to replace
     * @return string new content
     */
    public function process(string $content): string
    {

        // check if enabled
        if (
            ($this->settings['enable'] != 1)
            || (! $this->settings['maxSubdomains'])
        ){
            return $content;
        }

        // Replace content
        $object = $this;
        $config = $this->settings;
        $callback = function ($matches) use ($object, $config) {

            if ($config['ignoreIfContains']) {
                if (preg_match($config['ignoreIfContains'], $matches[2])) {
                    return $matches[1] . $matches[2];
                }
            }

            return $object->addDomain($matches[1], $matches[2]);
        };

        return preg_replace_callback($this->settings['search'], $callback, $content);
    }


    /**
     * Loads settings
     *
     * @return array
     */
    public function getSettings(): array
    {

        $settings = [
            'enable' => 0,
            'maxConnectionsPerDomain' => 4,
            'maxSubdomains' => 100,
            'search' => '/(href="|src="|srcset=")\/?((uploads\/media|uploads\/pics|typo3temp\/compressor|typo3temp\/GB|typo3conf\/ext|fileadmin)([^"]+))/i',
            'ignoreIfContains' => '/\.css|\.js|\?noCdn=1/',
            'baseDomain' => preg_replace('/^http(s)?:\/\/(www\.)?([^\/]+)\/?$/i', '$3', $GLOBALS['TSFE']->tmpl->setup['config.']['baseURL']),
            'protocol' => (($_SERVER['HTTPS']) || ($_SERVER['SERVER_PORT'] == '443')) ? 'https://' : 'http://'
        ];

        // use old version if set
        if (
            (isset($GLOBALS['TSFE']->config['config']['tx_accelerator_cdn.']))
            && (is_array($GLOBALS['TSFE']->config['config']['tx_accelerator_cdn.']))
        ){
            $settings = array_merge($settings, $GLOBALS['TSFE']->config['config']['tx_accelerator_cdn.']);

        // use new version
        } else  {

            /** @var  $objectManager */
            $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
            $configurationManager = $objectManager->get(ConfigurationManagerInterface::class);
            $configuration = $configurationManager->getConfiguration(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
                'Accelerator'
            );

            if (
                (isset($configuration['cdn']))
                && (is_array($configuration['cdn']))
            ){
                $settings = array_merge($settings, $configuration['cdn']);
            }
        }

        return $settings;
    }


}

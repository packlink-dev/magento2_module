<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Helper;

use Magento\Framework\Locale\Config;

/**
 * Class LocalizationHelper
 *
 * @package Packlink\PacklinkPro\Helper
 */
class LocalizationHelper
{
    /**
     * @var Config
     */
    private $localeConfig;

    /**
     * LocalizationHelper constructor.
     *
     * @param \Magento\Framework\Locale\Config $config
     */
    public function __construct(Config $config)
    {
        $this->localeConfig = $config;
    }

    /**
     * Copies translation files for all supported Packlink languages.
     */
    public function copyTranslations()
    {
        $this->copyTranslationsForLocale('es');
        $this->copyTranslationsForLocale('de');
        $this->copyTranslationsForLocale('fr');
        $this->copyTranslationsForLocale('it');
    }

    /**
     * Copies translation files for a given language locale to all Magento supported language locales.
     *
     * @param string $locale Language locale in lowercase (ex. es, de, fr...).
     */
    private function copyTranslationsForLocale($locale)
    {
        $locales = $this->localeConfig->getAllowedLocales();

        $selectedLocales = array_filter(
            $locales,
            function ($key) use ($locale) {
                return strpos($key, "{$locale}_") === 0;
            }
        );

        $translationDir = __DIR__ . '/../i18n/';

        foreach ($selectedLocales as $selectedLocale) {
            copy(
                $translationDir . $locale . '_' . strtoupper($locale) . '.csv',
                $translationDir . $selectedLocale . '.csv'
            );
        }
    }
}

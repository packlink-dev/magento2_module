<?php

namespace Packlink\PacklinkPro\Helper;

use Magento\Framework\ObjectManagerInterface;

/**
 * Class CspNonceProviderFactory.
 *
 * @package Packlink\PacklinkPro\Helper
 */
class CspNonceProviderFactory
{
    /** @var ObjectManagerInterface  */
    private $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create an instance of CspNonceProvider if it exists.
     *
     * @return object|null
     */
    public function create()
    {
        if (class_exists('Magento\Csp\Helper\CspNonceProvider')) {
            return $this->objectManager->get('Magento\Csp\Helper\CspNonceProvider');
        }

        return null;
    }
}

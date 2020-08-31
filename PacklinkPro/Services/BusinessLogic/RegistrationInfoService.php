<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Services\BusinessLogic;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Information;
use Magento\Store\Model\StoreManagerInterface;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Registration\RegistrationInfo;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Registration\RegistrationInfoService as RegistrationInfoServiceInterface;

/**
 * Class RegistrationInfoService
 *
 * @package Packlink\PacklinkPro\Services\BusinessLogic
 */
class RegistrationInfoService implements RegistrationInfoServiceInterface
{
    /**
     * @var \Magento\Backend\Model\Auth\Session
     */
    private $authSession;
    /**
     * @var \Magento\Store\Model\Information
     */
    private $storeInfo;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        Session $authSession,
        Information $storeInfo,
        StoreManagerInterface $storeManager
    ) {
        $this->authSession = $authSession;
        $this->storeInfo = $storeInfo;
        $this->storeManager = $storeManager;
    }

    /**
     * Returns registration data from the integration.
     *
     * @return RegistrationInfo
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getRegistrationInfoData()
    {
        $data = $this->getRegistrationData();

        return new RegistrationInfo($data['email'], $data['phone'], $data['source']);
    }

    /**
     * Returns registration data from Magento.
     *
     * @return array
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getRegistrationData()
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore();
        $user = $this->authSession->getUser();

        return [
            'email' => $user ? $user->getEmail() : '',
            'phone' => $this->storeInfo->getStoreInformationObject($store)->getData('phone'),
            'source' => $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB),
        ];
    }
}

<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2022 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Payment\Model\Config as PaymentConfig;
use Packlink\PacklinkPro\Bootstrap;
use Magento\Payment\Model\MethodList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Payment\Model\MethodInterface;

/**
 * Class DefaultWarehouse
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class CashOnDelivery extends Configuration
{
    private $paymentConfig;
    private $storeManager;
    private $scopeConfig;

    /**
     * @var Session
     */
    private $authSession;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Backend\Model\Auth\Session $session
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory,
        Session $session,
        PaymentConfig $paymentConfig,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context, $bootstrap, $jsonFactory);

        $this->allowedActions = ['getCashOnDeliveryConfig', 'saveCashOnDeliveryConfig'];

        $this->authSession    = $session;
        $this->paymentConfig  = $paymentConfig;
        $this->storeManager   = $storeManager;
        $this->scopeConfig    = $scopeConfig;
    }

    /**
     * Returns Packlink cash on delivery configuration.
     *
     * @return \Magento\Framework\Controller\Result\Json
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function getCashOnDeliveryConfig()
    {
        $cashOnDeliveryConfiguration = $this->cashOnDeliveryController->getCashOnDeliveryConfiguration();
        $offlinePaymentMethods = [];

        $response = [
            'configuration' => $cashOnDeliveryConfiguration ? $cashOnDeliveryConfiguration->toArray() : [],
            'paymentMethods' => $this->getOfflinePaymentMethods(),
        ];

        return $this->resultJsonFactory->create()->setData($response);
    }

    /**
     * Performs location search.
     *
     * @return array|\Magento\Framework\Controller\Result\Json
     */
    protected function saveCashOnDeliveryConfig()
    {
        $input = $this->getPacklinkPostData();

        try {
            $this->cashOnDeliveryController->saveConfig($input);
        } catch (\Exception $e) {
            return $this->result;
        }

        return $this->resultJsonFactory->create()->setData(['success' => true]);
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getOfflinePaymentMethods()
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $methods = $this->paymentConfig->getActiveMethods($storeId);

        $result = [];

        /** @var MethodInterface $method */
        foreach ($methods as $method) {
            $code  = $method->getCode();
            $title = (string)$this->scopeConfig->getValue(
                "payment/{$code}/title",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );

            // Prefer the official flag if present
            $isOffline = method_exists($method, 'isOffline') ? (bool)$method->isOffline() : null;

            // Fallback heuristic: treat non-gateway methods as offline
            if ($isOffline === null) {
                $isGateway = method_exists($method, 'isGateway') && (bool)$method->isGateway();
                $isOffline = !$isGateway;
            }

            if ($isOffline) {
                $result[] = [
                    'name'        => $code,           // e.g. 'cashondelivery', 'banktransfer', 'checkmo', 'purchaseorder', 'free'
                    'displayName' => $title !== '' ? $title : $code,
                ];
            }
        }

        return $result;
    }
}

<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\DefaultParcelController;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\DTO\Exceptions\FrontDtoValidationException;

/**
 * Class DefaultParcel
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class DefaultParcel extends Configuration
{
    /**
     * @var DefaultParcelController
     */
    private $baseController;

    /**
     * DefaultParcel constructor.
     *
     * @param Context $context
     * @param Bootstrap $bootstrap
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context, $bootstrap, $jsonFactory);

        $this->allowedActions = [
            'getDefaultParcel',
            'setDefaultParcel',
        ];

        $this->baseController = new DefaultParcelController();
    }

    /**
     * Returns default parcel.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getDefaultParcel()
    {
        $parcel = $this->baseController->getDefaultParcel();

        return $this->result->setData($parcel ? $parcel->toArray() : []);
    }

    /**
     * Sets default parcel.
     *
     * @return \Magento\Framework\Controller\Result\Json
     *
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    protected function setDefaultParcel()
    {
        $data = $this->getPacklinkPostData();
        $data['default'] = true;

        try {
            $this->baseController->setDefaultParcel($data);
        } catch (FrontDtoValidationException $e) {
            return $this->formatValidationErrorResponse($e->getValidationErrors());
        }

        return $this->getDefaultParcel();
    }
}

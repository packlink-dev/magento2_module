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
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\DTO\Exceptions\FrontDtoValidationException;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Http\DTO\ParcelInfo;

/**
 * Class DefaultParcel
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class DefaultParcel extends Configuration
{
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

        $this->fields = [
            'weight',
            'width',
            'height',
            'length',
        ];
    }

    /**
     * Returns default parcel.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getDefaultParcel()
    {
        $parcel = $this->getConfigService()->getDefaultParcel();

        if (!$parcel) {
            return $this->result;
        }

        return $this->result->setData($parcel->toArray());
    }

    /**
     * Sets default parcel.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function setDefaultParcel()
    {
        $data = $this->getPacklinkPostData();
        $data['default'] = true;

        try {
            $parcelInfo = ParcelInfo::fromArray($data);
        } catch (FrontDtoValidationException $e) {
            return $this->formatValidationErrorResponse($e->getValidationErrors());
        }

        $this->getConfigService()->setDefaultParcel($parcelInfo);

        return $this->result->setData($parcelInfo->toArray());
    }
}

<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception;
use Packlink\PacklinkPro\Bootstrap;
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

        return $this->result->setData($this->getConfigService()->getDefaultParcel());
    }

    /**
     * Sets default parcel.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function setDefaultParcel()
    {
        $data = $this->getPacklinkPostData();

        $validationResult = $this->validate($data);
        if (!empty($validationResult)) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result->setData($validationResult);
        }

        $data['default'] = true;
        $parcelInfo = ParcelInfo::fromArray($data);
        $this->getConfigService()->setDefaultParcel($parcelInfo);

        return $this->result->setData($data);
    }

    /**
     * Validates default parcel data.
     *
     * @param array $data
     *
     * @return array Validation result.
     */
    private function validate(array $data)
    {
        $result = [];

        foreach ($this->fields as $field) {
            if (!empty($data[$field])) {
                $value = (float)$data[$field];
                if ($value <= 0 || !\is_float($value)) {
                    $result[$field] = __('Field must be valid number.');
                }
            } else {
                $result[$field] = __('Field is required.');
            }
        }

        return $result;
    }
}

<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2022 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Webapi\Exception;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Helper\SystemInfoHelper;
use Packlink\PacklinkPro\Helper\UrlHelper;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\DebugController;

/**
 * Class Debug
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class Debug extends Configuration
{
    const SYSTEM_INFO_FILE_NAME = 'packlink-debug-data.zip';
    /**
     * @var \Packlink\PacklinkPro\Helper\SystemInfoHelper
     */
    private $systemInfoHelper;
    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    private $fileFactory;
    /**
     * @var UrlHelper
     */
    private $urlHelper;
    /**
     * @var FormKey
     */
    private $formKey;
    /**
     * @var DebugController
     */
    private $baseController;

    /**
     * Debug constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Packlink\PacklinkPro\Helper\SystemInfoHelper $systemInfoHelper
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory,
        SystemInfoHelper $systemInfoHelper,
        FileFactory $fileFactory,
        UrlHelper $urlHelper,
        FormKey $formKey
    ) {
        parent::__construct($context, $bootstrap, $jsonFactory);

        $this->allowedActions = [
            'getStatus',
            'setStatus',
            'getSystemInfo',
        ];

        $this->systemInfoHelper = $systemInfoHelper;
        $this->fileFactory = $fileFactory;
        $this->urlHelper = $urlHelper;
        $this->formKey = $formKey;
        $this->baseController = new DebugController();
    }

    /**
     * Returns debug mode status.
     *
     * @return \Magento\Framework\Controller\Result\Json
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getStatus()
    {
        return $this->result->setData([
            'status' => $this->baseController->getStatus(),
            'downloadUrl' => $this->urlHelper->getBackendUrl(
                'packlink/configuration/debug',
                [
                    'action' => 'getSystemInfo',
                    'ajax' => 1,
                    'form_key' => $this->formKey->getFormKey(),
                ]
            ),
        ]);
    }

    /**
     * Sets debug mode status.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function setStatus()
    {
        $data = $this->getPacklinkPostData();
        if (!isset($data['status']) || !is_bool($data['status'])) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result;
        }

        $this->baseController->setStatus((bool)$data['status']);

        return $this->result->setData(['status' => $data['status']]);
    }

    /**
     * Returns system information in a Zip file for download.
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Exception
     */
    protected function getSystemInfo()
    {
        return $this->fileFactory->create(
            self::SYSTEM_INFO_FILE_NAME,
            file_get_contents($this->systemInfoHelper->getSystemInfo()),
            DirectoryList::VAR_DIR
        );
    }
}

<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

/**
 * Class Configuration
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Configuration
 */
class Configuration extends Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = [
        'dashboard',
        'debug',
        'defaultparcel',
        'defaultwarehouse',
        'orderstatemapping',
        'shippingmethods',
    ];
    /**
     * Actions that are being handled by the controller.
     *
     * @var array
     */
    protected $allowedActions = [];
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var Json
     */
    protected $result;
    /**
     * Available controller fields.
     *
     * @var array
     */
    protected $fields;
    /**
     * @var ConfigurationService
     */
    private $configService;

    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);

        $this->resultJsonFactory = $jsonFactory;
        $this->result = $this->resultJsonFactory->create();

        $bootstrap->initInstance();
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     */
    public function execute()
    {
        $request = $this->getRequest();

        if (!$request->getParam('action')
            || !method_exists($this, $request->getParam('action'))
            || !\in_array($request->getParam('action'), $this->allowedActions, true)
        ) {
            $this->result->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);

            return $this->result->setData(
                [
                    'success' => false,
                    'message' => _('Wrong action requested.'),
                ]
            );
        }

        $action = $request->getParam('action');

        return $this->$action();
    }

    /**
     * Returns post data from Packlink request.
     *
     * @return array
     */
    protected function getPacklinkPostData()
    {
        return json_decode(file_get_contents('php://input'), true);
    }

    /**
     * Returns instance of configuration service.
     *
     * @return ConfigurationService
     */
    protected function getConfigService()
    {
        if ($this->configService === null) {
            $this->configService = ServiceRegister::getService(
                \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration::CLASS_NAME
            );
        }

        return $this->configService;
    }
}

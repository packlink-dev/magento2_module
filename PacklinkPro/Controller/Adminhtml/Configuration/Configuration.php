<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2021 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Webapi\Exception;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\DTO\ValidationError;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Controllers\ConfigurationController;

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
        'modulestate',
        'onboarding',
        'login',
        'registrationregions',
        'registration',
        'systeminfo',
    ];
    /**
     * Translation messages for fields that are being validated.
     *
     * @var array
     */
    protected $validationMessages = [
        'email' => 'Field must be valid email.',
        'phone' => 'Field must be valid phone number.',
        'weight' => 'Weight must be a positive decimal number.',
        'postal_code' => 'Postal code is not correct.',
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
     * @var ConfigurationService
     */
    private $configService;
    /**
     * @var ConfigurationController
     */
    private $baseController;

    /**
     * Configuration constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        Bootstrap $bootstrap,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context);

        $this->resultJsonFactory = $jsonFactory;
        $this->result = $this->resultJsonFactory->create();
        $this->baseController = new ConfigurationController();

        $this->allowedActions = ['getData'];

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
     * Returns data for the configuration page.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function getData()
    {
        return $this->result->setData(
            [
                'helpUrl' => $this->baseController->getHelpLink(),
                'version' => $this->getConfigService()->getModuleVersion(),
            ]
        );
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
     * Formats a collection on front DTO entity to the response array.
     *
     * @param \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\DTO\BaseDto[]|\Packlink\PacklinkPro\IntegrationCore\Infrastructure\Data\DataTransferObject[] $entities
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function formatDtoEntitiesResponse($entities)
    {
        $response = [];

        foreach ($entities as $entity) {
            $response[] = $entity->toArray();
        }

        return $this->result->setData($response);
    }

    /**
     * Returns a 400 validation error response.
     *
     * @param ValidationError[] $errors
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function formatValidationErrorResponse($errors)
    {
        $response = [];

        foreach ($errors as $error) {
            $response[$error->field] = $this->getValidationMessage($error->code, $error->field);
        }

        $this->result->setHttpResponseCode(400);

        return $this->result->setData($response);
    }

    /**
     * Returns a 404 not found error response.
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function formatNotFoundResponse()
    {
        $this->result->setHttpResponseCode(Exception::HTTP_NOT_FOUND);

        return $this->result->setData(['message' => _('Not found')]);
    }

    /**
     * Returns a validation message for validation error.
     *
     * @param string $code
     * @param string $field
     *
     * @return \Magento\Framework\Phrase
     */
    protected function getValidationMessage($code, $field)
    {
        if ($code === ValidationError::ERROR_REQUIRED_FIELD) {
            return __('Field is required.');
        }

        if (in_array($field, ['width', 'length', 'height'])) {
            return __('Field must be a positive integer.');
        }

        return __($this->validationMessages[$field]);
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

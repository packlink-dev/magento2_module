<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Ui\Component\Listing\Column;

use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Helper\UrlHelper;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Interfaces\OrderRepository;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;
use Packlink\PacklinkPro\Services\BusinessLogic\OrderRepositoryService;

/**
 * Class OrderDraft
 *
 * @package Packlink\PacklinkPro\Ui\Component\Listing\Column
 */
class OrderDraft extends Column
{
    const ALT_FIELD = 'title';
    /**
     * @var Repository
     */
    protected $assetRepo;
    /**
     * @var UrlHelper
     */
    private $urlHelper;

    /**
     * OrderDraft constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Repository $assetRepository
     * @param UrlHelper $urlHelper
     * @param Bootstrap $bootstrap
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        Repository $assetRepository,
        UrlHelper $urlHelper,
        Bootstrap $bootstrap,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);

        $this->assetRepo = $assetRepository;
        $this->urlHelper = $urlHelper;

        $bootstrap->initInstance();
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     *
     * @return array
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            /** @var ConfigurationService $configService */
            $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
            $userInfo = $configService->getUserInfo();
            if ($userInfo === null) {
                return $dataSource;
            }

            /** @var OrderRepositoryService $orderRepositoryService */
            $orderRepositoryService = ServiceRegister::getService(OrderRepository::CLASS_NAME);

            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                $orderDetails = $orderRepositoryService->getOrderDetailsById((int)$item['entity_id']);
                if ($orderDetails === null || $orderDetails->getShipmentReference() === null) {
                    continue;
                }

                $reference = $orderDetails->getShipmentReference();

                $logoUrl = $this->assetRepo->getUrl('Packlink_PacklinkPro::images/logo.png');

                if ($orderDetails->isDeleted()) {
                    $element = '<img class="pl-order-draft-icon" src="' . $logoUrl . '"/>';
                } else {
                    $draftUrl = $this->urlHelper->getOrderDraftUrl($userInfo->country, $reference);
                    $element = html_entity_decode(
                        '<a href="' . $draftUrl . '" target="_blank">'
                        . '<img class="pl-order-draft-icon" src="' . $logoUrl
                        . '" alt="' . __('View on Packlink PRO') . '" title="' . __('View on Packlink PRO') . '"/>'
                        . '</a>'
                    );
                }

                $item[$fieldName] = $element;
            }
        }

        return $dataSource;
    }
}

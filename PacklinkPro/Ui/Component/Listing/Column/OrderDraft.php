<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Ui\Component\Listing\Column;

use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Helper\UrlHelper;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Configuration;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\ShipmentDraft\ShipmentDraftService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\TaskExecution\QueueItem;
use Packlink\PacklinkPro\Services\BusinessLogic\ConfigurationService;

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

            /** @var OrderShipmentDetailsService $orderShipmentDetailsService */
            $orderShipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);

            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                $orderDetails = $orderShipmentDetailsService->getDetailsByOrderId($item['entity_id']);

                /** @var ShipmentDraftService $draftService */
                $draftService = ServiceRegister::getService(ShipmentDraftService::CLASS_NAME);
                $draftStatus = $draftService->getDraftStatus($item['entity_id']);
                $logoUrl = $this->assetRepo->getUrl('Packlink_PacklinkPro::images/logo.png');
                $element = '';

                switch ($draftStatus->status) {
                    case QueueItem::COMPLETED:
                        if ($orderDetails) {
                            $deleted = $orderShipmentDetailsService->isShipmentDeleted($orderDetails->getReference());

                            $element = html_entity_decode(
                                '<a class="pl-draft-button-wrapper" ' . ($deleted ? 'disable ' : 'href="' . $orderDetails->getShipmentUrl() . '" target="_blank" ') . '>'
                                . '<button class="pl-draft-button" ' . ($deleted ? 'disabled ' : '' ) . '><img class="pl-order-draft-icon" src="' . $logoUrl . '" alt=""/>'
                                . __('View on Packlink')
                                . '</button></a>'
                            );
                        }

                        break;
                    case QueueItem::QUEUED:
                    case QueueItem::IN_PROGRESS:
                        $element = '<div class="pl-draft-in-progress" data-order-id="' . $item['entity_id'] . '">'
                            . __('Draft is currently being created.')
                            . '<script type="text/javascript">plDraftInProgressInit("' . $item['entity_id'] . '");</script>'
                            . '</div>';
                        break;
                    default:
                        $element = '<button onClick="plCreateDraftClick(event)" class="pl-create-draft-button" data-order-id="' . $item['entity_id'] . '"><img class="pl-order-draft-icon" src="' .$logoUrl . '" alt="">'
                            . __('Send with Packlink')
                            . '</button>';
                }

                $item[$fieldName] = $element;
            }
        }

        return $dataSource;
    }
}

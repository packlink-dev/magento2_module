<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2020 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\OrderService;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;

/**
 * Class BulkPrint
 *
 * @package Packlink\PacklinkPro\Controller\Adminhtml\Order
 */
class BulkPrint extends Action
{
    /**
     * @var string
     */
    private $redirectUrl = '*/*/';
    /**
     * @var Filter
     */
    private $filter;
    /**
     * @var object
     */
    private $collectionFactory;
    /**
     * @var FileFactory
     */
    private $fileFactory;
    /**
     * @var ShipmentRepository
     */
    private $shipmentRepository;
    /**
     * @var OrderShipmentDetailsService
     */
    private $orderShipmentDetailsService;

    /**
     * BulkPrint constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Ui\Component\MassAction\Filter $filter
     * @param \Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory $collectionFactory
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     * @param \Magento\Sales\Model\Order\ShipmentRepository $shipmentRepository
     */
    public function __construct(
        Action\Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        Bootstrap $bootstrap,
        FileFactory $fileFactory,
        ShipmentRepository $shipmentRepository
    ) {
        parent::__construct($context);

        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->fileFactory = $fileFactory;
        $this->shipmentRepository = $shipmentRepository;

        $bootstrap->initInstance();
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        try {
            /** @var AbstractCollection $collection */
            $collection = $this->filter->getCollection($this->collectionFactory->create());

            return $this->bulkPrintShipmentLabels($collection);
        } catch (\Exception $e) {
            Logger::logError(__($e->getMessage()), 'Integration');
            $this->messageManager->addErrorMessage($e->getMessage());
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            return $resultRedirect->setPath($this->redirectUrl);
        }
    }

    /**
     * Prints all available shipment labels in one merged PDF document.
     *
     * @param AbstractCollection $collection
     *
     * @return \Magento\Framework\App\ResponseInterface
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\Exceptions\OrderShipmentDetailsNotFound
     */
    private function bulkPrintShipmentLabels(AbstractCollection $collection)
    {
        $shipmentIds = $collection->getAllIds();

        $files = $this->saveFilesLocally($shipmentIds);

        if (empty($files)) {
            $this->messageManager->addNoticeMessage(__('No Packlink shipment labels available.'));
        } else {
            try {
                $pdfContent = $this->mergePdfFiles($files);
                $now = date('Y-m-d_H-i-s');
                $fileName = "Packlink-bulk-shipment-labels_$now.pdf";

                $result = $this->fileFactory->create(
                    $fileName,
                    $pdfContent,
                    DirectoryList::VAR_DIR,
                    'application/pdf'
                );

                return $result;
            } catch (\Exception $e) {
                Logger::logError(__('Unable to create bulk labels file. Error: ') . $e->getMessage(), 'Integration');
                $this->messageManager->addErrorMessage(
                    __('Unable to create bulk labels file. Error: ') . $e->getMessage()
                );
            }
        }

        return $this->_redirect($this->_redirect->getRefererUrl());
    }

    /**
     * Saves PDF files to temporary directory on the system.
     *
     * @param array $shipmentIds Array of shipment IDs.
     *
     * @return array
     *
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Packlink\PacklinkPro\IntegrationCore\BusinessLogic\OrderShipmentDetails\Exceptions\OrderShipmentDetailsNotFound
     */
    private function saveFilesLocally(array $shipmentIds)
    {
        $orders = $this->getAllOrderDetails($shipmentIds);
        $orderService = ServiceRegister::getService(OrderService::CLASS_NAME);

        $files = [];
        foreach ($orders as $orderDetails) {
            $labels = $orderDetails->getShipmentLabels();

            if (empty($labels)) {
                /** @var OrderService $orderService */
                $labels = $orderService->getShipmentLabels($orderDetails->getReference());
                $this->getOrderShipmentDetailsService()->setLabelsByReference(
                    $orderDetails->getReference(),
                    $labels
                );
            }

            foreach ($labels as $label) {
                $this->getOrderShipmentDetailsService()->markLabelPrinted(
                    $orderDetails->getReference(),
                    $label->getLink()
                );

                $file = $this->savePDF($label->getLink());
                if ($file) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    /**
     * Returns order details for orders with shipment IDs.
     *
     * @param array $shipmentIds Array of shipment IDs.
     *
     * @return OrderShipmentDetails[] Array of order details entities..
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getAllOrderDetails(array $shipmentIds)
    {
        $orders = [];

        foreach ($shipmentIds as $shipmentId) {
            /** @var Shipment $shipment */
            $shipment = $this->shipmentRepository->get($shipmentId);

            if ($shipment === null) {
                continue;
            }

            $orderDetails = $this->getOrderShipmentDetailsService()->getDetailsByOrderId((string)$shipment->getOrderId());
            if ($orderDetails !== null) {
                $orders[] = $orderDetails;
            }
        }

        return $orders;
    }

    /**
     * Saves PDF file from provided URL to temporary location on the system.
     *
     * @param string $link
     *
     * @return false|string
     */
    private function savePDF($link)
    {
        $data = file_get_contents($link);

        if ($data === false) {
            return $data;
        }

        $file = tempnam(sys_get_temp_dir(), 'packlink_pdf_');
        file_put_contents($file, $data);

        return $file;
    }

    /**
     * Merges all PDF files into one PDF.
     *
     * @param array $files Array of PDF label files.
     *
     * @return string Merged PDF content.
     *
     * @throws \Zend_Pdf_Exception
     */
    private function mergePdfFiles($files)
    {
        $pdf = new \Zend_Pdf();
        $extractor = new \Zend_Pdf_Resource_Extractor();
        foreach ($files as $file) {
            $labelPdf = \Zend_Pdf::load($file);
            foreach ($labelPdf->pages as $page) {
                $pdf->pages[] = $extractor->clonePage($page);
            }
        }

        return $pdf->render();
    }

    /**
     * Returns an instance of order shipment details service.
     *
     * @return OrderShipmentDetailsService
     */
    private function getOrderShipmentDetailsService()
    {
        if ($this->orderShipmentDetailsService === null) {
            $this->orderShipmentDetailsService = ServiceRegister::getService(OrderShipmentDetailsService::CLASS_NAME);
        }

        return $this->orderShipmentDetailsService;
    }
}

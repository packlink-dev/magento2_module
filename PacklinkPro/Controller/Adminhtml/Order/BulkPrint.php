<?php
/**
 * @package    Packlink_PacklinkPro
 * @author     Packlink Shipping S.L.
 * @copyright  2019 Packlink
 */

namespace Packlink\PacklinkPro\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Module\Dir\Reader;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\ShipmentRepository;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use Packlink\PacklinkPro\Bootstrap;
use Packlink\PacklinkPro\Entity\ShopOrderDetails;
use Packlink\PacklinkPro\IntegrationCore\BusinessLogic\Order\Interfaces\OrderRepository;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\Logger\Logger;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PacklinkPro\IntegrationCore\Infrastructure\ServiceRegister;
use Packlink\PacklinkPro\Services\BusinessLogic\OrderRepositoryService;

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
     * @var Reader
     */
    private $moduleReader;
    /**
     * @var FileFactory
     */
    private $fileFactory;
    /**
     * @var ShipmentRepository
     */
    private $shipmentRepository;
    /**
     * @var OrderRepositoryService
     */
    private $orderRepositoryService;

    /**
     * BulkPrint constructor.
     *
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Ui\Component\MassAction\Filter $filter
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory
     * @param \Packlink\PacklinkPro\Bootstrap $bootstrap
     * @param \Magento\Framework\Module\Dir\Reader $reader
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     */
    public function __construct(
        Action\Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        Bootstrap $bootstrap,
        Reader $reader,
        FileFactory $fileFactory,
        ShipmentRepository $shipmentRepository
    ) {
        parent::__construct($context);

        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->moduleReader = $reader;
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
     */
    private function bulkPrintShipmentLabels(AbstractCollection $collection)
    {
        $shipmentIds = $collection->getAllIds();

        $tmpDirectory = $this->getTmpDirectory();
        if (!empty($shipmentIds) && !is_dir($tmpDirectory) && !mkdir($tmpDirectory)) {
            throw new \RuntimeException(sprintf(__("Directory '%s' was not created"), $tmpDirectory));
        }

        $this->saveFilesLocally($shipmentIds);

        if ($this->directoryEmpty($tmpDirectory)) {
            $this->messageManager->addNoticeMessage(__('No Packlink shipment labels available.'));
        } else {
            try {
                $pdfContent = $this->mergePdfFiles($tmpDirectory);
                $this->deleteTemporaryFiles($tmpDirectory);
                $now = date('Y-m-d_H-i-s');
                $fileName = "Packlink-bulk-shipment-labels_$now.pdf";

                $result = $this->fileFactory->create(
                    $fileName,
                    $pdfContent,
                    DirectoryList::VAR_DIR,
                    'application/pdf'
                );

                $this->markLabelsPrinted($shipmentIds);

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
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function saveFilesLocally(array $shipmentIds)
    {
        $orders = $this->getAllOrderDetails($shipmentIds);

        foreach ($orders as $orderDetails) {
            $labels = $orderDetails->getShipmentLabels();
            foreach ($labels as $label) {
                $this->savePDF($label->getLink());
            }
        }
    }

    /**
     * Marks labels for orders with provided IDs as printed.
     *
     * @param array $orderIds Array of order IDs.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function markLabelsPrinted(array $orderIds)
    {
        $orderDetailsRepository = RepositoryRegistry::getRepository(ShopOrderDetails::getClassName());
        $orders = $this->getAllOrderDetails($orderIds);

        foreach ($orders as $orderDetails) {
            $labels = $orderDetails->getShipmentLabels();

            foreach ($labels as $label) {
                if (!$label->isPrinted()) {
                    $label->setPrinted(true);
                }
            }

            $orderDetailsRepository->update($orderDetails);
        }
    }

    /**
     * Returns order details for orders with shipment IDs.
     *
     * @param array $shipmentIds Array of shipment IDs.
     *
     * @return ShopOrderDetails[] Array of order details entities..
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\PacklinkPro\IntegrationCore\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
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

            $orderDetails = $this->getOrderRepositoryService()->getOrderDetailsById((int)$shipment->getOrderId());
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
     */
    private function savePDF($link)
    {
        $file = fopen($link, 'rb');
        if ($file) {
            $tmpFile = fopen($this->getTmpDirectory() . microtime() . '.pdf', 'wb');
            if ($tmpFile) {
                while (!feof($file)) {
                    fwrite($tmpFile, fread($file, 1024 * 8), 1024 * 8);
                }

                fclose($tmpFile);
            }

            fclose($file);
        }
    }

    /**
     * Merges all PDF files within provided directory into one PDF.
     *
     * @param string $pdfDirectory Path to directory that contains PDF files.
     *
     * @return string Merged PDF content.
     *
     * @throws \Zend_Pdf_Exception
     */
    private function mergePdfFiles($pdfDirectory)
    {
        $pdf = new \Zend_Pdf();
        $extractor = new \Zend_Pdf_Resource_Extractor();
        $iterator = new \DirectoryIterator($pdfDirectory);
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isDot()) {
                $labelPdf = \Zend_Pdf::load($fileInfo->getPath() . '/' . $fileInfo->getFilename());
                foreach ($labelPdf->pages as $page) {
                    $pdf->pages[] = $extractor->clonePage($page);
                }
            }
        }

        return $pdf->render();
    }

    /**
     * Deletes all temporary files created in the process of bulk printing of labels.
     *
     * @param string $tmpDirectory
     */
    private function deleteTemporaryFiles($tmpDirectory)
    {
        $it = new \RecursiveDirectoryIterator($tmpDirectory, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator(
            $it,
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($tmpDirectory);
    }

    /**
     * Returns path to temporary directory within module, used for storing shipment labels.
     *
     * @return string
     */
    private function getTmpDirectory()
    {
        return $this->moduleReader->getModuleDir('', 'Packlink_PacklinkPro') . '/tmp/';
    }

    /**
     * Checks if a directory is empty.
     *
     * @param string $dir Path to directory.
     *
     * @return bool Returns TRUE if directory is empty, otherwise returns FALSE.
     */
    private function directoryEmpty($dir)
    {
        return !(new \FilesystemIterator($dir))->valid();
    }

    /**
     * Returns an instance of order repository service.
     *
     * @return OrderRepositoryService
     */
    private function getOrderRepositoryService()
    {
        if ($this->orderRepositoryService === null) {
            $this->orderRepositoryService = ServiceRegister::getService(OrderRepository::CLASS_NAME);
        }

        return $this->orderRepositoryService;
    }
}

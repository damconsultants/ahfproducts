<?php

namespace DamConsultants\Ahfproducts\Controller\Adminhtml\Index;

use DamConsultants\Ahfproducts\Model\ResourceModel\Collection\BynderUpdateSkuReportCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Framework\App\Response\Http\FileFactory;

class DownloadUpdateSkuReport extends Action
{
    public const ADMIN_RESOURCE = 'DamConsultants_Ahfproducts::menu_item10';

    private $collectionFactory;
    private $fileFactory;

    public function __construct(
        Action\Context $context,
        BynderUpdateSkuReportCollectionFactory $collectionFactory,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->collectionFactory = $collectionFactory;
        $this->fileFactory = $fileFactory;
    }

    public function execute()
    {
        $token = trim((string)$this->getRequest()->getParam('token'));
        $collection = $this->collectionFactory->create();
        if ($token !== '') {
            $collection->addFieldToFilter('token', $token);
        }

        $lines = ["Token,SKU,Attribute,Store,Status,Message,Updated At"];
        foreach ($collection as $item) {
            $lines[] = implode(',', array_map([$this, 'escapeCsv'], [
                $item->getToken(),
                $item->getSku(),
                $item->getSelectAttribute(),
                $item->getSelectStore(),
                $item->getStatus(),
                $item->getMessage(),
                $item->getUpdatedAt()
            ]));
        }

        $filename = $token !== '' ? 'update-sku-report-' . $token . '.csv' : 'update-sku-report.csv';
        return $this->fileFactory->create($filename, implode("\n", $lines), \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR, 'text/csv');
    }

    private function escapeCsv($value)
    {
        return '"' . str_replace('"', '""', (string)$value) . '"';
    }
}

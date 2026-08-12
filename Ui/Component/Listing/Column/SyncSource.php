<?php
/**
 * ahfproducts 
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the ecomteck.com license that is
 * available through the world-wide-web at this URL:
 * https://ahfproducts .com/
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    DamConsultants
 * @package     DamConsultants_Ahfproducts
 */
namespace DamConsultants\Ahfproducts\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Store\Model\StoreManagerInterface;

class SyncSource extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Closed constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param StoreManagerInterface $storeManager
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                
                if ($item) {
                    if ($item['sync_source'] == 1) {
                        $type ='Manual';
                    } elseif ($item['sync_source'] == 2) {
                        $type = 'Update By Cron';
                    } else {
                        $type ="";
                    }
                    $item['sync_source'] = $type;
                }
            }
        }
        return $dataSource;
    }
}

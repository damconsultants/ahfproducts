<?php

namespace DamConsultants\Ahfproducts\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CmsPageSaveAfterObserver implements ObserverInterface
{
    /**
     * @var $helper
     */
    protected $_datahelper;
    /**
     * @var $cmsHelper
     */
    protected $cmsHelper;

    /**
     * CmsPageSaveAfterObserver
     * @param \DamConsultants\Ahfproducts\Helper\Data $dataHelper
     * @param \Magento\Cms\Helper\Page $cmsHelper
     */
    public function __construct(
        \DamConsultants\Ahfproducts\Helper\Data $dataHelper,
        \Magento\Cms\Helper\Page $cmsHelper
    ) {
        $this->_datahelper = $dataHelper;
        $this->cmsHelper = $cmsHelper;
    }
    /**
     * Execute
     *
     * @return $this
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        $page = $observer->getObject();
        $arr = [
         $page->getData('content')
        ];
        
        $url_data = [];

        $str = implode(" ", $arr);
        $image_arr = explode(" ", $str);
        foreach ($image_arr as $a) {
            preg_match('@src="([^"]+)"@', $a, $match);
            $src = array_pop($match);
            $img_arr = explode('?', ''.$src);
            $url_data[] = $img_arr[0];
        }

        if (!empty($url_data)) {
            $cmspage = array_filter($url_data);
            $pageId = $page->getData('page_id');
            $CMSPageURL = $this->cmsHelper->getPageUrl($pageId);
            $api_call = $this->_datahelper->getCheckBynder();
            $api_response = json_decode($api_call, true);
            if (isset($api_response['status']) == 1) {
                $assets = $this->_datahelper->getBynderDataCmsPage($CMSPageURL, $cmspage);
            }
        }
    }
}

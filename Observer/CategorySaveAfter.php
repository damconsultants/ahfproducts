<?php

namespace DamConsultants\Ahfproducts\Observer;

use Magento\Catalog\Model\Category;
use Magento\Framework\Event\ObserverInterface;

class Categorysaveafter implements ObserverInterface
{
    /**
     * @var $helper
     */
    protected $_datahelper;

    /**
     * Categorysaveafter
     * @param \DamConsultants\Ahfproducts\Helper\Data $dataHelper
     */
    public function __construct(
        \DamConsultants\Ahfproducts\Helper\Data $dataHelper
    ) {
        $this->_datahelper = $dataHelper;
    }
    /**
     * Execute
     *
     * @return $this
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $category = $observer->getEvent()->getData('category');
        $url = $category->getUrlPath();
        $BaseUrl = $this->_datahelper->getbaseurl();
        $categoryUrl = $BaseUrl . $url . '.html';

        $arr = [
            $category->getDescription()
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
            $category_description = array_filter($url_data);
            $api_call = $this->_datahelper->getCheckBynder();
            $api_response = json_decode($api_call, true);
            if (isset($api_response['status']) == 1) {
                $assets = $this->_datahelper->getBynderDataCmsPage($categoryUrl, $category_description);
            }
        }
    }
}

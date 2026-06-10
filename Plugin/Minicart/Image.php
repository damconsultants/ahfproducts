<?php

namespace DamConsultants\Ahfproducts\Plugin\Minicart;


class Image
{
    /**
     * @var $_registry
     */
    protected $_registry;
    /**
     * @var $_registry
     */
    protected $product;
    /**
     * Image
     * @param \Magento\Framework\Registry $Registry
     * @param \Magento\Catalog\Model\Product $product
     */
    public function __construct(
        \Magento\Framework\Registry $Registry,
        \Magento\Catalog\Model\Product $product
    ) {
        
        $this->_registry = $Registry;
        $this->product = $product;
    }

    /**
     * Around Get Item Data
     *
     * @param \Magento\Checkout\CustomerData\AbstractItem $subject
     * @param \Closure $proceed
     * @param \Magento\Quote\Model\Quote\Item $item
     */
    public function aroundGetItemData(
        \Magento\Checkout\CustomerData\AbstractItem $subject,
        \Closure $proceed,
        \Magento\Quote\Model\Quote\Item $item
    ) {

         $data = $proceed($item);
        $productId = $item->getProduct()->getId();
        $product = $this->product->load($productId);
        $bynderImage = $product->getData('bynder_multi_img');
		if (!empty($bynderImage)) {
			$json_value = json_decode($bynderImage, true);
            if (is_array($json_value) && !empty($json_value)) {
			    $thumbnail = 'Thumbnail';
				foreach ($json_value as $values) {
					if (isset($values['image_role'])) {
						foreach ($values['image_role'] as $image_role) {
							if ($image_role ==  $thumbnail) {
								$image_values = trim($values['thum_url']);
								$data['product_image']['src'] =  $image_values;
							}
						}
					}
                }
			} else {
				$data['product_image']['src'];
			}
		} else {
			$data['product_image']['src'];
		}
        return $data;
    }
}

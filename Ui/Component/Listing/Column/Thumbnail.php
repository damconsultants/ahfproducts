<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace DamConsultants\Ahfproducts\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;

class Thumbnail extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * @var $name
     */
    public const NAME = 'thumbnail';
    /**
     * @var $alt
     */
    public const ALT_FIELD = 'name';

    /**
     * @var \Magento\Catalog\Helper\Image
     */
    private $imageHelper;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Catalog\Model\ProductRepository $ProductRepository
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Catalog\Model\ProductRepository $ProductRepository,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->imageHelper = $imageHelper;
        $this->urlBuilder = $urlBuilder;
        $this->_productRepository = $ProductRepository;
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
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as & $item) {
                //echo "<pre>"; print_r($item['entity_id']);
                $_product = $this->_productRepository->getById($item['entity_id']);
                $image_value = $_product->getBynderMultiImg();
                if (!empty($image_value)) {
                    $item_old_value = json_decode($image_value, true);
                    foreach ($item_old_value as $img) {
                        if (isset($img['image_role']) && count($img['image_role']) > 0) {
                            foreach ($img['image_role'] as $roll) {
                                if ($roll == 'Thumbnail') {
                                    $product = new \Magento\Framework\DataObject($item);
                                    $imageHelper = $this->imageHelper->init($product, 'product_listing_thumbnail');
                                    $item[$fieldName . '_src'] = $img['thum_url'];
                                    $item[$fieldName . '_link'] = $this->urlBuilder->getUrl(
                                        'catalog/product/edit',
                                        [
                                            'id' => $product->getEntityId(),
                                            'store' => $this->context->getRequestParam('store')
                                        ]
                                    );
                                    $ogImgHp = $this->imageHelper->init($product, 'product_listing_thumbnail_preview');
                                    $item[$fieldName . '_orig_src'] = $img['thum_url'];
                                }
                            }
                        }
                    }
                    
                } else {
                    $product = new \Magento\Framework\DataObject($item);
                    $imageHelper = $this->imageHelper->init($product, 'product_listing_thumbnail');
                    $item[$fieldName . '_src'] = $imageHelper->getUrl();
                    $item[$fieldName . '_alt'] = $this->getAlt($item) ?: $imageHelper->getLabel();
                    $item[$fieldName . '_link'] = $this->urlBuilder->getUrl(
                        'catalog/product/edit',
                        ['id' => $product->getEntityId(), 'store' => $this->context->getRequestParam('store')]
                    );
                    $origImageHelper = $this->imageHelper->init($product, 'product_listing_thumbnail_preview');
                    $item[$fieldName . '_orig_src'] = $origImageHelper->getUrl();
                }
            }
        }
        return $dataSource;
    }

    /**
     * Get Alt
     *
     * @param array $row
     *
     * @return null|string
     */
    protected function getAlt($row)
    {
        $altField = $this->getData('config/altField') ?: self::ALT_FIELD;
        return $row[$altField] ?? null;
    }
}

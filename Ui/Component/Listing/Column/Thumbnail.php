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
    public const NAME = 'thumbnail';
    public const ALT_FIELD = 'name';

    /**
     * @var \Magento\Catalog\Helper\Image
     */
    protected $imageHelper;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $_productRepository;

    /**
     * Constructor
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);

        $this->imageHelper = $imageHelper;
        $this->urlBuilder = $urlBuilder;
        $this->_productRepository = $productRepository;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {

            $thumbnailFound = false;

            try {
                $_product = $this->_productRepository->getById($item['entity_id']);

                $imageValue = $_product->getBynderMultiImg();

                if (!empty($imageValue)) {

                    $decodedImages = json_decode($imageValue, true);

                    if (is_array($decodedImages)) {

                        foreach ($decodedImages as $sku => $images) {

                            if (!is_array($images)) {
                                continue;
                            }

                            foreach ($images as $img) {

                                if (
                                    isset($img['image_role']) &&
                                    is_array($img['image_role']) &&
                                    in_array('Thumbnail', $img['image_role'])
                                ) {

                                    $product = new \Magento\Framework\DataObject($item);

                                    $item[$fieldName . '_src'] = $img['thum_url'];
                                    $item[$fieldName . '_orig_src'] = $img['thum_url'];
                                    $item[$fieldName . '_alt'] = $img['alt_text'] ?? $this->getAlt($item);

                                    $item[$fieldName . '_link'] = $this->urlBuilder->getUrl(
                                        'catalog/product/edit',
                                        [
                                            'id' => $product->getEntityId(),
                                            'store' => $this->context->getRequestParam('store')
                                        ]
                                    );

                                    $thumbnailFound = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Optional: log error
            }

            /**
             * Fallback to Magento thumbnail
             */
            if (!$thumbnailFound) {

                $product = new \Magento\Framework\DataObject($item);

                $imageHelper = $this->imageHelper->init(
                    $product,
                    'product_listing_thumbnail'
                );

                $item[$fieldName . '_src'] = $imageHelper->getUrl();
                $item[$fieldName . '_alt'] = $this->getAlt($item) ?: $imageHelper->getLabel();

                $item[$fieldName . '_link'] = $this->urlBuilder->getUrl(
                    'catalog/product/edit',
                    [
                        'id' => $product->getEntityId(),
                        'store' => $this->context->getRequestParam('store')
                    ]
                );

                $origImageHelper = $this->imageHelper->init(
                    $product,
                    'product_listing_thumbnail_preview'
                );

                $item[$fieldName . '_orig_src'] = $origImageHelper->getUrl();
            }
        }

        return $dataSource;
    }

    /**
     * Get Alt Text
     *
     * @param array $row
     * @return string|null
     */
    protected function getAlt($row)
    {
        $altField = $this->getData('config/altField') ?: self::ALT_FIELD;

        return $row[$altField] ?? null;
    }
}
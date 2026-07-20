<?php

namespace DamConsultants\Ahfproducts\Plugin\Catalog;

use Magento\Catalog\Ui\Component\Listing\Columns\Thumbnail;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use DamConsultants\Ahfproducts\Helper\Data;

class ThumbnailPlugin
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Data
     */
    private $dataHelper;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        UrlInterface $urlBuilder,
        RequestInterface $request,
        Data $dataHelper
    ) {
        $this->productRepository = $productRepository;
        $this->urlBuilder = $urlBuilder;
        $this->request = $request;
        $this->dataHelper = $dataHelper;
    }

    /**
     * After Prepare Data Source
     *
     * @param Thumbnail $subject
     * @param array $result
     * @return array
     */
    public function afterPrepareDataSource(
        Thumbnail $subject,
        array $result
    ) {
        if (empty($result['data']['items'])) {
            return $result;
        }

        $fieldName = $subject->getData('name');

        foreach ($result['data']['items'] as &$item) {

            if (empty($item['entity_id'])) {
                continue;
            }

            $thumbnailFound = false;

            $product = $this->productRepository->getById((int)$item['entity_id']);

            $json = $product->getData('bynder_multi_img');

            if (!empty($json)) {

                $images = json_decode($json, true);

                if (is_array($images)) {

                    foreach ($images as $sku => $imageList) {

                        if (!is_array($imageList)) {
                            continue;
                        }

                        foreach ($imageList as $image) {

                            if (
                                !empty($image['thum_url']) &&
                                isset($image['image_role']) &&
                                is_array($image['image_role']) &&
                                in_array('Thumbnail', $image['image_role'])
                            ) {

                                $item[$fieldName . '_src'] = trim($image['thum_url']);
                                $item[$fieldName . '_orig_src'] = trim($image['thum_url']);
                                $item[$fieldName . '_alt'] = $image['alt_text'] ?? ($item['name'] ?? '');

                                $thumbnailFound = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            if (!$thumbnailFound) {

                $placeholder = $this->dataHelper->getPlaceHolderImage();

                $item[$fieldName . '_src'] = $placeholder;
                $item[$fieldName . '_orig_src'] = $placeholder;
                $item[$fieldName . '_alt'] = $item['name'] ?? '';
            }

            $item[$fieldName . '_link'] = $this->urlBuilder->getUrl(
                'catalog/product/edit',
                [
                    'id' => $item['entity_id'],
                    'store' => $this->request->getParam('store')
                ]
            );
        }

        return $result;
    }
}